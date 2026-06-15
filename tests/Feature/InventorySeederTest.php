<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use Database\Seeders\BranchSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the Excel inventory data without creating duplicates', function () {
    $this->seed([
        BranchSeeder::class,
        UserSeeder::class,
        InventorySeeder::class,
        InventorySeeder::class,
    ]);

    expect(Item::query()->count())->toBe(214)
        ->and(Item::query()->whereNull('unit_price')->count())->toBe(214)
        ->and(Item::query()->distinct()->count('sku'))->toBe(214)
        ->and(Location::query()->count())->toBe(4)
        ->and(Category::query()->count())->toBe(25)
        ->and(Item::query()->where('sku', 'ITEM0000000388')->value('name'))->toBe('Gasul');
});
