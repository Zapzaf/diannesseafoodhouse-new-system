<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashDisbursement;
use App\Models\Category;
use App\Models\DeliveryItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\MenuOrderPayment;
use App\Models\NonVatablePurchase;
use App\Models\ProductionInput;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\VatablePurchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $monthKey = now()->format('Y-m');
        $branchId = $this->resolveBranchId($request);
        $selectedBranch = $branchId ? Branch::query()->find($branchId) : null;
        $analyticsPeriod = $this->resolveAnalyticsPeriod($request);
        $analyticsRange = $this->analyticsRange($analyticsPeriod);

        $saleQuery = $this->scopeToBranch(Sale::query(), $branchId);
        $paymentQuery = $this->scopeToBranch(MenuOrderPayment::query(), $branchId);

        $dailyRevenue = (float) (clone $saleQuery)->where('created_at', '>=', $today)->sum('grand_total')
            + (float) (clone $paymentQuery)->whereDate('payment_date', $today->toDateString())->sum('final_total');

        $monthlyRevenue = (float) (clone $saleQuery)->where('created_at', '>=', $monthStart)->sum('grand_total')
            + (float) (clone $paymentQuery)->where('payment_date', '>=', $monthStart->toDateString())->sum('final_total');

        $vatableExpenses = (float) $this->scopeToBranch(VatablePurchase::query(), $branchId)
            ->where('month_year', $monthKey)
            ->sum('gross_amount');
        $nonVatableExpenses = (float) $this->scopeToBranch(NonVatablePurchase::query(), $branchId)
            ->where('month_year', $monthKey)
            ->sum('gross_amount');
        $cashDisbursements = (float) $this->scopeToBranch(CashDisbursement::query(), $branchId)
            ->where('month_year', $monthKey)
            ->sum('amount');

        $monthlyExpenses = $vatableExpenses + $nonVatableExpenses + $cashDisbursements;

        $itemQuery = $this->scopeToBranch(Item::query(), $branchId);
        $categoryQuery = $this->scopeToBranch(Category::query(), $branchId);

        $lowStockQuery = $this->scopeToBranch(Item::query(), $branchId)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->with(['category', 'branch']);

        $lowStockCount = (clone $lowStockQuery)->count();
        $lowStockItems = (clone $lowStockQuery)
            ->orderBy('quantity')
            ->limit(10)
            ->get();

        $salesByMonth = (clone $saleQuery)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['created_at', 'grand_total'])
            ->groupBy(fn (Sale $sale) => $sale->created_at->format('Y-m'))
            ->map(fn ($sales) => (float) $sales->sum('grand_total'));

        $paymentsByMonth = (clone $paymentQuery)
            ->where('payment_date', '>=', now()->subMonths(5)->startOfMonth()->toDateString())
            ->get(['payment_date', 'final_total'])
            ->groupBy(fn (MenuOrderPayment $payment) => $payment->payment_date->format('Y-m'))
            ->map(fn ($payments) => (float) $payments->sum('final_total'));

        $revenueChart = collect(range(5, 0))->map(function (int $monthsBack) use ($salesByMonth, $paymentsByMonth) {
            $date = now()->subMonths($monthsBack);
            $key = $date->format('Y-m');

            return [
                'label' => $date->format('M Y'),
                'amount' => round((float) ($salesByMonth->get($key, 0) + $paymentsByMonth->get($key, 0)), 2),
            ];
        });

        $expenseBreakdown = collect([
            ['category' => 'Vatable Purchases', 'total' => $vatableExpenses],
            ['category' => 'Non-Vatable Purchases', 'total' => $nonVatableExpenses],
            ['category' => 'Cash Disbursements', 'total' => $cashDisbursements],
        ])->filter(fn (array $row) => $row['total'] > 0)->values();

        $itemCount = (clone $itemQuery)->count();
        $outOfStockCount = (clone $itemQuery)->where('quantity', '<=', 0)->count();
        $stockHealthPercent = $itemCount > 0
            ? max(0, min(100, (int) round((($itemCount - $lowStockCount) / $itemCount) * 100)))
            : 100;
        $inventoryAnalytics = $this->inventoryTransactionAnalytics($branchId, $analyticsRange);
        $deliveryAnalytics = $this->deliveryAnalytics($branchId, $analyticsRange);
        $productionAnalytics = $this->productionAnalytics($branchId, $analyticsRange);

        return view('dashboard', [
            'dailyRevenue' => $dailyRevenue,
            'monthlyRevenue' => $monthlyRevenue,
            'monthlyExpenses' => $monthlyExpenses,
            'netIncome' => $monthlyRevenue - $monthlyExpenses,
            'categoryCount' => (clone $categoryQuery)->count(),
            'supplierCount' => Supplier::query()->count(),
            'branchCount' => Branch::query()->where('is_active', true)->count(),
            'itemCount' => $itemCount,
            'outOfStockCount' => $outOfStockCount,
            'stockHealthPercent' => $stockHealthPercent,
            'lowStockCount' => $lowStockCount,
            'lowStockItems' => $lowStockItems,
            'revenueChart' => $revenueChart->toArray(),
            'expenseBreakdown' => $expenseBreakdown,
            'analyticsPeriod' => $analyticsPeriod,
            'analyticsRangeLabel' => $analyticsRange['label'],
            'inventoryAnalytics' => $inventoryAnalytics,
            'deliveryAnalytics' => $deliveryAnalytics,
            'productionAnalytics' => $productionAnalytics,
            'selectedBranchName' => $selectedBranch?->name,
        ]);
    }

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            return $user->branch_id ? (int) $user->branch_id : null;
        }

        return $request->session()->get('selected_branch_id')
            ? (int) $request->session()->get('selected_branch_id')
            : null;
    }

    private function scopeToBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId));
    }

    private function resolveAnalyticsPeriod(Request $request): string
    {
        $period = strtolower((string) $request->query('analytics_period', 'days'));

        return in_array($period, ['days', 'week', 'month'], true) ? $period : 'days';
    }

    private function analyticsRange(string $period): array
    {
        if ($period === 'month') {
            $dates = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset)->startOfMonth());

            return [
                'period' => $period,
                'label' => 'Last 6 months',
                'start' => $dates->first()->copy(),
                'keys' => $dates->map(fn (Carbon $date) => $date->format('Y-m'))->values()->all(),
                'labels' => $dates->map(fn (Carbon $date) => $date->format('M Y'))->values()->all(),
            ];
        }

        if ($period === 'week') {
            $dates = collect(range(7, 0))->map(fn (int $offset) => now()->subWeeks($offset)->startOfWeek());

            return [
                'period' => $period,
                'label' => 'Last 8 weeks',
                'start' => $dates->first()->copy(),
                'keys' => $dates->map(fn (Carbon $date) => $date->format('o-W'))->values()->all(),
                'labels' => $dates->map(fn (Carbon $date) => 'Week of '.$date->format('M j'))->values()->all(),
            ];
        }

        $dates = collect(range(6, 0))->map(fn (int $offset) => now()->subDays($offset)->startOfDay());

        return [
            'period' => 'days',
            'label' => 'Last 7 days',
            'start' => $dates->first()->copy(),
            'keys' => $dates->map(fn (Carbon $date) => $date->format('Y-m-d'))->values()->all(),
            'labels' => $dates->map(fn (Carbon $date) => $date->format('M j'))->values()->all(),
        ];
    }

    private function inventoryTransactionAnalytics(?int $branchId, array $range): array
    {
        $rows = $this->scopeToBranch(InventoryTransaction::query(), $branchId)
            ->where('status', 'approved')
            ->where('transaction_date', '>=', $range['start'])
            ->get(['type', 'quantity', 'transaction_date']);

        $added = $this->zeroSeries($range['keys']);
        $deducted = $this->zeroSeries($range['keys']);
        $addedCount = 0;
        $deductedCount = 0;

        foreach ($rows as $row) {
            $key = $this->analyticsKey($row->transaction_date, $range['period']);
            if (! array_key_exists($key, $added)) {
                continue;
            }

            if ($row->type === 'in') {
                $added[$key] += (float) $row->quantity;
                $addedCount++;
            } else {
                $deducted[$key] += (float) $row->quantity;
                $deductedCount++;
            }
        }

        $totalAdded = array_sum($added);
        $totalDeducted = array_sum($deducted);

        return [
            'labels' => $range['labels'],
            'added' => $this->seriesValues($added),
            'deducted' => $this->seriesValues($deducted),
            'total_added' => round($totalAdded, 2),
            'total_deducted' => round($totalDeducted, 2),
            'net' => round($totalAdded - $totalDeducted, 2),
            'added_count' => $addedCount,
            'deducted_count' => $deductedCount,
        ];
    }

    private function deliveryAnalytics(?int $branchId, array $range): array
    {
        $rows = DeliveryItem::query()
            ->join('deliveries', 'delivery_items.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.status', 'received')
            ->where('deliveries.created_at', '>=', $range['start'])
            ->when($branchId, fn (Builder $query, int $branchId) => $query->where('deliveries.destination_branch_id', $branchId))
            ->get([
                'delivery_items.quantity',
                'delivery_items.unit',
                'deliveries.created_at as movement_date',
            ]);

        $byUnit = [];
        foreach ($rows as $row) {
            $unit = trim((string) ($row->unit ?: 'unit'));
            $unitKey = strtolower($unit);

            if (! isset($byUnit[$unitKey])) {
                $byUnit[$unitKey] = [
                    'label' => $unit,
                    'quantity' => 0.0,
                    'count' => 0,
                ];
            }

            $byUnit[$unitKey]['quantity'] += (float) $row->quantity;
            $byUnit[$unitKey]['count']++;
        }

        usort($byUnit, fn (array $left, array $right) => $right['quantity'] <=> $left['quantity']);

        return [
            'labels' => array_values(array_map(fn (array $row) => $row['label'], $byUnit)),
            'quantities' => array_values(array_map(fn (array $row) => round($row['quantity'], 2), $byUnit)),
            'total_quantity' => round(array_sum(array_map(fn (array $row) => $row['quantity'], $byUnit)), 2),
            'delivery_item_count' => $rows->count(),
            'unit_count' => count($byUnit),
            'top_unit' => $byUnit[0]['label'] ?? 'N/A',
        ];
    }

    private function productionAnalytics(?int $branchId, array $range): array
    {
        $inputRows = ProductionInput::query()
            ->join('production_orders', 'production_inputs.production_order_id', '=', 'production_orders.id')
            ->where('production_orders.created_at', '>=', $range['start'])
            ->when($branchId, fn (Builder $query, int $branchId) => $query->where('production_orders.branch_id', $branchId))
            ->get([
                'production_inputs.quantity_used',
                'production_orders.created_at as movement_date',
            ]);

        $outputRows = ProductionOutput::query()
            ->join('production_orders', 'production_outputs.production_order_id', '=', 'production_orders.id')
            ->where('production_outputs.created_at', '>=', $range['start'])
            ->when($branchId, fn (Builder $query, int $branchId) => $query->where('production_orders.branch_id', $branchId))
            ->get([
                'production_outputs.quantity_produced',
                'production_outputs.created_at as movement_date',
            ]);

        $inputSeries = $this->zeroSeries($range['keys']);
        $outputSeries = $this->zeroSeries($range['keys']);

        foreach ($inputRows as $row) {
            $key = $this->analyticsKey($row->movement_date, $range['period']);
            if (array_key_exists($key, $inputSeries)) {
                $inputSeries[$key] += (float) $row->quantity_used;
            }
        }

        foreach ($outputRows as $row) {
            $key = $this->analyticsKey($row->movement_date, $range['period']);
            if (array_key_exists($key, $outputSeries)) {
                $outputSeries[$key] += (float) $row->quantity_produced;
            }
        }

        $orders = ProductionOrder::query()
            ->where('created_at', '>=', $range['start'])
            ->when($branchId, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->get(['status']);

        $totalInputs = array_sum($inputSeries);
        $totalOutputs = array_sum($outputSeries);

        return [
            'labels' => $range['labels'],
            'inputs' => $this->seriesValues($inputSeries),
            'outputs' => $this->seriesValues($outputSeries),
            'total_inputs' => round($totalInputs, 2),
            'total_outputs' => round($totalOutputs, 2),
            'net' => round($totalOutputs - $totalInputs, 2),
            'orders_started' => $orders->count(),
            'orders_finished' => $orders->where('status', 'finished')->count(),
        ];
    }

    private function zeroSeries(array $keys): array
    {
        return array_fill_keys($keys, 0.0);
    }

    private function seriesValues(array $series): array
    {
        return array_values(array_map(fn (float $value) => round($value, 2), $series));
    }

    private function analyticsKey(mixed $date, string $period): string
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return match ($period) {
            'month' => $date->format('Y-m'),
            'week' => $date->format('o-W'),
            default => $date->format('Y-m-d'),
        };
    }
}
