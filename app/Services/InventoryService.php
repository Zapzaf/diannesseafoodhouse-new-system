<?php

namespace App\Services;

use App\Jobs\SendLowStockSms;
use App\Models\Item;
use App\Models\User;

class InventoryService
{
    public function increase(Item $item, float $amount): Item
    {
        $item->increment('quantity', $amount);

        return $item->refresh();
    }

    public function increaseWithPrice(Item $item, float $amount, float $unitPrice): Item
    {
        $item->increment('quantity', $amount);
        $item->unit_price = $unitPrice;
        $item->save();

        return $item->refresh();
    }

    public function decrease(Item $item, float $amount): Item
    {
        if ((float) $item->quantity < $amount) {
            abort(422, 'Insufficient stock.');
        }

        $item->decrement('quantity', $amount);
        $item->refresh();

        $this->dispatchLowStockIfNeeded($item);

        return $item;
    }

    public function dispatchLowStockIfNeeded(Item $item): void
    {
        if ((float) $item->quantity > (float) $item->low_stock_threshold) {
            return;
        }

        $manager = $item->branch?->manager;

        if (! $manager instanceof User || empty($manager->phone)) {
            return;
        }

        SendLowStockSms::dispatch($item->id);
    }
}
