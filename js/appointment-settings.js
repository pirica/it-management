(function () {
    'use strict';

    var table = document.getElementById('aps-visit-reason-sortable');
    var form = document.getElementById('aps-visit-reason-reorder-form');
    var orderInput = document.getElementById('aps-visit-reason-order-json');
    if (!table || !form || !orderInput) {
        return;
    }
    if (table.getAttribute('data-aps-sortable-attached') === '1') {
        return;
    }
    table.setAttribute('data-aps-sortable-attached', '1');

    var tbody = table.querySelector('tbody');
    if (!tbody) {
        return;
    }

    var dragRow = null;

    tbody.addEventListener('dragstart', function (event) {
        var row = event.target.closest('tr[data-reason-id]');
        if (!row) {
            return;
        }
        dragRow = row;
        row.classList.add('aps-dragging');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.getAttribute('data-reason-id') || '');
        }
    });

    tbody.addEventListener('dragend', function () {
        if (dragRow) {
            dragRow.classList.remove('aps-dragging');
            dragRow = null;
        }
        clearDragOverRows();
    });

    tbody.addEventListener('dragover', function (event) {
        if (!dragRow) {
            return;
        }
        event.preventDefault();
        var target = event.target.closest('tr[data-reason-id]');
        if (!target || target === dragRow) {
            return;
        }
        clearDragOverRows();
        target.classList.add('aps-drag-over');
        var rect = target.getBoundingClientRect();
        var after = event.clientY > rect.top + rect.height / 2;
        if (after) {
            target.parentNode.insertBefore(dragRow, target.nextSibling);
        } else {
            target.parentNode.insertBefore(dragRow, target);
        }
    });

    tbody.addEventListener('drop', function (event) {
        event.preventDefault();
        clearDragOverRows();
        submitOrder();
    });

    function collectOrder() {
        var ids = [];
        var rows = tbody.querySelectorAll('tr[data-reason-id]');
        for (var i = 0; i < rows.length; i++) {
            var id = parseInt(rows[i].getAttribute('data-reason-id'), 10);
            if (!isNaN(id) && id > 0) {
                ids.push(id);
            }
        }
        return ids;
    }

    function submitOrder() {
        var ids = collectOrder();
        if (!ids.length) {
            return;
        }
        orderInput.value = JSON.stringify(ids);
        form.submit();
    }

    function clearDragOverRows() {
        var rows = tbody.querySelectorAll('tr.aps-drag-over');
        for (var i = 0; i < rows.length; i++) {
            rows[i].classList.remove('aps-drag-over');
        }
    }
})();
