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
            ->get();

        $revenueChart = Sale::query()
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->orderBy('created_at')
            ->get(['created_at', 'grand_total'])
            ->groupBy(fn (Sale $sale) => $sale->created_at->format('Y-m'))
            ->map(fn ($sales, $monthKey) => [
                'label' => $monthKey,
                'amount' => (float) $sales->sum('grand_total'),
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
