<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = ChartOfAccount::query()
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('chart-of-accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('chart-of-accounts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:255', 'unique:chart_of_accounts,name'],
            'type' => ['required', Rule::in(['debit_expense', 'debit_asset', 'credit_liability'])],
        ]);

        ChartOfAccount::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('chart-of-accounts.index')->with('success', 'Account created successfully.');
    }

    public function toggleActive(ChartOfAccount $chartOfAccount)
    {
        $chartOfAccount->update(['is_active' => ! $chartOfAccount->is_active]);

        return redirect()->route('chart-of-accounts.index')
            ->with('success', $chartOfAccount->is_active ? 'Account activated.' : 'Account deactivated.');
    }
}
