<?php

namespace App\Jobs;

use App\Models\Item;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendLowStockSms implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $itemId)
    {
    }

    /**
     * Create a new job instance.
     */
    public function handle(SmsService $smsService): void
    {
        $item = Item::with(['category.location', 'branch'])->find($this->itemId);

        if (! $item || (float) $item->quantity > (float) $item->low_stock_threshold) {
            return;
        }

        $managerPhone = $item->branch?->manager?->phone;

        if (empty($managerPhone)) {
            return;
        }

        $message = sprintf(
            "[%s] LOW STOCK ALERT\nItem: %s\nLocation: %s > %s\nCurrent Stock: %s %s\nThreshold: %s %s\nPlease restock immediately.",
            $item->branch?->name ?? 'Branch',
            $item->name,
            $item->category?->location?->name ?? 'Unassigned Location',
            $item->category?->name ?? 'Unassigned Category',
            $item->quantity,
            $item->unit ?? '',
            $item->low_stock_threshold,
            $item->unit ?? ''
        );

        $smsService->send($managerPhone, $message);
    }
}
