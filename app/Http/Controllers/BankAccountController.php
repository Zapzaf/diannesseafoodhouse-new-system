<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index(Request $request)
    {
        $bankAccounts = BankAccount::query()
            ->when($request->input('search'), fn ($q, $s) => $q->where('bank_name', 'like', "%{$s}%")
                ->orWhere('account_name', 'like', "%{$s}%")
                ->orWhere('account_number', 'like', "%{$s}%"))
            ->orderBy('bank_name')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('bank-accounts.index', compact('bankAccounts'));
    }

    public function create()
    {
        return view('bank-accounts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
        ]);

        BankAccount::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('bank-accounts.index')->with('success', 'Bank account created successfully.');
    }

    public function toggleActive(BankAccount $bankAccount)
    {
        $bankAccount->update(['is_active' => ! $bankAccount->is_active]);

        return redirect()->route('bank-accounts.index')
            ->with('success', $bankAccount->is_active ? 'Bank account activated.' : 'Bank account deactivated.');
    }
}
