<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'required', 'exists:customers,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_received' => ['nullable', 'boolean'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'next_payment_due_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date_expected' => ['nullable', 'date'],
            'project_leader_id' => ['nullable', 'exists:users,id'],
            'craftsmen' => ['nullable', 'array'],
            'craftsmen.*' => ['exists:users,id'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
