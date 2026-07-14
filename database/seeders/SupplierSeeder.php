<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use JsonException;

class SupplierSeeder extends Seeder
{
    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $creator = User::query()->where('role', 'admin')->firstOrFail();
        $suppliers = json_decode(
            file_get_contents(database_path('seeders/data/suppliers-from-excel.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        foreach ($suppliers as $data) {
            Supplier::query()->updateOrCreate(
                ['name' => $data['name']],
                [
                    'contact_person' => $this->nullable($data['contact_person']),
                    'phone' => $this->nullable($data['phone']),
                    'email' => $this->nullable($data['email']),
                    'address' => $this->nullable($data['address']),
                    'tin' => $this->nullable($data['tin']),
                    'is_vat_registered' => (bool) $data['is_vat_registered'],
                    'notes' => $this->nullable($data['notes']),
                    'created_by' => $creator->id,
                ]
            );
        }
    }

    private function nullable(?string $value): ?string
    {
        return blank($value) || in_array($value, ['None', 'N/A'], true) ? null : $value;
    }
}
