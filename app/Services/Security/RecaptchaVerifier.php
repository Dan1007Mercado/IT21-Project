<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function verify(string $token): bool
    {
        $secret = trim((string) config('services.recaptcha.secret_key'));
        $token = trim($token);

        if ($secret === '') {
            Log::error('reCAPTCHA verification is not configured.');

            return false;
        }

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->post(self::VERIFY_URL, [
                    'secret' => $secret,
                    'response' => $token,
                ]);

            if (! $response->successful()) {
                Log::warning('reCAPTCHA verification service returned an unsuccessful response.', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                Log::warning('reCAPTCHA verification service returned an invalid response.');

                return false;
            }

            return ($payload['success'] ?? false) === true;
        } catch (Throwable $exception) {
            Log::warning('reCAPTCHA verification service could not be reached.', [
                'exception' => $exception::class,
            ]);

            // Authentication remains fail-closed when verification is unavailable.
            return false;
        }
    }
}
