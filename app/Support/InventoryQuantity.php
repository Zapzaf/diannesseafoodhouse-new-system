<?php

namespace App\Support;

use Illuminate\Support\Str;

class InventoryQuantity
{
    private const MEASURABLE_UNITS = [
        'kg', 'kilogram', 'kilograms', 'g', 'gram', 'grams', 'mg', 'milligram', 'milligrams',
        'lb', 'lbs', 'pound', 'pounds', 'oz', 'ounce', 'ounces',
        'l', 'liter', 'liters', 'litre', 'litres', 'ml', 'milliliter', 'milliliters',
        'millilitre', 'millilitres', 'gal', 'gallon', 'gallons',
        'm', 'meter', 'meters', 'metre', 'metres', 'cm', 'centimeter', 'centimeters',
        'centimetre', 'centimetres', 'mm', 'millimeter', 'millimeters', 'millimetre', 'millimetres',
        'in', 'inch', 'inches', 'ft', 'foot', 'feet', 'yd', 'yard', 'yards',
    ];

    public static function allowsDecimals(?string $unit): bool
    {
        return in_array(Str::lower(trim((string) $unit)), self::MEASURABLE_UNITS, true);
    }

    public static function validationRules(?string $unit): array
    {
        return self::allowsDecimals($unit)
            ? ['required', 'numeric', 'gt:0', 'decimal:0,2']
            : ['required', 'integer', 'min:1'];
    }

    public static function validationMessages(?string $unit): array
    {
        $unitLabel = trim((string) $unit) ?: 'selected';

        return [
            'quantity.integer' => "Quantity must be a whole number for {$unitLabel} units.",
            'quantity.decimal' => "Quantity may have at most 2 decimal places for {$unitLabel} units.",
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.gt' => 'Quantity must be greater than 0.',
            'quantity.max' => 'Quantity cannot exceed the remaining stock.',
        ];
    }
}
