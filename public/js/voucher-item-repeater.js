window.VoucherItemRepeater = {
    computeVat: function (amountWVat) {
        const net = amountWVat / 1.12;
        return { net: net, vat: amountWVat - net };
    },
    // Single source of truth for the VAT-inclusive line total (amount w/VAT
    // + VAT-exempt + Non-VAT), shared by every voucher form that uses this
    // repeater (APV/PCV), so their previews and grand totals can't drift
    // apart from each other — or from what the server actually saves — again.
    computeTotal: function (amountWVat, vatExempt, nonVat) {
        const split = this.computeVat(amountWVat);
        return split.net + split.vat + vatExempt + nonVat;
    },
    renderPreviewHtml: function (amountWVat, vatExempt, nonVat) {
        const split = this.computeVat(amountWVat);
        const total = split.net + split.vat + vatExempt + nonVat;
        return '<span class="voucher-item-preview-total">₱' + total.toFixed(2) + '</span>' +
            '<span class="voucher-item-preview-breakdown">Net ₱' + split.net.toFixed(2) + ' · VAT ₱' + split.vat.toFixed(2) + '</span>';
    },
    initRepeater: function (containerId, templateId, addButtonId, onChange) {
        const container = document.getElementById(containerId);
        const template = document.getElementById(templateId);
        const addButton = document.getElementById(addButtonId);
        if (!container || !template || !addButton) return;

        let index = container.querySelectorAll('.voucher-item-row').length;

        function bindRow(row) {
            row.querySelectorAll('input').forEach(function (input) {
                input.addEventListener('input', function () { if (onChange) onChange(); });
            });
        }

        addButton.addEventListener('click', function () {
            const clone = template.content.cloneNode(true);
            const row = clone.querySelector('.voucher-item-row');
            row.innerHTML = row.innerHTML.split('__INDEX__').join(index);
            container.appendChild(row);
            index++;
            bindRow(container.lastElementChild);
            if (window.refreshLucideIcons) window.refreshLucideIcons();
            if (onChange) onChange();
        });

        container.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.voucher-item-remove');
            if (!removeBtn) return;
            e.preventDefault();
            const rows = container.querySelectorAll('.voucher-item-row');
            const row = removeBtn.closest('.voucher-item-row');
            if (rows.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            }
            if (onChange) onChange();
        });

        container.querySelectorAll('.voucher-item-row').forEach(bindRow);
    }
};
