@php
    $vMinDate = $monthYear . '-01';
    $vMaxDate = \Carbon\Carbon::createFromFormat('Y-m', $monthYear)->endOfMonth()->format('Y-m-d');
    $vMonthLabel = \Carbon\Carbon::createFromFormat('Y-m', $monthYear)->format('F Y');
@endphp

{{-- Create Vatable Modal --}}
<div class="modal fade" id="createVatableModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('expenses.vatable.store', $monthYear) }}" method="POST" id="createVatableForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i data-feather="plus-circle" class="me-1"></i> Add Vatable Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i data-feather="info" style="width:14px;height:14px;" class="me-1"></i>
                        Expenses must fall within <strong>{{ $vMonthLabel }}</strong>.
                        To add expenses for a different month, use the <strong>Go to Month</strong> selector on the Expenses page.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" name="date" id="cv-date" class="form-control"
                                min="{{ $vMinDate }}" max="{{ $vMaxDate }}">
                            <div id="cv-date-err" class="text-danger small mt-1" style="display:none;">
                                Date must be within {{ $vMonthLabel }}.
                            </div>
                        </div>
                        <div class="col-md-8"><label class="form-label fw-semibold">Vendor Name <span class="text-danger">*</span></label><input type="text" name="vendor_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Address</label><input type="text" name="address" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">SI No.</label><input type="text" name="si_number" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">TIN</label><input type="text" name="tin" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Gross Amount <span class="text-danger">*</span></label><input type="number" step="0.01" name="gross_amount" class="form-control" value="0" required></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">VAT <span class="text-danger">*</span></label><input type="number" step="0.01" name="vat" class="form-control" value="0" required></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Net Purchases <span class="text-danger">*</span></label><input type="number" step="0.01" name="net_purchases" class="form-control" value="0" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="cvSubmit" class="btn btn-primary"><i data-feather="save" class="me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Vatable Modal --}}
<div class="modal fade" id="editVatableModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editVatableForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i data-feather="edit-2" class="me-1"></i> Edit Vatable Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i data-feather="info" style="width:14px;height:14px;" class="me-1"></i>
                        Date must remain within <strong>{{ $vMonthLabel }}</strong>.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" name="date" id="ev-date" class="form-control"
                                min="{{ $vMinDate }}" max="{{ $vMaxDate }}">
                            <div id="ev-date-err" class="text-danger small mt-1" style="display:none;">
                                Date must be within {{ $vMonthLabel }}.
                            </div>
                        </div>
                        <div class="col-md-8"><label class="form-label fw-semibold">Vendor Name <span class="text-danger">*</span></label><input type="text" name="vendor_name" id="ev-vendor" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Address</label><input type="text" name="address" id="ev-address" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">SI No.</label><input type="text" name="si_number" id="ev-si" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">TIN</label><input type="text" name="tin" id="ev-tin" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Gross Amount <span class="text-danger">*</span></label><input type="number" step="0.01" name="gross_amount" id="ev-gross" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">VAT <span class="text-danger">*</span></label><input type="number" step="0.01" name="vat" id="ev-vat" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Net Purchases <span class="text-danger">*</span></label><input type="number" step="0.01" name="net_purchases" id="ev-net" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="evSubmit" class="btn btn-primary"><i data-feather="save" class="me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const MIN = '{{ $vMinDate }}';
    const MAX = '{{ $vMaxDate }}';

    function validateDate(inputId, errId, submitId) {
        const input  = document.getElementById(inputId);
        const err    = document.getElementById(errId);
        const submit = document.getElementById(submitId);
        if (!input) return;
        input.addEventListener('change', function () {
            const val = this.value;
            const outOfRange = val && (val < MIN || val > MAX);
            err.style.display    = outOfRange ? 'block' : 'none';
            input.classList.toggle('is-invalid', outOfRange);
            submit.disabled = outOfRange;
        });
    }

    validateDate('cv-date', 'cv-date-err', 'cvSubmit');
    validateDate('ev-date', 'ev-date-err', 'evSubmit');

    document.getElementById('editVatableModal').addEventListener('show.bs.modal', function (e) {
        const b = e.relatedTarget;
        document.getElementById('editVatableForm').action = `/expenses/${b.dataset.my}/vatable/${b.dataset.id}`;
        document.getElementById('ev-date').value    = b.dataset.date;
        document.getElementById('ev-vendor').value  = b.dataset.vendor;
        document.getElementById('ev-address').value = b.dataset.address;
        document.getElementById('ev-si').value      = b.dataset.si;
        document.getElementById('ev-tin').value     = b.dataset.tin;
        document.getElementById('ev-gross').value   = b.dataset.gross;
        document.getElementById('ev-vat').value     = b.dataset.vat;
        document.getElementById('ev-net').value     = b.dataset.net;
        // reset validation state
        document.getElementById('ev-date-err').style.display = 'none';
        document.getElementById('ev-date').classList.remove('is-invalid');
        document.getElementById('evSubmit').disabled = false;
    });
})();
</script>
