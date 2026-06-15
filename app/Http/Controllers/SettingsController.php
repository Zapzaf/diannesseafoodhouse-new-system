<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $branches = Branch::query()
            ->with('manager')
            ->withCount(['users', 'items', 'categories', 'locations'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $selectedBranchId = $user->isAdmin()
            ? (int) ($request->input('branch_id') ?: $request->session()->get('selected_branch_id') ?: ($branches->first()->id ?? 0))
            : (int) ($user->branch_id ?? 0);

        $selectedBranch = $branches->firstWhere('id', $selectedBranchId);

        return view('settings.show', [
            'totalBranches' => Branch::count(),
            'totalUsers' => User::count(),
            'totalItems' => Item::count(),
            'lowStockItems' => Item::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'selectedBranchId' => $selectedBranchId,
        ]);
    }

    public function updateBranch(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'tin_number' => ['nullable', 'string', 'max:50'],
            'vat_enabled' => ['nullable', 'boolean'],
            'vat_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pwd_discount_enabled' => ['nullable', 'boolean'],
            'senior_discount_enabled' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::query()->findOrFail((int) $validated['branch_id']);

        if (! $user->isAdmin() && (int) $user->branch_id !== (int) $branch->id) {
            abort(403, 'You can only update settings for your assigned branch.');
        }

        $vatEnabled = (bool) ($validated['vat_enabled'] ?? false);

        $branch->update([
            'contact_number' => trim((string) ($validated['contact_number'] ?? '')) ?: null,
            'tin_number' => trim((string) ($validated['tin_number'] ?? '')) ?: null,
            'vat_enabled' => $vatEnabled,
            'vat_percentage' => $vatEnabled
                ? round((float) ($validated['vat_percentage'] ?? ($branch->vat_percentage ?? 12)), 2)
                : 0,
            'pwd_discount_enabled' => (bool) ($validated['pwd_discount_enabled'] ?? false),
            'senior_discount_enabled' => (bool) ($validated['senior_discount_enabled'] ?? false),
        ]);

        return redirect()
            ->route('settings.show', ['branch_id' => $branch->id])
            ->with('success', 'Branch settings updated successfully.');
    }
}
