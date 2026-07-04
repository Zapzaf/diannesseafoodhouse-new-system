# Purchase & Disbursement Book — Laravel Implementation Spec

Based on: `PURCHASE-DISBURSEMENT-BOOK.xlsx` (Dianne's Seafood House Inc. accountant's manual book)

## 1. Business Workflow (translated from the accountant's notes)

The accountant's book has **4 linked ledgers**. The core rule is: *every purchase or payment must land in exactly one of these, and some flow into others.*

| Sheet | Purpose | Trigger |
|---|---|---|
| **APV** (Accounts Payable Voucher) — "Credit Purchases" | Item-by-item log of everything bought **on credit / not yet paid**, including purchases made directly by staff to a supplier | A purchase happens but is not paid immediately |
| **PCV** (Petty Cash Voucher) | All payments/purchases taken **from petty cash** | Staff pays cash-on-hand for something |
| **CV** (Check Voucher) — "Disbursements" | (a) Payment of an existing APV (settling accounts payable), (b) COD purchases that require immediate cash/check payment, (c) **Replenishment of petty cash fund** (reimburses the PCV total) | Money actually leaves the bank/check book |
| **Check Register** | Simple log of every check actually issued | A CV results in a physical/online check |

**Key linking rules found in the sheet notes:**
1. Every APV item belongs to inventory/purchases (`"All APV - Inventory-related - goes to Purchases"`).
2. When petty cash needs to be topped up, you **do not** re-enter the individual PCV line items — you create **one CV** of type "PCF Replenishment" for the PCV sub-total. The expense/cost accounts stay on the PCV; the CV just moves cash.
3. A CV can optionally reference an **APV #** — this is what closes out an unpaid credit purchase.
   - If a bill will be paid later, create the APV now (it captures VAT/Non-VAT detail) and pay it via CV when due.
   - If a bill is paid immediately (COD), skip the APV and put VAT/Non-VAT breakdown directly on the CV.
