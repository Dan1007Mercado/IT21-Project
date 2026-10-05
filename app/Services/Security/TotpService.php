<?php

namespace App\Services\Security;

use App\Models\User;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\DB;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;

final class TotpService
{
    public function generateSecret(): string
    {
        return TOTP::generate(new NativeClock, 20)->getSecret();
    }

    public function provisioningUri(User $user, string $secret): string
    {
        return $this->totp($secret)
            ->withLabel($user->email)
            ->withIssuer((string) config('mfa.issuer', 'INTSEC'))
            ->withIssuerIncludedAsParameter(true)
            ->getProvisioningUri();
    }

    public function qrDataUri(string $provisioningUri): string
    {
        $qrCode = new QrCode(
            data: $provisioningUri,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 20,
            roundBlockSizeMode: RoundBlockSizeMode::None,
        );

        return (new SvgWriter)->write($qrCode)->getDataUri();
    }

    public function formattedSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    public function verify(string $secret, string $code): ?int
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        $totp = $this->totp($secret);
        $now = now()->timestamp;
        $window = (int) config('mfa.totp_window', 1);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $timestamp = $now + ($offset * (int) config('mfa.totp_period', 30));

            if (hash_equals($totp->at($timestamp), $code)) {
                return intdiv($timestamp, (int) config('mfa.totp_period', 30));
            }
        }

        return null;
    }

    public function consume(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $locked->hasMfaConfigured()) {
                return false;
            }

            $timestep = $this->verify((string) $locked->totp_secret, $code);

            if ($timestep === null || ($locked->last_totp_timestep !== null && $timestep <= $locked->last_totp_timestep)) {
                return false;
            }

            $locked->forceFill(['last_totp_timestep' => $timestep])->save();
            $user->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    private function totp(string $secret): TOTP
    {
        return TOTP::create(
            secret: $secret,
            period: (int) config('mfa.totp_period', 30),
            digits: (int) config('mfa.totp_digits', 6),
            clock: new NativeClock,
        );
    }
}
