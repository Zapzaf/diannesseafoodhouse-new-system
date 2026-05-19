<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function show(): View
    {
        return view('settings.show', [
            'totalBranches' => Branch::count(),
            'totalUsers' => User::count(),
            'totalItems' => Item::count(),
            'lowStockItems' => Item::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
        ]);
    }
}
