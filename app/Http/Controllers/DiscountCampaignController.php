<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DiscountCampaign;
use App\Models\DiscountRedemption;
use App\Services\DiscountCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiscountCampaignController extends Controller
{
    public function __construct(private readonly DiscountCampaignService $discountCampaignService)
    {
    }

    public function index(Request $request): View
    {
        $branchId = $this->activeBranchId($request);
        $status = $request->validate(['status' => ['nullable', 'in:active,inactive']])['status'] ?? '';

        $campaigns = DiscountCampaign::query()
            ->with(['branch', 'creator'])
            ->withCount(['redemptions' => fn ($q) => $q->where('status', 'applied')])
            ->when($branchId, fn ($q, $id) => $q->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $id)))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('discount-campaigns.index', [
            'campaigns' => $campaigns,
            'status' => $status,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function create(): View
    {
        return view('discount-campaigns.create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCampaign($request);

        DiscountCampaign::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('discount-campaigns.index')->with('success', 'Discount campaign created successfully.');
    }

    public function edit(DiscountCampaign $discountCampaign): View
    {
        return view('discount-campaigns.edit', [
            'campaign' => $discountCampaign,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, DiscountCampaign $discountCampaign): RedirectResponse
    {
        $data = $this->validateCampaign($request, $discountCampaign->id);

        $discountCampaign->update($data);

        return redirect()->route('discount-campaigns.index')->with('success', 'Discount campaign updated successfully.');
    }

    public function toggleActive(DiscountCampaign $discountCampaign): RedirectResponse
    {
        $discountCampaign->update(['is_active' => !$discountCampaign->is_active]);

        return back()->with('success', 'Discount campaign ' . ($discountCampaign->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(DiscountCampaign $discountCampaign): RedirectResponse
    {
        if ($discountCampaign->redemptions()->exists()) {
            return back()->with('error', 'This campaign has already been used on orders and cannot be deleted. Deactivate it instead.');
        }

        $discountCampaign->delete();

        return redirect()->route('discount-campaigns.index')->with('success', 'Discount campaign deleted.');
    }

    public function redemptions(Request $request, DiscountCampaign $discountCampaign): View
    {
        $redemptions = $discountCampaign->redemptions()
            ->with(['order:id,order_number,branch_id,total_amount', 'appliedBy'])
            ->latest()
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('discount-campaigns.redemptions', [
            'campaign' => $discountCampaign,
            'redemptions' => $redemptions,
        ]);
    }

    /**
     * AJAX endpoint used by the order create/edit screens to validate a
     * coupon code before the order is actually submitted.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:50',
            'branch_id' => 'required|exists:branches,id',
            'purchase_amount' => 'required|numeric|min:0',
        ]);

        $result = $this->discountCampaignService->validateCoupon(
            $data['code'],
            (int) $data['branch_id'],
            (float) $data['purchase_amount']
        );

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'amount' => $result['amount'],
            'label' => $result['campaign']?->name,
            'code' => $result['campaign']?->code,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCampaign(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('discount_campaigns', 'code')->ignore($ignoreId)],
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0.01',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ], [
            'code.unique' => 'This coupon code is already in use by another campaign.',
            'ends_at.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        if ($data['type'] === 'percentage' && (float) $data['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'value' => 'A percentage discount cannot exceed 100%.',
            ]);
        }

        $data['code'] = (($data['code'] ?? '') !== '') ? $data['code'] : null;
        $data['min_purchase_amount'] = $data['min_purchase_amount'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
