@php
    $nvMinDate   = $monthYear . '-01';
    $nvMaxDate   = \Carbon\Carbon::createFromFormat('Y-m', $monthYear)->endOfMonth()->format('Y-m-d');
    $nvMonthLabel = \Carbon\Carbon::createFromFormat('Y-m', $monthYear)->format('F Y');
@endphp

{{-- Create Non-Vatable Modal --}}
<div class="modal fade" id="createNonVatableModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('expenses.nonvatable.store', $monthYear) }}" method="POST" id="createNonVatableForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i data-feather="plus-circle" class="me-1"></i> Add Non-Vatable Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i data-feather="info" style="width:14px;height:14px;" class="me-1"></i>
                        Expenses must fall within <strong>{{ $nvMonthLabel }}</strong>.
                        To add expenses for a different month, use the <strong>Go to Month</strong> selector on the Expenses page.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" name="date" id="cnv-date" class="form-control"
                                min="{{ $nvMinDate }}" max="{{ $nvMaxDate }}">
                            <div id="cnv-date-err" class="text-danger small mt-1" style="display:none;">
                                Date must be within {{ $nvMonthLabel }}.
                            </div>
                        </div>
                        <div class="col-md-7"><label class="form-label fw-semibold">Vendor Name <span class="text-danger">*</span></label><input type="text" name="vendor_name" class="form-control" required></div>
                        <div class="col-12"><label class="form-label fw-semibold">Gross Amount <span class="text-danger">*</span></label><input type="number" step="0.01" name="gross_amount" class="form-control" value="0" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="cnvSubmit" class="btn btn-primary"><i data-feather="save" class="me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Non-Vatable Modal --}}
<div class="modal fade" id="editNonVatableModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editNonVatableForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i data-feather="edit-2" class="me-1"></i> Edit Non-Vatable Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i data-feather="info" style="width:14px;height:14px;" class="me-1"></i>
                        Date must remain within <strong>{{ $nvMonthLabel }}</strong>.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" name="date" id="env-date" class="form-control"
                                min="{{ $nvMinDate }}" max="{{ $nvMaxDate }}">
                            <div id="env-date-err" class="text-danger small mt-1" style="display:none;">
                                Date must be within {{ $nvMonthLabel }}.
                            </div>
                        </div>
                        <div class="col-md-7"><label class="form-label fw-semibold">Vendor Name <span class="text-danger">*</span></label><input type="text" name="vendor_name" id="env-vendor" class="form-control" required></div>
                        <div class="col-12"><label class="form-label fw-semibold">Gross Amount <span class="text-danger">*</span></label><input type="number" step="0.01" name="gross_amount" id="env-gross" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="envSubmit" class="btn btn-primary"><i data-feather="save" class="me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const MIN = '{{ $nvMinDate }}';
    const MAX = '{{ $nvMaxDate }}';

    function validateDate(inputId, errId, submitId) {
        const input  = document.getElementById(inputId);
        const err    = document.getElementById(errId);
        const submit = document.getElementById(submitId);
        if (!input) return;
        input.addEventListener('change', function () {
            const outOfRange = this.value && (this.value < MIN || this.value > MAX);
            err.style.display = outOfRange ? 'block' : 'none';
            input.classList.toggle('is-invalid', outOfRange);
            submit.disabled = outOfRange;
        });
    }

    validateDate('cnv-date', 'cnv-date-err', 'cnvSubmit');
    validateDate('env-date', 'env-date-err', 'envSubmit');

    document.getElementById('editNonVatableModal').addEventListener('show.bs.modal', function (e) {
        const b = e.relatedTarget;
        document.getElementById('editNonVatableForm').action = `/expenses/${b.dataset.my}/nonvatable/${b.dataset.id}`;
        document.getElementById('env-date').value   = b.dataset.date;
        document.getElementById('env-vendor').value = b.dataset.vendor;
        document.getElementById('env-gross').value  = b.dataset.gross;
        document.getElementById('env-date-err').style.display = 'none';
        document.getElementById('env-date').classList.remove('is-invalid');
        document.getElementById('envSubmit').disabled = false;
    });
})();
</script>
