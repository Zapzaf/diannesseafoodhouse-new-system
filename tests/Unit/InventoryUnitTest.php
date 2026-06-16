<?php

use App\Support\InventoryUnit;

it('includes units currently used by seeded inventory in dropdown options', function () {
    expect(InventoryUnit::options())
        ->toHaveKeys(['pcs', 'pack', 'kg', 'case', 'bottle', 'tank'])
        ->not->toHaveKey('piece');
});

it('matches common singular plural and abbreviated unit aliases', function () {
    expect(InventoryUnit::matches('piece', 'pcs'))->toBeTrue()
        ->and(InventoryUnit::matches('box', 'boxes'))->toBeTrue()
        ->and(InventoryUnit::matches('case', 'cases'))->toBeTrue()
        ->and(InventoryUnit::matches('liter', 'l'))->toBeTrue()
        ->and(InventoryUnit::matches('piece', 'kg'))->toBeFalse();
});

it('normalizes legacy piece units to pcs', function () {
    expect(InventoryUnit::normalize('piece'))->toBe('pcs')
        ->and(InventoryUnit::normalize('pieces'))->toBe('pcs')
        ->and(InventoryUnit::normalize('pc'))->toBe('pcs');
});
