<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
            'type' => ['required', Rule::in(['company', 'sole_proprietorship'])],
            'company_name' => ['required_if:type,company', 'nullable', 'string', 'max:255'],
            'business_name' => ['required_if:type,sole_proprietorship', 'nullable', 'string', 'max:255'],
            'owner_name' => ['required_if:type,sole_proprietorship', 'nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'tin' => ['nullable', 'string', 'max:50'],
            'is_vat_registered' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
