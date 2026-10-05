<?php

namespace Tests\Feature;

use App\Mail\MfaOtpCodeMail;
use App\Models\MfaOtpChallenge;
use App\Models\User;
use App\Services\Security\EmailOtpService;
use App\Services\Security\RecoveryCodeService;
use App\Services\Security\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;
use Tests\TestCase;

class MfaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_user_is_restricted_to_mandatory_enrollment_after_first_factor(): void
    {
        $user = User::factory()->withoutMfa()->create();

        $this->actingAsWithoutMfa($user)->get('/dashboard')
            ->assertRedirect('/mfa/enroll');

        $this->actingAsWithoutMfa($user)->get('/mfa/enroll')
            ->assertRedirect('/mfa/enroll/authenticator');

        $this->actingAsWithoutMfa($user)->post('/logout')->assertRedirect('/login');
    }

    public function test_administrator_cannot_bypass_enrollment_by_entering_an_admin_url(): void
    {
        $admin = User::factory()->administrator()->withoutMfa()->create();

        $this->actingAsWithoutMfa($admin)->get('/admin')
            ->assertRedirect('/mfa/enroll');
    }

    public function test_authenticator_enrollment_generates_encrypted_secret_and_local_qr_without_enabling_mfa(): void
    {
        Http::fake();
        $user = User::factory()->withoutMfa()->create(['email' => 'person@example.test']);

        $response = $this->actingAsWithoutMfa($user)->get('/mfa/enroll/authenticator');

        $response->assertOk()
            ->assertSee('Set up authenticator app')
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee('person@example.test');

        $user->refresh();
        $this->assertNotNull($user->pending_totp_secret);
        $this->assertNull($user->mfa_enabled_at);
        $this->assertNotSame(
            $user->pending_totp_secret,
            DB::table('users')->where('id', $user->id)->value('pending_totp_secret'),
        );
        $this->assertArrayNotHasKey('pending_totp_secret', $user->toArray());
        $this->assertArrayNotHasKey('totp_secret', $user->toArray());
        Http::assertNothingSent();
    }

    public function test_provisioning_uri_is_google_authenticator_compatible_and_encoded(): void
    {
        $user = User::factory()->withoutMfa()->make(['email' => 'person+security@example.test']);
        $uri = app(TotpService::class)->provisioningUri($user, 'JBSWY3DPEHPK3PXP');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('INTSEC%3Aperson%2Bsecurity%40example.test', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=INTSEC', $uri);
    }

    public function test_invalid_first_totp_does_not_enable_mfa_and_valid_code_generates_hashed_recovery_codes(): void
    {
        Mail::fake();
        $user = User::factory()->withoutMfa()->create();
        $this->actingAsWithoutMfa($user)->get('/mfa/enroll/authenticator')->assertOk();
        $secret = (string) $user->fresh()->pending_totp_secret;

        $this->post('/mfa/enroll/authenticator', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->mfa_enabled_at);

        $code = TOTP::createFromSecret($secret, new NativeClock)->now();
        $this->post('/mfa/enroll/authenticator', ['code' => $code])
            ->assertRedirect('/mfa/recovery-codes');

        $user->refresh();
        $this->assertNotNull($user->mfa_enabled_at);
        $this->assertFalse($user->hasMfaConfigured());
        $this->assertDatabaseCount('user_recovery_codes', 10);
        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('totp_secret'));

        foreach ($user->recoveryCodes as $record) {
            $this->assertDoesNotMatchRegularExpression(RecoveryCodeService::PATTERN, $record->getRawOriginal('code_hash'));
        }
    }

    public function test_recovery_codes_must_be_acknowledged_before_full_application_access(): void
    {
        Mail::fake();
        $user = User::factory()->withoutMfa()->create();
        $this->actingAsWithoutMfa($user)->get('/mfa/enroll/authenticator');
        $secret = (string) $user->fresh()->pending_totp_secret;
        $code = TOTP::createFromSecret($secret, new NativeClock)->now();
        $this->post('/mfa/enroll/authenticator', ['code' => $code]);

        $this->get('/dashboard')->assertRedirect('/mfa/enroll');
        $this->post('/mfa/recovery-codes/finish')->assertSessionHasErrors('acknowledged');
        $this->post('/mfa/recovery-codes/finish', ['acknowledged' => '1'])
            ->assertRedirect('/dashboard');
        $this->assertTrue($user->fresh()->hasMfaConfigured());
        $this->get('/dashboard')->assertOk();
    }

    public function test_totp_challenge_verifies_session_and_prevents_timestep_replay(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create(['totp_secret' => $secret, 'last_totp_timestep' => null]);
        $code = TOTP::createFromSecret($secret, new NativeClock)->now();

        $this->actingAsWithoutMfa($user)->get('/dashboard')->assertRedirect('/mfa/challenge');
        $this->post('/mfa/challenge/authenticator', ['code' => $code])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();

        $this->withSession([]);
        $this->post('/mfa/challenge/authenticator', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_mfa_state_is_bound_to_the_current_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAsWithoutMfa($second)
            ->withSession(['mfa.verified_user_id' => $first->id, 'mfa.verified_at' => now()->timestamp])
            ->get('/dashboard')
            ->assertRedirect('/mfa/challenge');
    }

    public function test_verified_email_otp_is_hashed_single_use_and_purpose_scoped(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $service = app(EmailOtpService::class);
        $service->send($user, MfaOtpChallenge::PURPOSE_MFA_LOGIN);

        $code = '';
        Mail::assertSent(MfaOtpCodeMail::class, function (MfaOtpCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $challenge = MfaOtpChallenge::query()->firstOrFail();
        $this->assertNotSame($code, $challenge->getRawOriginal('otp_hash'));
        $this->assertTrue(Hash::check($code, $challenge->otp_hash));
        $this->assertFalse($service->verify($user, MfaOtpChallenge::PURPOSE_EMAIL_VERIFICATION, $code));
        $this->assertTrue($service->verify($user, MfaOtpChallenge::PURPOSE_MFA_LOGIN, $code));
        $this->assertFalse($service->verify($user, MfaOtpChallenge::PURPOSE_MFA_LOGIN, $code));
    }

    public function test_unverified_email_cannot_be_used_as_login_fallback(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAsWithoutMfa($user)->get('/mfa/challenge/email')
            ->assertRedirect('/mfa/challenge');
        $this->post('/mfa/challenge/email/send')->assertSessionHasErrors('email_otp');
        Mail::assertNothingSent();
    }

    public function test_recovery_code_is_single_use_and_regeneration_invalidates_old_codes(): void
    {
        $user = User::factory()->create();
        $service = app(RecoveryCodeService::class);
        $old = $service->regenerate($user);

        $this->assertTrue($service->consume($user, $old[0]));
        $this->assertFalse($service->consume($user, $old[0]));

        $new = $service->regenerate($user);
        $this->assertFalse($service->consume($user, $old[1]));
        $this->assertTrue($service->consume($user, $new[0]));
    }

    public function test_recovery_file_is_not_a_first_factor_and_valid_upload_consumes_one_code(): void
    {
        $user = User::factory()->create();
        $codes = app(RecoveryCodeService::class)->regenerate($user);
        $file = UploadedFile::fake()->createWithContent('INTSEC-Recovery-Codes.txt', implode("\n", $codes));

        $this->post('/mfa/challenge/recovery/file', ['recovery_file' => $file])
            ->assertRedirect('/login');

        Mail::fake();
        $file = UploadedFile::fake()->createWithContent('INTSEC-Recovery-Codes.txt', implode("\n", $codes));
        $this->actingAsWithoutMfa($user)
            ->withSession(['url.intended' => '/dashboard'])
            ->post('/mfa/challenge/recovery/file', ['recovery_file' => $file])
            ->assertRedirect('/dashboard');

        $this->assertSame(1, $user->recoveryCodes()->whereNotNull('consumed_at')->count());
    }

    public function test_malformed_or_oversized_recovery_upload_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAsWithoutMfa($user)
            ->post('/mfa/challenge/recovery/file', [
                'recovery_file' => UploadedFile::fake()->createWithContent('attack.html', '<script>alert(1)</script>'),
            ])->assertSessionHasErrors('recovery_file');

        $this->actingAsWithoutMfa($user)
            ->post('/mfa/challenge/recovery/file', [
                'recovery_file' => UploadedFile::fake()->create('codes.txt', 65, 'text/plain'),
            ])->assertSessionHasErrors('recovery_file');
    }

    public function test_email_change_requires_password_and_invalidates_verification(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => 'new@example.test',
            'current_password' => 'password',
        ])->assertRedirect();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_fresh_password_login_clears_stale_mfa_state_and_remember_me_does_not_bypass_challenge(): void
    {
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true])]);
        config(['services.recaptcha.secret_key' => 'test-secret']);
        $user = User::factory()->create([
            'email' => 'remember@example.test',
            'password' => Hash::make('password'),
        ]);

        $this->withSession([
            'mfa.verified_user_id' => $user->id,
            'mfa.verified_at' => now()->timestamp,
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
            'g-recaptcha-response' => 'valid-token',
        ])->assertRedirect('/mfa/challenge')->assertSessionMissing('mfa.verified_user_id');
    }

    public function test_expired_email_otp_and_mail_failure_never_verify_mfa(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        app(EmailOtpService::class)->send($user, MfaOtpChallenge::PURPOSE_MFA_LOGIN);
        $code = '';
        Mail::assertSent(MfaOtpCodeMail::class, function (MfaOtpCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        MfaOtpChallenge::query()->update(['expires_at' => now()->subSecond()]);
        $this->assertFalse(app(EmailOtpService::class)->verify($user, MfaOtpChallenge::PURPOSE_MFA_LOGIN, $code));

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        MfaOtpChallenge::query()->update(['last_sent_at' => now()->subMinutes(2)]);
        $this->actingAsWithoutMfa($user)->post('/mfa/challenge/email/send')
            ->assertSessionHasErrors('email_otp');
        $this->get('/dashboard')->assertRedirect('/mfa/challenge');
    }

    public function test_downloaded_recovery_file_contains_codes_but_not_password_or_totp_secret(): void
    {
        $user = User::factory()->create(['email' => 'download@example.test']);
        $codes = app(RecoveryCodeService::class)->regenerate($user);
        $bundle = encrypt(json_encode([
            'codes' => $codes,
            'context' => 'management',
            'created_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));

        $response = $this->actingAs($user)
            ->withSession(['mfa.recovery_bundle' => $bundle])
            ->get('/mfa/recovery-codes/download');

        $response->assertOk();
        $contents = $response->streamedContent();
        $this->assertStringContainsString($codes[0], $contents);
        $this->assertStringNotContainsString((string) $user->totp_secret, $contents);
        $this->assertStringNotContainsString((string) $user->password, $contents);
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
    }

    public function test_replacement_keeps_old_authenticator_until_new_secret_is_confirmed(): void
    {
        Mail::fake();
        $oldSecret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'totp_secret' => $oldSecret,
            'last_totp_timestep' => null,
        ]);
        $oldCode = TOTP::createFromSecret($oldSecret, new NativeClock)->now();

        $this->actingAs($user)->post('/profile/mfa/authenticator/replace', [
            'current_password' => 'password',
            'code' => $oldCode,
        ])->assertRedirect('/profile/mfa/authenticator/replace');

        $user->refresh();
        $this->assertSame($oldSecret, $user->totp_secret);
        $this->assertNotNull($user->pending_totp_secret);

        $newCode = TOTP::createFromSecret((string) $user->pending_totp_secret, new NativeClock)->now();
        $this->post('/profile/mfa/authenticator/replace/confirm', ['code' => $newCode])
            ->assertRedirect('/mfa/recovery-codes');
        $this->assertNotSame($oldSecret, $user->fresh()->totp_secret);
    }
}
