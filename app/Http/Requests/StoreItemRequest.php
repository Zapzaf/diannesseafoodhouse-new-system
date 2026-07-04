<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:120', 'unique:items,sku'],
            'category_id' => ['required', 'exists:categories,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'unit' => ['nullable', 'string', 'max:32'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
