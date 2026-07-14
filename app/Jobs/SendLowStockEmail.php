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

    /**
     * @param bool $force Skip the per-branch enable/disable check (manual sends).
     */
    public function __construct(public int $itemId, public bool $force = false)
    {
    }

    public function handle(BranchMailerService $branchMailer): void
    {
        $item = Item::with(['category.location', 'branch.manager', 'branch.mailSetting'])->find($this->itemId);

        if (! $item || (float) $item->quantity > (float) $item->low_stock_threshold) {
            return;
        }

        $branch = $item->branch;

        if (! $this->force && ! $branchMailer->notificationsEnabled($branch)) {
            return;
        }

        $recipients = $branchMailer->recipientsFor($branch);

        if ($recipients === []) {
            return;
        }

        $branchMailer->mailerFor($branch)
            ->to($recipients)
            ->send(new LowStockAlert($item));
    }
}
