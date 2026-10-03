<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalSecurityEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'uuid'],
            'source' => ['required', 'string', 'max:100', Rule::in(config('intsec.event_sources', []))],
            'event_type' => ['required', 'string', Rule::in(['login_failed', 'login_success', 'logout', 'unauthorized_access', 'monitored_login_attempt', 'suspicious_request', 'sql_injection_attempt'])],
            'severity' => ['nullable', 'string', 'max:20'],
            'ip' => ['required', 'ip'],
            'route' => ['required', 'string', 'max:2048'],
            'method' => ['required', 'string', 'max:10'],
            'user_agent' => ['nullable', 'string', 'max:2048'],
            'message' => ['required', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
