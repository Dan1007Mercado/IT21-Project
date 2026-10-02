<?php

namespace App\Services\Security;

final class TelemetryMetadataSanitizer
{
    private const SENSITIVE_KEY_PATTERN = '/(?:password|passwd|passphrase|secret|token|authorization|cookie|csrf|session|api[_-]?key|credential)/i';

    /** @return array<string, mixed> */
    public function sanitize(array $metadata): array
    {
        $safe = [];

        foreach ($metadata as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY_PATTERN, (string) $key)) {
                $safe[$key] = '[REDACTED]';
                continue;
            }

            $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $safe;
    }
}
