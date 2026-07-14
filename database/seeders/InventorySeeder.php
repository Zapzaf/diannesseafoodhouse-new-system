<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use JsonException;

class InventorySeeder extends Seeder
{
    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $branch = Branch::query()->where('name', 'Carcar Branch')->firstOrFail();
        $creator = User::query()->where('role', 'admin')->firstOrFail();
        $items = json_decode(
            file_get_contents(database_path('seeders/data/inventory-from-excel.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        foreach ($items as $data) {
            $location = Location::query()->firstOrCreate([
                'branch_id' => $branch->id,
                'name' => $data['location'],
            ]);

            $category = Category::query()->firstOrCreate(
                [
                    'location_id' => $location->id,
                    'name' => $data['category'],
                ],
                ['branch_id' => $branch->id]
            );

            Item::query()->updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'name' => $data['name'],
                    'category_id' => $category->id,
                    'branch_id' => $branch->id,
                    'unit' => $data['unit'] ?: null,
                    'quantity' => (float) $data['quantity'],
                    'unit_price' => null,
                    'low_stock_threshold' => $this->resolveLowStockThreshold($data),
                    'notes' => $this->makeNotes($data),
                    'created_by' => $creator->id,
                ]
            );
        }
    }

    /**
     * Default to 5 when the threshold is missing, null, or 0.
     */
    private function resolveLowStockThreshold(array $data): float
    {
        $threshold = (float) ($data['low_stock_threshold'] ?? 0);

        return $threshold > 0 ? $threshold : 5.0;
    }

    private function makeNotes(array $data): string
    {
        $details = [
            'Description' => $data['description'] ?? null,
            'Scale' => $data['scale'] ?? null,
            'Last taken out' => $data['last_taken_out'] ?? null,
            'Remarks' => $data['remarks'] ?? null,
            'Source' => 'INVENTORY DATA FROM 06-12-2026.xlsx',
        ];

        return collect($details)
            ->reject(fn ($value) => blank($value) || in_array($value, ['None', 'N/A'], true))
            ->map(fn ($value, $label) => "{$label}: {$value}")
            ->implode(PHP_EOL);
    }
}
