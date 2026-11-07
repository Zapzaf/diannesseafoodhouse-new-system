<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseVoucher;
use App\Support\NameNormalizer;
use App\Support\VatCalculator;
use Illuminate\Support\Collection;

/**
 * Transfers an approved supplier delivery into the Purchase Module as an
 * unpaid Credit APV, so the payable exists without anyone re-encoding the
 * invoice. Call it inside the approval transaction.
 */
class DeliveryApvService
{
    public function createForDelivery(Delivery $delivery, int $actorId): ?PurchaseVoucher
    {
        // Branch transfers move stock between our own branches — nothing is owed.
        if (! $delivery->supplier_id || $delivery->source_branch_id) {
            return null;
        }

        if (PurchaseVoucher::query()->where('delivery_id', $delivery->id)->exists()) {
            return null;
        }

        $delivery->loadMissing(['items', 'supplier', 'creator']);

        $amountWVat = round((float) $delivery->amount_w_vat, 2);
        $vatExempt = round((float) $delivery->vat_exempt, 2);
        $nonVat = round((float) $delivery->non_vat_purchase, 2);

        // No invoice amounts encoded on the delivery — fall back to the item
        // line prices, treated as VAT-inclusive like the Amount w/ VAT field.
        if ($amountWVat + $vatExempt + $nonVat <= 0) {
            $amountWVat = round((float) $delivery->items->sum(fn (DeliveryItem $i) => (float) $i->price), 2);
        }

        if ($amountWVat + $vatExempt + $nonVat <= 0) {
            return null; // nothing payable to record
        }

        $lines = $this->weightedLines($delivery->items);
        $costAccountId = $this->resolveAccount(config('accounting.delivery_apv.cost_account'), 'debit_expense');
        $creditAccountId = $this->resolveAccount(config('accounting.delivery_apv.credit_account'), 'credit_liability');

        $voucher = PurchaseVoucher::create([
            'branch_id' => $delivery->destination_branch_id,
            'delivery_id' => $delivery->id,
            'date' => $delivery->delivery_date ?? now()->toDateString(),
            'apv_no' => PurchaseVoucher::nextApvNo(),
            'purchase_type' => 'credit',
            'vendor_id' => $delivery->supplier_id,
            'buyer' => $delivery->creator?->name ?? $delivery->supplier?->name ?? 'N/A',
            'si_no' => $delivery->si_no,
            'credit_account_id' => $creditAccountId,
            'status' => 'unpaid',
            'remarks' => 'Auto-generated from approved delivery '.$delivery->reference_number.'.',
            'created_by' => $actorId,
        ]);

        $wVatShares = $this->allocate($amountWVat, $lines->pluck('weight'));
        $exemptShares = $this->allocate($vatExempt, $lines->pluck('weight'));
        $nonVatShares = $this->allocate($nonVat, $lines->pluck('weight'));

        foreach ($lines->values() as $index => $line) {
            /** @var DeliveryItem $item */
            $item = $line['item'];
            $split = VatCalculator::split($wVatShares[$index]);

            $voucher->items()->create([
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'particulars' => $item->description ?: ($item->item?->name ?? 'Delivery item #'.$item->id),
                'cost_account_id' => $costAccountId,
                'amount_w_vat' => $wVatShares[$index],
                'vat' => $split['vat'],
                'net_purchases' => $split['net_purchases'],
                'vat_exempt' => $exemptShares[$index],
                'non_vat_purchase' => $nonVatShares[$index],
            ]);
        }

        return $voucher;
    }

    /**
     * Lines weighted by their line price. Items with no price carry no cost
     * and are left off; if no item has a price, the invoice is split evenly.
     *
     * @return Collection<int, array{item: DeliveryItem, weight: float}>
     */
    private function weightedLines(Collection $items): Collection
    {
        $priced = $items->filter(fn (DeliveryItem $i) => (float) $i->price > 0);

        if ($priced->isNotEmpty()) {
            return $priced->map(fn (DeliveryItem $i) => ['item' => $i, 'weight' => (float) $i->price])->values();
        }

        return $items->map(fn (DeliveryItem $i) => ['item' => $i, 'weight' => 1.0])->values();
    }

    /**
     * Splits an amount across weights in whole centavos; the last line takes
     * the rounding remainder so the parts always sum to the total exactly.
     *
     * @return array<int, float>
     */
    private function allocate(float $total, Collection $weights): array
    {
        $totalCents = (int) round($total * 100);
        $weightSum = (float) $weights->sum();
        $shares = [];
        $allocated = 0;
        $last = $weights->count() - 1;

        foreach ($weights->values() as $index => $weight) {
            $cents = $index === $last
                ? $totalCents - $allocated
                : (int) floor($totalCents * $weight / $weightSum);
            $allocated += $cents;
            $shares[$index] = $cents / 100;
        }

        return $shares;
    }

    private function resolveAccount(string $name, string $type): int
    {
        $account = ChartOfAccount::query()
            ->where('name_normalized', NameNormalizer::normalize($name))
            ->first();

        return ($account ?? ChartOfAccount::create([
            'name' => $name,
            'type' => $type,
            'is_active' => true,
        ]))->id;
    }
}
