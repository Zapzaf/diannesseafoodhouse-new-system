<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use JsonException;

class MenuSeeder extends Seeder
{
    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();
        $creator = User::query()->where('role', 'admin')->firstOrFail();

        $categories = json_decode(
            file_get_contents(database_path('seeders/data/menu-category-from-excel.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        foreach ($categories as $data) {
            MenuCategory::query()->firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'name' => $data['name'],
                ],
                ['description' => $data['description'] ?: null]
            );
        }

        $menus = json_decode(
            file_get_contents(database_path('seeders/data/menu-from-excel.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        foreach ($menus as $data) {
            $menuCategory = MenuCategory::query()->firstOrCreate([
                'branch_id' => $branch->id,
                'name' => $data['category'],
            ]);

            Menu::query()->updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'name' => $data['name'],
                ],
                [
                    'menu_category_id' => $menuCategory->id,
                    'category_id' => null,
                    'category' => $data['category'],
                    'menu_description' => $data['menu_description'] ?: null,
                    'selling_price' => (float) $data['selling_price'],
                    'image' => $data['image'] ?? null,
                    'created_by' => $creator->id,
                ]
            );
        }
    }
}
