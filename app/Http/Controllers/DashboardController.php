<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Supplier;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $dailyRevenue = (float) Sale::where('created_at', '>=', $today)->sum('grand_total');
        $monthlyRevenue = (float) Sale::where('created_at', '>=', $monthStart)->sum('grand_total');

        $lowStockItems = Item::query()
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->with('category')
            ->limit(10)
            ->get()
            ->map(function (Item $item): array {
                return [
                    'name' => $item->name,
                    'category' => $item->category?->name ?? '-',
                    'quantity' => $item->quantity,
                    'low_stock_threshold' => $item->low_stock_threshold,
                ];
            });

        $revenueChart = Sale::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month_key")
            ->selectRaw('SUM(grand_total) as amount')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->month_key,
                'amount' => (float) $row->amount,
            ]);

        return view('dashboard', [
            'dailyRevenue' => $dailyRevenue,
            'monthlyRevenue' => $monthlyRevenue,
            'monthlyExpenses' => 0,
            'netIncome' => $monthlyRevenue,
            'categoryCount' => Category::query()->count(),
            'supplierCount' => Supplier::query()->count(),
            'branchCount' => Branch::query()->where('is_active', true)->count(),
            'lowStockCount' => $lowStockItems->count(),
            'lowStockItems' => $lowStockItems,
            'revenueChart' => $revenueChart->toArray(),
            'expenseBreakdown' => collect(),
        ]);
    }
}
