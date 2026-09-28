<?php

namespace App\Http\Requests;

use App\Enums\CalculationLineType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Het formulier levert de (door de gebruiker gecontroleerde) AI-regels
     * aan als JSON-string; hier wordt dat een array voor validatie.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('lines'))) {
            $decoded = json_decode((string) $this->input('lines'), true);
            $this->merge(['lines' => is_array($decoded) ? $decoded : []]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'lines' => ['nullable', 'array', 'max:100'],
            ...collect(self::lineRules())
                ->mapWithKeys(fn (array $rules, string $field) => ['lines.*.'.$field => $rules])
                ->all(),
        ];
    }

    /**
     * Validatieregels voor één calculatieregel — ook gebruikt door de
     * AI-assistent om voorgestelde regels te controleren.
     *
     * @return array<string, array<mixed>>
     */
    public static function lineRules(): array
    {
        return [
            'type' => ['required', Rule::enum(CalculationLineType::class)],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'surcharge_pct' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'price_item_id' => ['nullable', 'integer', 'exists:price_items,id'],
        ];
    }
}
