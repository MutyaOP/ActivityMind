<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // доступ к чужим задачам закрывает TaskPolicy
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'category_id'   => ['nullable', Rule::exists('categories', 'id')
                                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))],
            'priority'      => ['required', 'in:low,medium,high'],
            'status'        => ['required', 'in:todo,in_progress,done'],
            'due_date'      => ['nullable', 'date'],
            'estimated_min' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title'         => 'название',
            'description'   => 'описание',
            'category_id'   => 'категория',
            'priority'      => 'приоритет',
            'status'        => 'статус',
            'due_date'      => 'срок',
            'estimated_min' => 'оценка времени',
        ];
    }
}
