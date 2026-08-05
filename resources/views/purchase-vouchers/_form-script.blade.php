<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemsContainer = document.getElementById('itemsContainer');
    const voucherForm = itemsContainer ? itemsContainer.closest('form') : null;

    function rowAmountInputs(row) {
        return [
            row.querySelector('.voucher-item-amount-w-vat'),
            row.querySelector('.voucher-item-vat-exempt'),
            row.querySelector('.voucher-item-non-vat'),
        ].filter(Boolean);
    }

    function rowTotal(row) {
        const amount = parseFloat(row.querySelector('.voucher-item-amount-w-vat')?.value || 0) || 0;
        const exempt = parseFloat(row.querySelector('.voucher-item-vat-exempt')?.value || 0) || 0;
        const nonVat = parseFloat(row.querySelector('.voucher-item-non-vat')?.value || 0) || 0;
        return amount + exempt + nonVat;
    }

    function updatePreviewsAndTotal() {
        let grandTotal = 0;
        document.querySelectorAll('#itemsContainer .voucher-item-row').forEach(function (row) {
            const amount = parseFloat(row.querySelector('.voucher-item-amount-w-vat')?.value || 0) || 0;
            const exempt = parseFloat(row.querySelector('.voucher-item-vat-exempt')?.value || 0) || 0;
            const nonVat = parseFloat(row.querySelector('.voucher-item-non-vat')?.value || 0) || 0;
            const split = VoucherItemRepeater.computeVat(amount);
            // Total is VAT-inclusive: amount (net + VAT) + VAT-exempt + Non-VAT.
            // Matches payable_total / total_purchases used everywhere else in the app.
            const total = split.net + split.vat + exempt + nonVat;
            const preview = row.querySelector('.voucher-item-preview');
            if (preview) {
                preview.textContent = 'Net ₱' + split.net.toFixed(2) + ' · VAT ₱' + split.vat.toFixed(2) + ' · Total ₱' + total.toFixed(2);
            }
            grandTotal += total;

            // Clear a previously-flagged "needs an amount" row as soon as the
            // admin fixes it — don't wait for another full-page submit/reload.
            if (rowTotal(row) > 0) {
                rowAmountInputs(row).forEach(function (input) {
                    input.setCustomValidity('');
                    input.classList.remove('is-invalid');
                });
                row.classList.remove('table-danger');
            }
        });
        const grandTotalEl = document.getElementById('grandTotal');
        if (grandTotalEl) grandTotalEl.textContent = '₱' + grandTotal.toFixed(2);
    }

    // Mirror the server's "each item needs an amount" rule client-side, so a
    // row missing VAT/VAT-exempt/Non-VAT amounts is caught — and clearly
    // pointed out — before the form ever leaves the browser.
    function validateItemAmounts() {
        let firstInvalidInput = null;

        document.querySelectorAll('#itemsContainer .voucher-item-row').forEach(function (row) {
            const hasAmount = rowTotal(row) > 0;
            const inputs = rowAmountInputs(row);

            inputs.forEach(function (input) {
                input.setCustomValidity(hasAmount ? '' : 'Enter an amount in VAT, VAT-exempt, or Non-VAT for this line item.');
                input.classList.toggle('is-invalid', !hasAmount);
            });
            row.classList.toggle('table-danger', !hasAmount);

            if (!hasAmount && !firstInvalidInput) {
                firstInvalidInput = inputs[0];
            }
        });

        return firstInvalidInput;
    }

    voucherForm?.addEventListener('submit', function (event) {
        const firstInvalidInput = validateItemAmounts();
        if (firstInvalidInput) {
            event.preventDefault();
            firstInvalidInput.reportValidity();
            firstInvalidInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    VoucherItemRepeater.initRepeater('itemsContainer', 'itemRowTemplate', 'addItemRowBtn', updatePreviewsAndTotal);
    updatePreviewsAndTotal();
});
</script>
