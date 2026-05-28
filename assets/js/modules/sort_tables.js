/**
 * Sorts table rows by a column, toggling ascending/descending order.
 *
 * @param {HTMLTableElement} table
 * @param {number} colIndex
 * @returns {void}
 */
export function sortTableByColumn(table, colIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const th = table.querySelectorAll('th')[colIndex];
    const headerLabel = (th?.textContent || '').trim().toLowerCase();
    const isSocieteColumn = headerLabel === 'société' || headerLabel === 'societe';
    const isFirstSortAction = !tbody.dataset.sortCol;

    // Compute next sort direction.
    let asc = !tbody.dataset.sortAsc || tbody.dataset.sortCol != colIndex || tbody.dataset.sortAsc === 'false';

    // Special case: first click on pre-sorted "Société" column should toggle to DESC.
    if (isFirstSortAction && isSocieteColumn && th?.classList.contains('sorted-asc')) {
        asc = false;
    }

    tbody.dataset.sortAsc = asc;
    tbody.dataset.sortCol = colIndex;

    // Mark active column sort state.
    th.classList.remove('sorted-asc', 'sorted-desc');
    th.classList.add(asc ? 'sorted-asc' : 'sorted-desc');

    rows.sort((a, b) => {
        const getCellValue = (td) => {
            const input = td.querySelector('input, select, textarea');
            if (input) {
                if (input.tagName.toLowerCase() === 'select') {
                    return input.options[input.selectedIndex]?.text || '';
                }
                return input.value || '';
            }
            return td.textContent.trim();
        };

        const aText = getCellValue(a.children[colIndex]).trim();
        const bText = getCellValue(b.children[colIndex]).trim();

        // Use numeric comparison when both values are valid numbers.
        const aNum = parseFloat(aText.replace(/\s/g, '').replace(',', '.'));
        const bNum = parseFloat(bText.replace(/\s/g, '').replace(',', '.'));

        if (!isNaN(aNum) && !isNaN(bNum)) {
            return asc ? aNum - bNum : bNum - aNum;
        }

        return asc ? aText.localeCompare(bText, 'fr') : bText.localeCompare(aText, 'fr');
    });

    // Re-append rows in sorted order.
    rows.forEach((row) => tbody.appendChild(row));
}

/**
 * Enables row submit buttons only when row inputs differ from initial values.
 *
 * @param {HTMLTableElement} table
 * @returns {void}
 */
export function enableSubmitOnChange(table) {
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');

    rows.forEach((row) => {
        const inputs = Array.from(row.querySelectorAll('input, select, textarea')).filter((input) => {
            if (!(input instanceof HTMLElement)) return false;
            if (input.matches('[data-row-change-ignore="1"]')) return false;
            if (input instanceof HTMLInputElement && input.type === 'file') {
                return input.matches('[data-row-change-track="1"]');
            }
            return true;
        });
        const submitButton = row.querySelector('button[type="submit"]');
        if (!submitButton) return;

        // Keep initial form state snapshot.
        const initialValues = inputs.map((input) => {
            if (input instanceof HTMLInputElement && input.type === 'file') {
                return (input.files && input.files.length) ? input.files.length : 0;
            }
            if (input.type === 'checkbox' || input.type === 'radio') {
                return input.checked;
            }
            return input.value;
        });

        const checkChanged = () => {
            return inputs.some((input, i) => {
                if (input instanceof HTMLInputElement && input.type === 'file') {
                    const now = (input.files && input.files.length) ? input.files.length : 0;
                    return now !== initialValues[i];
                }
                if (input.type === 'checkbox' || input.type === 'radio') {
                    return input.checked !== initialValues[i];
                }
                return input.value !== initialValues[i];
            });
        };

        inputs.forEach((input) => {
            input.addEventListener('input', () => {
                submitButton.disabled = !checkChanged();
            });
            input.addEventListener('change', () => {
                submitButton.disabled = !checkChanged();
            });
        });
    });
}

/**
 * Enables a single bulk submit button when any table input differs from initial values.
 *
 * Expected markup:
 * - a button with data-bulk-save-button="1" (usually outside <tbody>)
 *
 * @param {HTMLTableElement} table
 * @returns {void}
 */
export function enableBulkSubmitOnChange(table) {
    if (!table) return;
    if (table.__ctsBulkSubmitInit) return;
    table.__ctsBulkSubmitInit = true;

    const form = table.closest('form');
    const formId = form?.id || '';
    const submitButton =
        table.querySelector('button[data-bulk-save-button="1"]')
        || form?.querySelector?.('button[data-bulk-save-button="1"]')
        || (formId ? document.querySelector(`button[data-bulk-save-button="1"][form="${CSS.escape(formId)}"]`) : null)
        || null;
    if (!submitButton) return;

    const inputs = Array.from(table.querySelectorAll('input, select, textarea')).filter((input) => {
        if (!(input instanceof HTMLElement)) return false;
        if (input.matches('[data-row-change-ignore="1"]')) return false;
        if (input instanceof HTMLInputElement && input.type === 'file') {
            return input.matches('[data-row-change-track="1"]');
        }
        return true;
    });

    const initialValues = inputs.map((input) => {
        if (input instanceof HTMLInputElement && input.type === 'file') {
            return (input.files && input.files.length) ? input.files.length : 0;
        }
        if (input.type === 'checkbox' || input.type === 'radio') {
            return input.checked;
        }
        return input.value;
    });

    const checkChanged = () => {
        return inputs.some((input, i) => {
            if (input instanceof HTMLInputElement && input.type === 'file') {
                const now = (input.files && input.files.length) ? input.files.length : 0;
                return now !== initialValues[i];
            }
            if (input.type === 'checkbox' || input.type === 'radio') {
                return input.checked !== initialValues[i];
            }
            return input.value !== initialValues[i];
        });
    };

    const sync = () => {
        submitButton.disabled = !checkChanged();
    };

    // Keep the initial state consistent (e.g. when browser restores form values).
    sync();

    inputs.forEach((input) => {
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
    });

    // Some widgets (selectlike) can dispatch bubbling events from inside the enhanced UI;
    // ensure we always re-evaluate on any change within the table.
    table.addEventListener('input', sync, true);
    table.addEventListener('change', sync, true);
}
