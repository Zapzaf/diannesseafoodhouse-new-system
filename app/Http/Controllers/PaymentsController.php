<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MenuOrder;
use App\Models\MenuOrderPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    private function branchScope()
    {
        $user = auth()->user();
        $query = MenuOrderPayment::with(['order.branch', 'order.items', 'receivedBy']);

        if (!$user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } elseif (session('selected_branch_id')) {
            $query->where('branch_id', session('selected_branch_id'));
        }

        return $query;
    }

    private function authorizeBranch(int $branchId): void
    {
        $user = auth()->user();
        if (!$user->isAdmin() && (int) $user->branch_id !== $branchId) {
            abort(403);
        }
    }

    public function index(): View
    {
        return view('payments.index');
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $sort = (string) $request->input('sort', 'payment_date');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'payment_date', 'amount', 'method', 'or_number'];

        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'payment_date';
        }

        $payments = $this->branchScope()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('or_number', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhere('method', 'like', "%{$search}%")
                        ->orWhereHas('order', fn($order) => $order->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        $payments->getCollection()->transform(function ($payment) {
            return [
                'id' => $payment->id,
                'transaction_type' => 'menu_order',
                'transaction_reference' => 'Order #' . $payment->menu_order_id,
                'payment_date' => $payment->payment_date?->format('Y-m-d'),
                'customer_name' => $payment->order?->customerDisplayName() ?? '—',
                'room_or_order' => $payment->order?->branch?->name ?? '—',
                'method' => ucfirst((string) $payment->method),
                'amount' => $payment->amount,
                'reference_number' => $payment->reference_number,
                'or_number' => $payment->or_number,
                'detail_url' => route('payments.show', $payment),
                'receipt_url' => route('menu-orders.payments.receipt', $payment),
            ];
        });

        return response()->json($payments);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $menuOrderId = $request->query('menu_order_id');

        if (!$menuOrderId) {
            // No menu order specified — show a selector
            $user = auth()->user();
            $query = MenuOrder::with(['branch'])
                ->where('payment_status', '!=', 'paid')
                ->where('status', '!=', 'cancelled')
                ->where('balance', '>', 0);

            if (!$user->isAdmin()) {
                $query->where('branch_id', $user->branch_id);
            } elseif (session('selected_branch_id')) {
                $query->where('branch_id', session('selected_branch_id'));
            }

            $pendingOrders = $query->orderBy('created_at', 'desc')->get();

            return view('payments.create', compact('pendingOrders'));
        }

        $menuOrder = MenuOrder::with(['branch', 'items.menu', 'payments'])->findOrFail((int) $menuOrderId);
        $this->authorizeBranch($menuOrder->branch_id);

        if ((string) $menuOrder->status === 'cancelled') {
            return redirect()->route('menu-orders.show', $menuOrder)->with('error', 'Cannot record payment for a cancelled order.');
        }

        if ((string) $menuOrder->payment_status === 'paid' || (float) $menuOrder->balance <= 0) {
            return redirect()->route('menu-orders.show', $menuOrder)->with('error', 'This order is already fully paid.');
        }

        return view('payments.create', compact('menuOrder'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'menu_order_id' => 'required|exists:menu_orders,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,gcash,card,bank',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $menuOrder = MenuOrder::with('payments')->findOrFail((int) $data['menu_order_id']);
        $this->authorizeBranch($menuOrder->branch_id);

        $user = auth()->user();

        DB::transaction(function () use ($menuOrder, $data, $user) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();

            if ((string) $lockedOrder->status === 'cancelled') {
                throw ValidationException::withMessages(['amount' => 'Cancelled orders cannot receive payments.']);
            }

            if ((string) $lockedOrder->payment_status === 'paid' || (float) $lockedOrder->balance <= 0) {
                throw ValidationException::withMessages(['amount' => 'This order is already fully paid.']);
            }

            $amount = round((float) $data['amount'], 2);
            $balance = round((float) $lockedOrder->balance, 2);

            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed the current balance of PHP ' . number_format($balance, 2) . '.',
                ]);
            }

            $orNumber = 'OR-' . str_pad((string) ((MenuOrderPayment::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT);

            MenuOrderPayment::create([
                'branch_id' => $lockedOrder->branch_id,
                'menu_order_id' => $lockedOrder->id,
                'amount' => $amount,
                'subtotal' => round((float) $lockedOrder->subtotal, 2),
                'additional_charge_label' => $lockedOrder->additional_charge_label,
                'additional_charge_amount' => round((float) ($lockedOrder->additional_charge_amount ?? 0), 2),
                'total_vat_exempt' => round((float) $lockedOrder->total_vat_exempt, 2),
                'total_discount' => round((float) $lockedOrder->discount_amount, 2),
                'final_total' => round((float) $lockedOrder->total_amount, 2),
                'discount_type' => $lockedOrder->discount_type,
                'discount_amount' => round((float) $lockedOrder->discount_amount, 2),
                'vat_amount' => round((float) $lockedOrder->vat_amount, 2),
                'is_vat_exempt' => round((float) $lockedOrder->total_vat_exempt, 2) > 0,
                'method' => $data['method'],
                'reference_number' => $data['reference_number'] ?? null,
                'or_number' => $orNumber,
                'payment_date' => $data['payment_date'],
                'notes' => $data['notes'] ?? null,
                'received_by' => $user->id,
            ]);

            $amountPaid = round((float) $lockedOrder->payments()->sum('amount'), 2);
            $totalAmount = round((float) $lockedOrder->total_amount, 2);
            $newBalance = round(max(0, $totalAmount - $amountPaid), 2);

            $lockedOrder->update([
                'amount_paid' => $amountPaid,
                'balance' => $newBalance,
                'payment_status' => $newBalance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid'),
                'status' => $newBalance <= 0 ? 'completed' : 'open',
            ]);
        });

        return redirect()
            ->route('menu-orders.show', $menuOrder)
            ->with('success', 'Payment recorded successfully.');
    }

    public function show(MenuOrderPayment $payment): View
    {
        $this->authorizeBranch($payment->branch_id);
        $payment->load(['order.branch', 'order.items.menu', 'receivedBy']);

        return view('payments.show', compact('payment'));
    }
}