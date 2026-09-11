<?php

namespace App\Validation;

use App\Services\Security\IpNetwork;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a plain IPv4/IPv6 address or CIDR range.
 */
final class IpOrCidrRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || IpNetwork::normalize($value) === null) {
            $fail('The :attribute must be a valid IPv4 address, IPv6 address, or CIDR range (e.g. 192.168.1.10, 2001:db8::1, 10.0.0.0/8).');
        }
    }
}
