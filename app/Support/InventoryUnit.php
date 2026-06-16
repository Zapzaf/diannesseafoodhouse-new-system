<?php

namespace App\Support;

use Illuminate\Support\Str;

class InventoryUnit
{
    private const OPTIONS = [
        'pcs' => 'pcs',
        'kg' => 'kg',
        'g' => 'g',
        'lbs' => 'lbs',
        'oz' => 'oz',
        'l' => 'l',
        'ml' => 'ml',
        'gal' => 'gal',
        'case' => 'case',
        'box' => 'box',
        'pack' => 'pack',
        'bottle' => 'bottle',
        'can' => 'can',
        'roll' => 'roll',
        'set' => 'set',
        'pair' => 'pair',
        'dozen' => 'dozen',
        'tray' => 'tray',
        'tank' => 'tank',
        'bag' => 'bag',
        'cup' => 'cup',
        'tbsp' => 'tbsp',
        'tsp' => 'tsp',
    ];

    private const ALIASES = [
        'pc' => 'pcs',
        'piece' => 'pcs',
        'pieces' => 'pcs',
        'cases' => 'case',
        'boxes' => 'box',
        'packs' => 'pack',
        'bottles' => 'bottle',
        'cans' => 'can',
        'rolls' => 'roll',
        'sets' => 'set',
        'pairs' => 'pair',
        'dozens' => 'dozen',
        'trays' => 'tray',
        'tanks' => 'tank',
        'bags' => 'bag',
        'cups' => 'cup',
        'liter' => 'l',
        'liters' => 'l',
        'litre' => 'l',
        'litres' => 'l',
        'gallon' => 'gal',
        'gallons' => 'gal',
        'lb' => 'lbs',
        'pound' => 'lbs',
        'pounds' => 'lbs',
    ];

    public static function options(): array
    {
        return self::OPTIONS;
    }

    public static function normalize(?string $unit): string
    {
        $normalized = Str::lower(trim((string) $unit));

        return self::ALIASES[$normalized] ?? $normalized;
    }

    public static function matches(?string $left, ?string $right): bool
    {
        return self::normalize($left) === self::normalize($right);
    }
}
