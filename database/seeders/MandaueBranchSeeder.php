<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MandaueBranchSeeder extends Seeder
{
    /**
     * Mandaue Branch sells the exact same menu, and stocks the same catalog of
     * items, as Carcar Branch. This clones Carcar's locations/categories/items
     * and menu-categories/menus into Mandaue, giving every item a fresh unique
     * SKU and starting Mandaue's on-hand quantities at 0 — it's a new physical
     * location, so its actual stock is tracked independently from Carcar's.
     */
    public function run(): void
    {
        $carcarBranch = Branch::where('name', 'Carcar Branch')->firstOrFail();
        $mandaueBranch = Branch::where('name', 'Mandaue Branch')->firstOrFail();
        $creator = User::where('role', 'admin')->firstOrFail();

        $nextSkuNumber = $this->nextSkuNumber();

        DB::transaction(function () use ($carcarBranch, $mandaueBranch, $creator, &$nextSkuNumber): void {
            $this->cloneInventory($carcarBranch, $mandaueBranch, $creator, $nextSkuNumber);
            $this->cloneMenu($carcarBranch, $mandaueBranch, $creator);
        });
    }

    private function cloneInventory(Branch $carcarBranch, Branch $mandaueBranch, User $creator, int &$nextSkuNumber): void
    {
        $locations = Location::where('branch_id', $carcarBranch->id)->with('categories.items')->get();

        foreach ($locations as $sourceLocation) {
            $mandaueLocation = Location::firstOrCreate([
                'branch_id' => $mandaueBranch->id,
                'name' => $sourceLocation->name,
            ]);

            foreach ($sourceLocation->categories as $sourceCategory) {
                $mandaueCategory = Category::firstOrCreate(
                    [
                        'location_id' => $mandaueLocation->id,
                        'name' => $sourceCategory->name,
                    ],
                    ['branch_id' => $mandaueBranch->id]
                );

                foreach ($sourceCategory->items as $sourceItem) {
                    $alreadyCloned = Item::where('branch_id', $mandaueBranch->id)
                        ->where('category_id', $mandaueCategory->id)
                        ->where('name', $sourceItem->name)
                        ->exists();

                    if ($alreadyCloned) {
                        continue;
                    }

                    Item::create([
                        'name' => $sourceItem->name,
                        'sku' => $sourceItem->sku ? sprintf('ITEM%010d', $nextSkuNumber++) : null,
                        'category_id' => $mandaueCategory->id,
                        'branch_id' => $mandaueBranch->id,
                        'unit' => $sourceItem->unit,
                        // A new branch starts with no physical stock — quantities are
                        // tracked independently per location, unlike the catalog itself.
                        'quantity' => 0,
                        'unit_price' => $sourceItem->unit_price,
                        'low_stock_threshold' => $sourceItem->low_stock_threshold,
                        'notes' => $sourceItem->notes,
                        'created_by' => $creator->id,
                    ]);
                }
            }
        }
    }

    private function cloneMenu(Branch $carcarBranch, Branch $mandaueBranch, User $creator): void
    {
        $menuCategories = MenuCategory::where('branch_id', $carcarBranch->id)->with('menus')->get();

        foreach ($menuCategories as $sourceMenuCategory) {
            $mandaueMenuCategory = MenuCategory::firstOrCreate(
                [
                    'branch_id' => $mandaueBranch->id,
                    'name' => $sourceMenuCategory->name,
                ],
                ['description' => $sourceMenuCategory->description]
            );

            foreach ($sourceMenuCategory->menus as $sourceMenu) {
                Menu::updateOrCreate(
                    [
                        'branch_id' => $mandaueBranch->id,
                        'name' => $sourceMenu->name,
                    ],
                    [
                        'menu_category_id' => $mandaueMenuCategory->id,
                        'category_id' => null,
                        'category' => $sourceMenu->category,
                        'menu_description' => $sourceMenu->menu_description,
                        'selling_price' => $sourceMenu->selling_price,
                        'image' => $sourceMenu->image,
                        'created_by' => $creator->id,
                    ]
                );
            }
        }
    }

    /**
     * Items are auto-numbered "ITEM0000000001"-style; continue that sequence so
     * cloned Mandaue items get fresh, globally-unique SKUs (sku has no per-branch
     * scoping in the schema).
     */
    private function nextSkuNumber(): int
    {
        $maxSku = Item::query()
            ->whereNotNull('sku')
            ->where('sku', 'like', 'ITEM%')
            ->selectRaw("MAX(CAST(SUBSTRING(sku, 5) AS UNSIGNED)) as max_number")
            ->value('max_number');

        return ((int) $maxSku) + 1;
    }
}
