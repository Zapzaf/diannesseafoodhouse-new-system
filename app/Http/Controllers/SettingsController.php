<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
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
            'sidebarBgLight' => AppSetting::get('sidebar_bg_light', '#ffffff'),
            'sidebarBgDark' => AppSetting::get('sidebar_bg_dark', '#0f131c'),
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
            'disable_ingredients' => ['nullable', 'boolean'],
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
            'disable_ingredients' => (bool) ($validated['disable_ingredients'] ?? false),
        ]);

        return redirect()
            ->route('settings.show', ['branch_id' => $branch->id])
            ->with('success', 'Branch settings updated successfully.');
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($request->boolean('reset')) {
            AppSetting::set('sidebar_bg_light', null);
            AppSetting::set('sidebar_bg_dark', null);

            return redirect()->route('settings.show')->with('success', 'Sidebar colors reset to defaults.');
        }

        $validated = $request->validate([
            'sidebar_bg_light' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_bg_dark' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'sidebar_bg_light.regex' => 'The light mode color must be a valid hex color (e.g. #1e2530).',
            'sidebar_bg_dark.regex' => 'The dark mode color must be a valid hex color (e.g. #0f131c).',
        ]);

        AppSetting::set('sidebar_bg_light', strtolower($validated['sidebar_bg_light']));
        AppSetting::set('sidebar_bg_dark', strtolower($validated['sidebar_bg_dark']));

        return redirect()->route('settings.show')->with('success', 'Appearance settings updated successfully.');
    }
}
