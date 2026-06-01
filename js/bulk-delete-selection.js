/**
 * Bulk row selection for "Select to Delete" — shared across module list screens.
 * Why: inline copies had no way to exit selection mode without deleting or reloading.
 */
(function () {
    function itmInitBulkDeleteSelection() {
        const selectAllRows = document.getElementById('select-all-rows') || document.getElementById('select-all-departments');
        const bulkDeleteForm = document.querySelector('form[id="bulk-delete-form"], form[id="department-bulk-form"]');
        if (!bulkDeleteForm || bulkDeleteForm.getAttribute('data-itm-bulk-delete-bound') === '1') {
            return;
        }
        bulkDeleteForm.setAttribute('data-itm-bulk-delete-bound', '1');

        const toggleButton = bulkDeleteForm.querySelector('button[name="bulk_action"][value="bulk_delete"]');
        if (!toggleButton) {
            return;
        }

        const selectLabel = (toggleButton.textContent || 'Select to Delete').trim();
        const rowCheckboxes = document.querySelectorAll('input[name="ids[]"][form="' + bulkDeleteForm.id + '"]');
        const deleteCells = Array.from(rowCheckboxes).map(function (checkbox) {
            return checkbox.closest('td');
        }).filter(Boolean);
        const selectAllHeaderCell = selectAllRows ? selectAllRows.closest('th') : null;
        let selectionMode = false;

        let cancelButton = bulkDeleteForm.querySelector('[data-itm-bulk-cancel="1"]');
        if (!cancelButton) {
            cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'btn btn-sm';
            cancelButton.setAttribute('data-itm-bulk-cancel', '1');
            cancelButton.textContent = 'Cancel';
            cancelButton.style.display = 'none';
            toggleButton.insertAdjacentElement('afterend', cancelButton);
        }

        function setSelectionVisibility(visible) {
            if (selectAllHeaderCell) {
                selectAllHeaderCell.style.display = visible ? '' : 'none';
            }
            deleteCells.forEach(function (cell) {
                cell.style.display = visible ? '' : 'none';
            });
        }

        function exitSelectionMode() {
            selectionMode = false;
            setSelectionVisibility(false);
            toggleButton.textContent = selectLabel;
            cancelButton.style.display = 'none';
            if (selectAllRows) {
                selectAllRows.checked = false;
            }
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });
        }

        if (selectAllRows) {
            selectAllRows.addEventListener('change', function () {
                rowCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAllRows.checked;
                });
            });
        }

        cancelButton.addEventListener('click', exitSelectionMode);

        setSelectionVisibility(false);

        bulkDeleteForm.addEventListener('submit', function (event) {
            if (event.submitter !== toggleButton) {
                return;
            }

            if (!selectionMode) {
                event.preventDefault();
                selectionMode = true;
                setSelectionVisibility(true);
                toggleButton.textContent = 'Delete Selected';
                cancelButton.style.display = '';
                return;
            }

            const anySelected = Array.from(rowCheckboxes).some(function (checkbox) {
                return checkbox.checked;
            });
            if (!anySelected) {
                event.preventDefault();
                alert('Please select at least one record to delete.');
                return;
            }

            if (!confirm('Delete selected records?')) {
                event.preventDefault();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', itmInitBulkDeleteSelection);
    } else {
        itmInitBulkDeleteSelection();
    }
})();
