<?php

namespace App\Jobs;

use App\Mail\LowStockAlert;
use App\Models\Item;
use App\Services\BranchMailerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendLowStockEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $itemId)
    {
    }

    public function handle(BranchMailerService $branchMailer): void
    {
        $item = Item::with(['category.location', 'branch.manager', 'branch.mailSetting'])->find($this->itemId);

        if (! $item || (float) $item->quantity > (float) $item->low_stock_threshold) {
            return;
        }

        $branch = $item->branch;
        $managerEmail = $branch?->manager?->email;

        if (empty($managerEmail) || ! $branchMailer->notificationsEnabled($branch)) {
            return;
        }

        $branchMailer->mailerFor($branch)
            ->to($managerEmail, $branch?->manager?->name)
            ->send(new LowStockAlert($item));
    }
}
