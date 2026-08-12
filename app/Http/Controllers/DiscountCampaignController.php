<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DiscountCampaign;
use App\Models\DiscountCampaignCode;
use App\Models\DiscountRedemption;
use App\Services\DiscountCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            ->with(['branch', 'creator', 'codes'])
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
        $codes = $data['codes'];
        unset($data['codes']);

        DB::transaction(function () use ($data, $codes, $request): void {
            $campaign = DiscountCampaign::create($data + ['created_by' => $request->user()->id]);
            $this->syncCodes($campaign, $codes);
        });

        return redirect()->route('discount-campaigns.index')->with('success', 'Discount campaign created successfully.');
    }

    public function edit(DiscountCampaign $discountCampaign): View
    {
        return view('discount-campaigns.edit', [
            'campaign' => $discountCampaign->load('codes'),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, DiscountCampaign $discountCampaign): RedirectResponse
    {
        $data = $this->validateCampaign($request, $discountCampaign->id);
        $codes = $data['codes'];
        unset($data['codes']);

        DB::transaction(function () use ($data, $codes, $discountCampaign): void {
            $discountCampaign->update($data);
            $this->syncCodes($discountCampaign, $codes);
        });

        return redirect()->route('discount-campaigns.index')->with('success', 'Discount campaign updated successfully.');
    }

    /**
     * Updates a campaign's coupon codes to match the given rows: existing
     * codes (matched by id) are updated in place so their usage_count isn't
     * lost, rows with no id become new codes, and any existing code missing
     * from the submission is removed.
     *
     * @param  array<int, array{id: ?int, code: string, usage_limit: ?int}>  $rows
     */
    private function syncCodes(DiscountCampaign $campaign, array $rows): void
    {
        $keepIds = [];

        foreach ($rows as $row) {
            if ($row['id'] !== null) {
                $campaign->codes()->whereKey($row['id'])->update([
                    'code' => $row['code'],
                    'usage_limit' => $row['usage_limit'],
                ]);
                $keepIds[] = $row['id'];
            } else {
                $keepIds[] = $campaign->codes()->create([
                    'code' => $row['code'],
                    'usage_limit' => $row['usage_limit'],
                ])->id;
            }
        }

        $campaign->codes()->whereNotIn('id', $keepIds)->delete();
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
            ->with(['order:id,order_number,branch_id,customer_name,total_amount', 'appliedBy'])
            ->latest()
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('discount-campaigns.redemptions', [
            'campaign' => $discountCampaign,
            'redemptions' => $redemptions,
        ]);
    }

    /**
     * Cross-campaign redemption history: every coupon/automatic/manual
     * promo discount ever applied, with which code, campaign, guest, and
     * order it belongs to — the audit trail an admin reaches for without
     * having to know (or click into) the specific campaign first.
     */
    public function redemptionHistory(Request $request): View
    {
        $branchId = $this->activeBranchId($request);
        $campaignId = $request->input('campaign_id');
        $source = $request->input('source');
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $redemptions = DiscountRedemption::query()
            ->with([
                'order:id,order_number,branch_id,customer_name,total_amount',
                'campaign:id,name,branch_id',
                'campaignCode:id,code',
                'appliedBy:id,name',
                'branch:id,name',
            ])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($campaignId, fn ($q, $id) => $q->where('discount_campaign_id', $id))
            ->when($source, fn ($q, $s) => $q->where('source', $s))
            ->when($dateFrom, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($dateTo, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search): void {
                $inner->where('code_used', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('order_number', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($this->perPage($request, 25))
            ->withQueryString();

        return view('discount-campaigns.redemption-history', [
            'redemptions' => $redemptions,
            'campaigns' => DiscountCampaign::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'campaign_id' => $campaignId,
                'source' => $source,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
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
            // Echo back the code as typed — a campaign may have several.
            'code' => $data['code'],
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
            'codes' => 'nullable|array',
            'codes.*.id' => 'nullable|integer',
            'codes.*.code' => 'nullable|string|max:50',
            'codes.*.usage_limit' => 'nullable|integer|min:1',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0.01',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'unified_usage_limit' => 'nullable|boolean',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ], [
            'ends_at.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        if ($data['type'] === 'percentage' && (float) $data['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => 'A percentage discount cannot exceed 100%.',
            ]);
        }

        // Unified: one Usage Limit shared by the whole campaign (every code
        // draws from the same pool) — the simple default. Off: each code
        // tracks and caps its own usage independently, and the campaign-level
        // Usage Limit goes unused. The two are mutually exclusive, so
        // whichever mode isn't active has its number cleared rather than
        // silently kept around and ignored.
        $unified = (bool) ($data['unified_usage_limit'] ?? true);
        $data['unified_usage_limit'] = $unified;
        $data['usage_limit'] = $unified ? ($data['usage_limit'] ?? null) : null;

        // Leave every code blank for an automatic discount; multiple rows
        // become several codes that all redeem this same campaign. Blank
        // codes are dropped, and an id only survives if it actually belongs
        // to this campaign — a stale/tampered id is treated as a brand-new
        // code instead.
        $ownedCodeIds = $ignoreId
            ? DiscountCampaignCode::where('discount_campaign_id', $ignoreId)->pluck('id')->all()
            : [];

        $codes = collect($data['codes'] ?? [])
            ->map(function (array $row) use ($ownedCodeIds, $unified) {
                $id = (int) ($row['id'] ?? 0);

                return [
                    'id' => in_array($id, $ownedCodeIds, true) ? $id : null,
                    'code' => trim((string) ($row['code'] ?? '')),
                    // Each code's own limit only matters when the campaign
                    // isn't unified; default 1 use per code otherwise.
                    'usage_limit' => $unified ? null : (($row['usage_limit'] ?? '') !== '' ? (int) $row['usage_limit'] : 1),
                ];
            })
            ->filter(fn (array $row) => $row['code'] !== '')
            ->unique('code')
            ->values();

        if ($codes->isNotEmpty()) {
            $conflict = DiscountCampaignCode::whereIn('code', $codes->pluck('code'))
                ->when($ignoreId, fn ($q, $id) => $q->where('discount_campaign_id', '!=', $id))
                ->value('code');

            if ($conflict) {
                throw ValidationException::withMessages([
                    'codes' => 'The code "'.$conflict.'" is already in use by another campaign.',
                ]);
            }
        }

        $data['codes'] = $codes->all();
        $data['min_purchase_amount'] = $data['min_purchase_amount'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
