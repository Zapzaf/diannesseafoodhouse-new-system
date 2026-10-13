<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionRequest extends FormRequest
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
            'branch_id' => ['required', 'exists:branches,id'],
            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*.item_id' => ['required', 'exists:items,id'],
            'inputs.*.quantity_used' => ['required', 'numeric', 'gt:0'],
            'inputs.*.unit' => ['nullable', 'string', 'max:32'],
        ];
    }
}
