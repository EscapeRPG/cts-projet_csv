import { sortTableByColumn, enableSubmitOnChange, enableBulkSubmitOnChange } from "sort_tables";

/**
 * Initializes sortable columns and inline submit behavior for centers table.
 *
 * @param {ParentNode} root
 * @returns {void}
 */
export function initVoituresList(root = document) {
    const table = root.querySelector('.voitures-list');
    if (!table) return;
    // Don't use a data-* attribute for init-guard: Turbo snapshots can preserve
    // dataset values while event listeners are lost, which would prevent re-init.
    if (table.__ctsInit) return;
    table.__ctsInit = true;

    const headers = table.querySelectorAll('th');

    // Enable sorting only for selected columns.
    headers.forEach((th, index) => {
        const sortableColumns = ['Société', 'Centre', 'Flocable', 'Active'];
        if (sortableColumns.includes(th.textContent.trim())) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                sortTableByColumn(table, index);
            });
            th.classList.add('sorted-asc');
        }
    });

    if (table.dataset.bulkEdit === '1') {
        enableBulkSubmitOnChange(table);
    } else {
        enableSubmitOnChange(table);
    }
}

const boot = () => initVoituresList(document);
document.addEventListener('DOMContentLoaded', boot);
document.addEventListener('turbo:load', boot);
// Needed when Turbo restores a cached snapshot (event listeners are not preserved).
document.addEventListener('turbo:render', boot);
document.addEventListener('cts:list:content-updated', (e) => {
    initVoituresList(e?.detail?.container ?? document);
});
