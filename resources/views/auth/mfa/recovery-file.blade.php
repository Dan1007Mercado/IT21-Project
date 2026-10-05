================================================
INTSEC EMERGENCY ACCOUNT RECOVERY
================================================

Account: {{ $maskedEmail }}
Generated: {{ $generatedAt->format('F j, Y H:i T') }}

IMPORTANT SECURITY INFORMATION

Store this file securely and preferably offline.
Normal INTSEC password and reCAPTCHA authentication is still required before these credentials can be used.
Each recovery code can only be used once.

RECOVERY CODES

@foreach ($codes as $code)
{{ $code }}
@endforeach

================================================
