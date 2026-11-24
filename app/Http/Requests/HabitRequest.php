<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HabitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // доступ к чужим привычкам закрывает HabitPolicy
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:255'],
            'frequency'         => ['required', 'in:daily,weekly'],
            'target_per_period' => ['nullable', 'integer', 'min:1', 'max:7'],
            'color'             => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active'         => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title'             => 'название',
            'frequency'         => 'периодичность',
            'target_per_period' => 'раз в неделю',
            'color'             => 'цвет',
        ];
    }
}
