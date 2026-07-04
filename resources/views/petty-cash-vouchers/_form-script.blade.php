<script>
document.addEventListener('DOMContentLoaded', function () {
    function updatePreviewsAndTotal() {
        let grandTotal = 0;
        document.querySelectorAll('#itemsContainer .voucher-item-row').forEach(function (row) {
            const amount = parseFloat(row.querySelector('.voucher-item-amount-w-vat')?.value || 0) || 0;
            const exempt = parseFloat(row.querySelector('.voucher-item-vat-exempt')?.value || 0) || 0;
            const nonVat = parseFloat(row.querySelector('.voucher-item-non-vat')?.value || 0) || 0;
            const split = VoucherItemRepeater.computeVat(amount);
            const total = split.net + exempt + nonVat;
            const preview = row.querySelector('.voucher-item-preview');
            if (preview) {
                preview.textContent = 'Net ₱' + split.net.toFixed(2) + ' · VAT ₱' + split.vat.toFixed(2) + ' · Total ₱' + total.toFixed(2);
            }
            grandTotal += total;
        });
        const grandTotalEl = document.getElementById('grandTotal');
        if (grandTotalEl) grandTotalEl.textContent = '₱' + grandTotal.toFixed(2);
    }

    VoucherItemRepeater.initRepeater('itemsContainer', 'itemRowTemplate', 'addItemRowBtn', updatePreviewsAndTotal);
    updatePreviewsAndTotal();
});
</script>
