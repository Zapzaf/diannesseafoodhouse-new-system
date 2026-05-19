<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $supplierByName = Supplier::pluck('id', 'name');

        $templates = [
            [
                'location' => 'Kitchen Freezer',
                'category' => 'Chicken',
                'items' => [
                    ['name' => 'Sweet and Sour Chicken', 'unit' => 'pcs', 'qty' => 20, 'threshold' => 5],
                    ['name' => 'Fried Chicken', 'unit' => 'pcs', 'qty' => 15, 'threshold' => 5],
                ],
            ],
            [
                'location' => 'Kitchen Freezer',
                'category' => 'Pork',
                'items' => [
                    ['name' => 'Pork Liempo', 'unit' => 'kg', 'qty' => 8, 'threshold' => 2],
                ],
            ],
            [
                'location' => 'Dry Storage',
                'category' => 'Canned Goods',
                'items' => [
                    ['name' => 'Evaporated Milk (370ml)', 'unit' => 'can', 'qty' => 50, 'threshold' => 10],
                    ['name' => 'Condensed Milk (300ml)', 'unit' => 'can', 'qty' => 30, 'threshold' => 10],
                ],
            ],
        ];

        foreach (Branch::all() as $branch) {
            foreach ($templates as $template) {
                $location = Location::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'name' => $template['location'],
                    ],
                    []
                );

                $category = Category::updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'name' => $template['category'],
                    ],
                    [
                        'branch_id' => $branch->id,
                    ]
                );

                foreach ($template['items'] as $itemData) {
                    $sku = strtoupper(substr($branch->name, 0, 2)) . '-' . strtoupper(substr($template['category'], 0, 3)) . '-' . strtoupper(substr(md5($itemData['name']), 0, 6));

                    Item::updateOrCreate(
                        [
                            'branch_id' => $branch->id,
                            'category_id' => $category->id,
                            'name' => $itemData['name'],
                        ],
                        [
                            'sku' => $sku,
                            'unit' => $itemData['unit'],
                            'beginning_item' => $itemData['qty'],
                            'quantity' => $itemData['qty'],
                            'low_stock_threshold' => $itemData['threshold'],
                            'supplier_id' => $supplierByName['Golden Pantry Goods'] ?? null,
                            'supplier_name' => 'Golden Pantry Goods',
                            'supplier_contact' => '09183456789',
                            'notes' => 'Seeded initial inventory',
                            'created_by' => $admin->id,
                        ]
                    );
                }
            }
        }
    }
}
