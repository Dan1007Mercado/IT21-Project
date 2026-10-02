<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalRequestActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'source' => ['required', 'string', 'max:100', Rule::in(config('intsec.event_sources', []))],
            'ip' => ['required', 'ip'],
            'method' => ['required', 'string', 'max:10'],
            'path' => ['required', 'string', 'max:2048', 'starts_with:/'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'status_code' => ['required', 'integer', 'between:100,599'],
            'user_agent' => ['nullable', 'string', 'max:2048'],
            'is_authenticated' => ['required', 'boolean'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'request_size' => ['nullable', 'integer', 'min:0'],
            'response_size' => ['nullable', 'integer', 'min:0'],
            'occurred_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
