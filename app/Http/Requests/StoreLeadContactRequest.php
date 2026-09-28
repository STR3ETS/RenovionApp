<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadContactRequest extends FormRequest
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
            'type' => ['required', Rule::in(['telefoon', 'email', 'whatsapp', 'bezoek'])],
            'summary' => ['required', 'string', 'max:2000'],
            'next_action' => ['nullable', 'string', 'max:255', 'required_with:next_action_at'],
            'next_action_at' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'type contactmoment',
            'summary' => 'samenvatting',
            'next_action' => 'volgende actie',
            'next_action_at' => 'datum volgende actie',
        ];
    }
}
