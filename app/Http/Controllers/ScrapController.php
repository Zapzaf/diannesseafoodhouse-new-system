<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\WastageItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScrapController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $branchId = $this->resolveBranchId($request);

        $scrapItems = WastageItem::query()
            ->with(['report.branch', 'report.creator', 'report.productionOrder', 'item', 'convertedItem'])
            ->whereHas('report', function ($query) use ($user, $branchId): void {
                $query
                    ->when(! $user->isAdmin(), fn ($query) => $query->where('branch_id', $user->branch_id))
                    ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId));
            })
            ->latest()
            ->paginate($this->perPage(request(), 15))->withQueryString();

        return view('scrap.index', [
            'scrapItems' => $scrapItems,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }
}


