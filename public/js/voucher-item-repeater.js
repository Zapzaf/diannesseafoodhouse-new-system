window.VoucherItemRepeater = {
    computeVat: function (amountWVat) {
        const net = amountWVat / 1.12;
        return { net: net, vat: amountWVat - net };
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
