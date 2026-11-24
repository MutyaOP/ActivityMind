<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngestActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // доступ проверяет AuthenticateDevice
    }

    public function rules(): array
    {
        return [
            'items'                => ['required', 'array', 'min:1', 'max:500'],
            'items.*.app_name'     => ['required', 'string', 'max:255'],
            'items.*.window_title' => ['nullable', 'string', 'max:1000'],
            'items.*.started_at'   => ['required', 'date', 'before_or_equal:+5 minutes'],
            'items.*.duration_sec' => ['required', 'integer', 'min:1', 'max:86400'],
            'items.*.is_idle'      => ['nullable', 'boolean'],
        ];
    }
}
