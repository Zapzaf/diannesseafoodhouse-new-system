<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MenuOrder;
use App\Models\MenuOrderItem;
use App\Models\MenuOrderPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Builds the shared summary payload used by the X, Y, and Z Reading reports.
 * All three readings compute the same set of figures over a different
 * (already-filtered) MenuOrderPayment query scope: X over "right now" for a
 * branch/terminal, Y over a single open shift, Z over a terminal's full
 * business day.
 */
class SalesReadingService
{
    /**
     * Payment methods the recording flow is allowed to produce
     * (see MenuOrderController::storePayment). Used only to flag
     * unexpected data for investigation — it never blocks a report.
     */
    private const KNOWN_PAYMENT_METHODS = ['cash', 'gcash', 'card', 'bank'];

    /**
     * @return array<string, mixed>
     */
    public function summarize(Builder $paymentsQuery, ?int $branchId = null, ?string $voidDateFrom = null, ?string $voidDateTo = null): array
    {
        $base = fn (): Builder => clone $paymentsQuery;

        $grossSales = $this->money((float) $base()->sum('subtotal') + (float) $base()->sum('additional_charge_amount'));
        $totalDiscount = $this->money((float) $base()->sum('discount_amount'));
        $vatExemptSales = $this->money((float) $base()->sum('total_vat_exempt'));
        $vatAmount = $this->money((float) $base()->sum('vat_amount'));

        // Promotional discounts (coupon/automatic/manual) are entirely
        // separate from the PWD/Senior discount above — never combined into
        // $totalDiscount/$discount_amount, so PWD/Senior figures stay exactly
        // as BIR requires them regardless of what promotions exist.
        $promoDiscountAmount = $this->money((float) $base()->sum('promo_discount_amount'));
        $promoCouponDiscountAmount = $this->money((float) $base()->whereIn('promo_discount_source', ['coupon', 'automatic'])->sum('promo_discount_amount'));
        $promoManualDiscountAmount = $this->money((float) $base()->where('promo_discount_source', 'manual')->sum('promo_discount_amount'));
        $promoDiscountCount = $base()->where('promo_discount_amount', '>', 0)->count();

        $netSalesAfterPromoDiscount = $this->money(max(0, $grossSales - $promoDiscountAmount));
        $netOfDiscount = $this->money($grossSales - $totalDiscount - $promoDiscountAmount);
        $netSales = $this->money($netOfDiscount - $vatAmount);
        $vatableSales = $this->money(max(0, $netSales - $vatExemptSales));
        $amountCollected = $this->money((float) $base()->sum('amount'));

        $transactionCount = $base()->count();

        $seniorCount = $base()->where('discount_type', 'senior')->count();
        $pwdCount = $base()->where('discount_type', 'pwd')->count();
        $seniorDiscountAmount = $this->money((float) $base()->where('discount_type', 'senior')->sum('discount_amount'));
        $pwdDiscountAmount = $this->money((float) $base()->where('discount_type', 'pwd')->sum('discount_amount'));
        $otherDiscountAmount = $this->money(max(0, $totalDiscount - $seniorDiscountAmount - $pwdDiscountAmount));
        $otherDiscountCount = $transactionCount > 0
            ? $base()->whereNotIn('discount_type', ['none', 'senior', 'pwd'])->count()
            : 0;

        $orderIds = $base()->pluck('menu_order_id')->filter()->unique()->values();
        $customersServed = $orderIds->isNotEmpty()
            ? (int) MenuOrder::query()->whereIn('id', $orderIds)->sum('total_pax')
            : 0;

        // Grouped by menu item, once per order — not per payment — so a
        // split-payment order never double-counts its items.
        $itemsSold = $orderIds->isNotEmpty()
            ? MenuOrderItem::query()
                ->whereIn('menu_order_id', $orderIds)
                ->selectRaw('menu_id, SUM(quantity) as quantity_sold, SUM(subtotal) as total_sales')
                ->groupBy('menu_id')
                ->with('menu:id,name')
                ->get()
                ->map(function ($row) {
                    $row->menu_name = $row->menu?->name ?? 'Unknown Item';
                    $row->quantity_sold = (float) $row->quantity_sold;
                    $row->total_sales = $this->money((float) $row->total_sales);

                    return $row;
                })
                ->sortByDesc('quantity_sold')
                ->values()
            : collect();
        $totalItemsSold = (float) $itemsSold->sum('quantity_sold');

        $salesByBranch = $base()
            ->selectRaw('branch_id, COUNT(*) as transactions, SUM(subtotal + additional_charge_amount) as gross, SUM(discount_amount) as discount, SUM(amount) as collected')
            ->groupBy('branch_id')
            ->get()
            ->map(function ($row) {
                $row->branch_name = Branch::find($row->branch_id)?->name ?? 'Unknown Branch';
                $row->gross = $this->money((float) $row->gross);
                $row->discount = $this->money((float) $row->discount);
                $row->collected = $this->money((float) $row->collected);

                return $row;
            });

        $salesByCashier = $base()
            ->selectRaw('received_by, COUNT(*) as transactions, SUM(amount) as collected')
            ->groupBy('received_by')
            ->with('receivedBy:id,name')
            ->get()
            ->map(function ($row) {
                $row->cashier_name = $row->receivedBy?->name ?? 'Unknown';
                $row->collected = $this->money((float) $row->collected);

                return $row;
            });

        $byMethod = $base()
            ->selectRaw('method, COUNT(*) as transactions, SUM(amount) as amount')
            ->groupBy('method')
            ->get()
            ->map(function ($row) {
                $row->amount = $this->money((float) $row->amount);

                if (!in_array($row->method, self::KNOWN_PAYMENT_METHODS, true)) {
                    Log::warning('Sales reading encountered an unrecognized payment method.', [
                        'method' => $row->method,
                        'transactions' => $row->transactions,
                    ]);
                }

                return $row;
            });

        $voidedQuery = MenuOrder::query()
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->where('status', 'voided');

        if ($voidDateFrom && $voidDateTo) {
            $voidedQuery->whereDate('voided_at', '>=', $voidDateFrom)
                ->whereDate('voided_at', '<=', $voidDateTo);
        }

        $voidedOrders = $voidedQuery->get();

        return [
            'gross_sales' => $grossSales,
            'total_discount' => $totalDiscount,
            'senior_discount_amount' => $seniorDiscountAmount,
            'pwd_discount_amount' => $pwdDiscountAmount,
            'other_discount_amount' => $otherDiscountAmount,
            'senior_count' => $seniorCount,
            'pwd_count' => $pwdCount,
            'other_discount_count' => $otherDiscountCount,
            'promo_discount_amount' => $promoDiscountAmount,
            'promo_coupon_discount_amount' => $promoCouponDiscountAmount,
            'promo_manual_discount_amount' => $promoManualDiscountAmount,
            'promo_discount_count' => $promoDiscountCount,
            'net_sales_after_promo_discount' => $netSalesAfterPromoDiscount,
            'vat_exempt_sales' => $vatExemptSales,
            'vatable_sales' => $vatableSales,
            'zero_rated_sales' => 0.0,
            'vat_amount' => $vatAmount,
            'net_sales' => $netSales,
            'amount_collected' => $amountCollected,
            'transaction_count' => $transactionCount,
            'customers_served' => $customersServed,
            'items_sold' => $itemsSold,
            'total_items_sold' => $totalItemsSold,
            'sales_by_branch' => $salesByBranch,
            'sales_by_cashier' => $salesByCashier,
            'by_method' => $byMethod,
            'voided_count' => $voidedOrders->count(),
            'voided_amount' => $this->money((float) $voidedOrders->sum('total_amount')),
        ];
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }
}
