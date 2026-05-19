@php
    $dMinDate   = $monthYear . '-01';
    $dMaxDate   = \Carbon\Carbon::createFromFormat('Y-m', $monthYear)->endOfMonth()->format('Y-m-d');
    $dMonthLabel = \Carbon\Carbon::createFromFormat('Y-m', $monthYear)->format('F Y');
@endphp

{{-- Create Disbursement Modal --}}
<div class="modal fade" id="createDisbursementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('expenses.disbursement.store', $monthYear) }}" method="POST" id="createDisbursementForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i data-feather="plus-circle" class="me-1"></i> Add Disbursement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i data-feather="info" style="width:14px;height:14px;" class="me-1"></i>
                        Expenses must fall within <strong>{{ $dMonthLabel }}</strong>.
                        To add expenses for a different month, use the <strong>Go to Month</strong> selector on the Expenses page.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" name="date" id="cd-date" class="form-control"
                                min="{{ $dMinDate }}" max="{{ $dMaxDate }}">
                            <div id="cd-date-err" class="text-danger small mt-1" style="display:none;">
                                Date must be within {{ $dMonthLabel }}.
                            </div>
                        </div>
                        <div class="col-md-7"><label class="form-label fw-semibold">Check No.</label><input type="text" name="check_number" class="form-control"></div>
                        <div class="col-md-8"><label class="form-label fw-semibold">Payee <span class="text-danger">*</span></label><input type="text" name="payee" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label><input type="number" step="0.01" name="amount" class="form-control" value="0" required></div>
                        <div class="col-12"><label class="form-label fw-semibold">Reference</label><input type="text" name="reference" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="cdSubmit" class="btn btn-primary"><i data-feather="save" class="me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Disbursement Modal --}}
<div class="modal fade" id="editDisbursementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editDisbursementForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i data-feather="edit-2" class="me-1"></i> Edit Disbursement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i data-feather="info" style="width:14px;height:14px;" class="me-1"></i>
                        Date must remain within <strong>{{ $dMonthLabel }}</strong>.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" name="date" id="ed-date" class="form-control"
                                min="{{ $dMinDate }}" max="{{ $dMaxDate }}">
                            <div id="ed-date-err" class="text-danger small mt-1" style="display:none;">
                                Date must be within {{ $dMonthLabel }}.
                            </div>
                        </div>
                        <div class="col-md-7"><label class="form-label fw-semibold">Check No.</label><input type="text" name="check_number" id="ed-check" class="form-control"></div>
                        <div class="col-md-8"><label class="form-label fw-semibold">Payee <span class="text-danger">*</span></label><input type="text" name="payee" id="ed-payee" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label><input type="number" step="0.01" name="amount" id="ed-amount" class="form-control" required></div>
                        <div class="col-12"><label class="form-label fw-semibold">Reference</label><input type="text" name="reference" id="ed-reference" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="edSubmit" class="btn btn-primary"><i data-feather="save" class="me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const MIN = '{{ $dMinDate }}';
    const MAX = '{{ $dMaxDate }}';

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

    validateDate('cd-date', 'cd-date-err', 'cdSubmit');
    validateDate('ed-date', 'ed-date-err', 'edSubmit');

    document.getElementById('editDisbursementModal').addEventListener('show.bs.modal', function (e) {
        const b = e.relatedTarget;
        document.getElementById('editDisbursementForm').action = `/expenses/${b.dataset.my}/disbursements/${b.dataset.id}`;
        document.getElementById('ed-date').value      = b.dataset.date;
        document.getElementById('ed-check').value     = b.dataset.check;
        document.getElementById('ed-payee').value     = b.dataset.payee;
        document.getElementById('ed-amount').value    = b.dataset.amount;
        document.getElementById('ed-reference').value = b.dataset.reference;
        document.getElementById('ed-date-err').style.display = 'none';
        document.getElementById('ed-date').classList.remove('is-invalid');
        document.getElementById('edSubmit').disabled = false;
    });
})();
</script>
