<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeliveryRequest extends FormRequest
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
        $isBranchTransfer = (bool) $this->input('source_branch_id') || (bool) $this->input('source_item_id');

        return [
            'supplier_id' => [$isBranchTransfer ? 'nullable' : 'required', 'exists:suppliers,id'],
            'source_branch_id' => ['nullable', 'exists:branches,id'],
            'destination_branch_id' => ['required', 'exists:branches,id'],
            'source_item_id' => ['nullable', 'exists:items,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.item_id' => [
                $isBranchTransfer ? 'required' : 'nullable',
                'required_if:items.*.allocated_to,inventory',
                'exists:items,id',
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['required', 'string', 'max:32'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.allocated_to' => ['required', 'in:inventory,production'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $missingDestinations = collect($this->input('items', []))
                ->filter(fn (mixed $row): bool => is_array($row) && empty($row['allocated_to']))
                ->map(fn (array $row): string => trim((string) ($row['description'] ?? '')) ?: 'Unnamed item')
                ->unique()
                ->values();

            if ($missingDestinations->isNotEmpty()) {
                $validator->errors()->add(
                    'items',
                    'The following items do not have a destination selected: '
                    .$missingDestinations->implode(', ')
                    .'. Please select a destination before proceeding.'
                );
            }
        });
    }
}