4. Every CV that resulted in a check must have a matching **Check Register** entry (Check #). CV ≠ paid until it has a check register row.
5. **EWT (Expanded Withholding Tax)** is tracked per CV (`RATE`, `AMOUNT`) and reduces `AMOUNT PAID` vs `AMOUNT W/VAT`.

### Flow diagram
```
                 ┌────────────────────┐
 Credit Purchase │   APV (unpaid)      │──┐
 (item-level)    └────────────────────┘  │  APV # referenced
                                          ▼
 Petty cash spend┌────────────────────┐  ┌────────────────────┐   ┌──────────────────┐
 (item-level)    │   PCV               │─▶│   CV (Disbursement) │──▶│  Check Register   │
                 └────────────────────┘  └────────────────────┘   └──────────────────┘
                  (replenishment only,     (actual cash/check out)   (actual check #)
                   no item detail carried)
```

## 2. Shared Field Set (all 4 ledgers reuse this vocabulary)

| Field | Notes |
|---|---|
| `date` | Stored as Excel serial in source (e.g. `46113`) → convert to real date on import |
| `voucher_no` | APV #, PCV #, or CV # — each series is independently auto-incrementing |
| `particulars` | Free-text description / item name |
| `cost_expense_account` | Debit account — should be an FK to a `chart_of_accounts` table, not free text (source file has a stray `?` value proving free text breaks) |
| `credit_account` | Only on APV — usually `Accounts Payable - <Vendor/Person>` |
| `vendor_name`, `address`, `tin` | Vendor master data — should be pulled from a `vendors` table, not retyped every row |
| `si_no` | Supplier's Sales Invoice number |
| `amount_w_vat` | Gross amount |
| `vat` | 12% VAT component |
| `net_purchases` | `amount_w_vat / 1.12` |
| `vat_exempt` | Purchases with no VAT applicable |
| `non_vat_purchase` | Purchases from non-VAT registered suppliers |
| `total_purchases` | `net_purchases + vat_exempt + non_vat_purchase` (or `vat + net_purchases + vat_exempt + non_vat` depending on row — see §4) |
| `remarks` | Free text |

## 3. Proposed Database Schema

```php
// chart_of_accounts
Schema::create('chart_of_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique()->nullable();
    $table->string('name'); // "Materials & Supplies", "Gasoline & Supplies", "Electricity"...
    $table->enum('type', ['debit_expense', 'credit_liability']);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// vendors (extend existing supplier table if you already have one from the Item model work)
Schema::create('vendors', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('address')->nullable();
    $table->string('tin')->nullable();
    $table->boolean('is_vat_registered')->default(true);
    $table->timestamps();
});

// purchase_vouchers  (= APV, "Credit Purchases")
Schema::create('purchase_vouchers', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->string('apv_no'); // not unique alone — one APV # covers many item rows
    $table->foreignId('vendor_id')->nullable()->constrained();
    $table->string('si_no')->nullable();
    $table->foreignId('credit_account_id')->constrained('chart_of_accounts'); // e.g. Accounts Payable - Dinah
    $table->enum('status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
    $table->timestamps();
});

Schema::create('purchase_voucher_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_voucher_id')->constrained()->cascadeOnDelete();
    $table->decimal('quantity', 12, 2)->nullable();
    $table->string('unit')->nullable();
    $table->string('particulars');
    $table->foreignId('cost_account_id')->constrained('chart_of_accounts');
    $table->decimal('amount_w_vat', 14, 2)->default(0);
    $table->decimal('vat', 14, 2)->default(0);
    $table->decimal('net_purchases', 14, 2)->default(0);
    $table->decimal('vat_exempt', 14, 2)->default(0);
    $table->decimal('non_vat_purchase', 14, 2)->default(0);
    $table->decimal('total_purchases', 14, 2)->storedAs(
        'net_purchases + vat_exempt + non_vat_purchase'
    ); // generated column, always correct
    $table->string('remarks')->nullable();
    $table->timestamps();
});

// petty_cash_vouchers  (= PCV)
Schema::create('petty_cash_vouchers', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->string('cv_no')->nullable(); // filled once replenished
    $table->string('pcv_no');
    $table->foreignId('check_voucher_id')->nullable()->constrained('check_vouchers')
        ->comment('set once this PCV batch is replenished');
    $table->timestamps();
});

Schema::create('petty_cash_voucher_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('petty_cash_voucher_id')->constrained()->cascadeOnDelete();
    $table->decimal('quantity', 12, 2)->nullable();
    $table->string('unit')->nullable();
    $table->string('particulars');
    $table->foreignId('cost_account_id')->constrained('chart_of_accounts');
    $table->decimal('amount_w_vat', 14, 2)->default(0);
    $table->decimal('vat', 14, 2)->default(0);
    $table->decimal('net_purchases', 14, 2)->default(0);
    $table->decimal('vat_exempt', 14, 2)->default(0);
    $table->decimal('non_vat_purchase', 14, 2)->default(0);
    $table->string('remarks')->nullable();
    $table->timestamps();
});

// check_vouchers  (= CV, "Disbursements")
Schema::create('check_vouchers', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->string('cv_no')->unique();
    $table->foreignId('purchase_voucher_id')->nullable()->constrained('purchase_vouchers')
        ->comment('set when this CV pays an existing APV');
    $table->enum('type', ['pcf_replenishment', 'apv_payment', 'cod_purchase', 'other_disbursement'])
        ->default('other_disbursement');
    $table->string('particulars');
    $table->foreignId('cost_account_id')->nullable()->constrained('chart_of_accounts')
        ->comment('null when PCF replenishment — cost accounts live on the PCV items');
    $table->string('payee_name');
    $table->string('address')->nullable();
    $table->string('si_no')->nullable();
    $table->string('tin')->nullable();
    $table->decimal('amount_w_vat', 14, 2)->default(0);
    $table->decimal('vat', 14, 2)->default(0);
    $table->decimal('net_purchases', 14, 2)->default(0);
    $table->decimal('vat_exempt', 14, 2)->default(0);
    $table->decimal('non_vat_purchase', 14, 2)->default(0);
    $table->decimal('ewt_rate', 5, 4)->default(0); // e.g. 0.02 for 2%
    $table->decimal('ewt_amount', 14, 2)->default(0);
    $table->decimal('amount_paid', 14, 2)->default(0); // amount_w_vat - ewt_amount
    $table->string('remarks')->nullable();
    $table->timestamps();
});

// check_register
Schema::create('check_register', function (Blueprint $table) {
    $table->id();
    $table->foreignId('check_voucher_id')->constrained('check_vouchers');
    $table->date('check_date');
    $table->string('check_no')->unique();
    $table->string('payee');
    $table->string('particulars');
    $table->decimal('amount', 14, 2);
    $table->enum('status', ['issued', 'cleared', 'voided'])->default('issued');
    $table->timestamps();
});
```

## 4. Computation Rules

- VAT-inclusive purchase (12% PH VAT):
  ```
  net_purchases = amount_w_vat / 1.12
  vat           = amount_w_vat - net_purchases   // = amount_w_vat * (0.12/1.12)
  ```
- `total_purchases = net_purchases + vat_exempt + non_vat_purchase`
- CV `amount_paid = amount_w_vat - ewt_amount`, where `ewt_amount = taxable_base * ewt_rate` (taxable base is usually `net_purchases`, confirm exact base with the accountant — the source file leaves this ambiguous on rows without an APV).
- Petty cash sub-total (sum of PCV items) must equal the CV amount when a "PCF Replenishment" CV is created for it — enforce this with a validation rule, not just a display total.

## 5. Eloquent Model Relationships

```php
// PurchaseVoucher (APV)
public function items() { return $this->hasMany(PurchaseVoucherItem::class); }
public function vendor() { return $this->belongsTo(Vendor::class); }
public function creditAccount() { return $this->belongsTo(ChartOfAccount::class, 'credit_account_id'); }
public function checkVoucher() { return $this->hasOne(CheckVoucher::class); }
public function getTotalAttribute() { return $this->items->sum('total_purchases'); }

// PettyCashVoucher (PCV)
public function items() { return $this->hasMany(PettyCashVoucherItem::class); }
public function checkVoucher() { return $this->belongsTo(CheckVoucher::class); }

// CheckVoucher (CV)
public function purchaseVoucher() { return $this->belongsTo(PurchaseVoucher::class); }
public function pettyCashVouchers() { return $this->hasMany(PettyCashVoucher::class); }
public function checkRegisterEntry() { return $this->hasOne(CheckRegister::class); }

// CheckRegister
public function checkVoucher() { return $this->belongsTo(CheckVoucher::class); }
```

## 6. Status / State Machine

```
APV:  unpaid ──(CV created referencing this APV, fully covers total)──▶ paid
              ──(CV covers partial amount)──▶ partially_paid

CV:   draft ──(Check Register entry added)──▶ issued ──▶ cleared / voided
```
Recommend an `Observer` on `CheckRegister::created()` that:
1. Marks the parent `CheckVoucher` as issued.
2. If `check_voucher.purchase_voucher_id` is set, recomputes and updates the `PurchaseVoucher.status`.

## 7. UI / Module Structure (matching your existing sidenav/Blade pattern)

```
Sidenav: Purchases & Disbursements
├── Credit Purchases (APV)        → resource controller, item-repeater form (like your existing Item model forms)
├── Petty Cash Vouchers (PCV)     → resource controller, item-repeater form
├── Check Vouchers (CV)           → resource controller
│      - "Replenish Petty Cash" action → pulls unlinked PCVs, sums total, pre-fills CV
│      - "Pay APV" action → search unpaid APVs, pre-fills vendor/amount
└── Check Register                → simple table, mostly auto-created from CV "Issue Check" action
```

Suggested routes:
```php
Route::resource('purchase-vouchers', PurchaseVoucherController::class);
Route::resource('petty-cash-vouchers', PettyCashVoucherController::class);
Route::resource('check-vouchers', CheckVoucherController::class);
Route::post('check-vouchers/{cv}/issue-check', [CheckVoucherController::class, 'issueCheck']);
Route::get('check-register', [CheckRegisterController::class, 'index']);
```

## 8. Reports (matching your Dianne's inventory .docx report pattern)

Given your existing recurring `.docx` reporting work for Dianne's, this module should support the same:
- **Monthly Purchase & Disbursement Summary** — sub-totals per ledger (mirrors the `SUB-TOTAL` rows in the source file), grouped by VAT / VAT-exempt / Non-VAT.
- **Unpaid APV Aging Report** — outstanding accounts payable by vendor.
- **Petty Cash Fund Status** — spent-but-not-yet-replenished total (a running "float" balance check).
- **EWT Summary** — for BIR withholding tax filing (Form 1601-EQ / 2307 support).

## 9. Migration/Import Notes

- Import the existing Excel data with a one-off Artisan command (`php artisan import:purchase-book path/to/file.xlsx`) using `maatwebsite/excel`, mapping Excel serial dates to `Carbon`.
- Excel columns are inconsistent about which totals are populated per row (some rows use `vat_exempt`/`non_vat_purchase` instead of `vat`/`net_purchases`) — validate on import and flag rows where none of the four amount fields sum correctly, for the accountant to review before go-live.
- Free-text values like `cost_expense_account = "?"` (seen in the PCV sheet, "ALLOWANCE" row) confirm the current manual process has data-entry gaps — the new module should make `cost_account_id` a required select, not free te




## 10. Open Questions for the Accountant

1. Confirm the EWT taxable base (net purchases vs. gross) and standard EWT rates used (e.g. 1%, 2%, 5%) so a rate lookup table can be seeded.
2. Confirm whether an APV can be paid by more than one CV (partial payments) — schema above already supports it via status, but the UI needs a "remaining balance" indicator.
3. Confirm whether Check Register should support "voided" checks (source book has no void example) — recommend yes, for accountant reconciliation.