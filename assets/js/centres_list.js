import { sortTableByColumn, enableSubmitOnChange, adjustColumnWidths } from "sort_tables";

/**
 * Applies dynamic column width adjustments.
 *
 * @param {HTMLTableElement} table
 * @returns {void}
 */
function initTable(table) {
    adjustColumnWidths(table);
}

/**
 * Initializes sortable columns and inline submit behavior for centers table.
 *
 * @param {ParentNode} root
 * @returns {void}
 */
export function initCentresList(root = document) {
    const table = root.querySelector('.centres-list');
    if (!table) return;
    if (table.dataset.ctsInit === '1') return;
    table.dataset.ctsInit = '1';

    const headers = table.querySelectorAll('th');

    // Enable sorting only for selected columns.
    headers.forEach((th, index) => {
        const sortableColumns = ['Société', 'Réseau', 'CP', 'Ville'];
        if (sortableColumns.includes(th.textContent.trim())) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                sortTableByColumn(table, index);
            });
            th.classList.add('sorted-asc');
        }
    });

    initTable(table);
    enableSubmitOnChange(table);
}

document.addEventListener('turbo:load', () => initCentresList(document));
document.addEventListener('cts:list:content-updated', (e) => {
    initCentresList(e?.detail?.container ?? document);
});
