<?php

namespace App\Http\Requests;

use App\Enums\DocumentCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,heic,pdf,doc,docx,xls,xlsx'],
            'documentable_type' => ['required', Rule::in(['lead', 'project', 'customer', 'quote'])],
            'documentable_id' => ['required', 'integer'],
            'category' => ['nullable', Rule::enum(DocumentCategory::class)],
        ];
    }
}
