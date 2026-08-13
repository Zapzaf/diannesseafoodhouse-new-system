<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Support\NameNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $accounts = ChartOfAccount::query()
            ->when($search !== '', function ($q) use ($search) {
                // Plain substring match (so partial words like "Elect" still
                // find "Electricity") OR'd with a normalized match, so a
                // search for "Meal Expenses" also finds "Meal Expense".
                $normalized = NameNormalizer::normalize($search);
                $q->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('name_normalized', 'like', "%{$normalized}%"));
            })
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
        $validated = $this->validateAccount($request);

        ChartOfAccount::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('chart-of-accounts.index')->with('success', 'Account created successfully.');
    }

    public function edit(ChartOfAccount $chartOfAccount)
    {
        return view('chart-of-accounts.edit', ['account' => $chartOfAccount]);
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        $validated = $this->validateAccount($request, $chartOfAccount->id);

        $chartOfAccount->update($validated);

        return redirect()->route('chart-of-accounts.index')->with('success', 'Account updated successfully.');
    }

    public function toggleActive(ChartOfAccount $chartOfAccount)
    {
        $chartOfAccount->update(['is_active' => ! $chartOfAccount->is_active]);

        return redirect()->route('chart-of-accounts.index')
            ->with('success', $chartOfAccount->is_active ? 'Account activated.' : 'Account deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccount(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('chart_of_accounts', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['debit_expense', 'debit_asset', 'credit_liability'])],
        ]);

        // Catches near-identical names ("Meal Expense" vs "Meals Expense" /
        // "MEAL EXPENSE" / extra spacing) that a plain column-unique rule on
        // the raw name would miss.
        if (ChartOfAccount::existsWithName($validated['name'], $ignoreId)) {
            throw ValidationException::withMessages([
                'name' => 'An account with this name already exists.',
            ]);
        }

        return $validated;
    }
}
