<?php

namespace App\Http\Controllers;

use App\Models\CheckRegister;
use Illuminate\Http\Request;

class CheckRegisterController extends Controller
{
    public function index(Request $request)
    {
        $checks = CheckRegister::with(['checkVoucher', 'branch'])
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), fn ($q, $s) => $q->where(function ($query) use ($s): void {
                $query->where('check_no', 'like', "%{$s}%")->orWhere('payee', 'like', "%{$s}%");
            }))
            ->latest('check_date')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('check-register.index', compact('checks'));
    }

    public function markCleared(Request $request, CheckRegister $checkRegister)
    {
        $this->authorizeBranchRecord($request, $checkRegister->branch_id);

        if ($checkRegister->status !== 'issued') {
            return back()->with('error', 'Only issued checks can be marked as cleared.');
        }

        $checkRegister->update(['status' => 'cleared']);

        return back()->with('success', 'Check marked as cleared.');
    }

    public function void(Request $request, CheckRegister $checkRegister)
    {
        $this->authorizeBranchRecord($request, $checkRegister->branch_id);

        if ($checkRegister->status !== 'issued') {
            return back()->with('error', 'Only issued checks can be voided.');
        }

        $checkRegister->update(['status' => 'voided']);

        return back()->with('success', 'Check voided.');
    }
}
