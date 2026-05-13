import { sortTableByColumn, enableSubmitOnChange } from "sort_tables";

/**
 * Initializes sortable columns and inline submit behavior for companies table.
 *
 * @param {ParentNode} root
 * @returns {void}
 */
export function initSocietesList(root = document) {
    const table = root.querySelector('.societes-list');
    if (!table) return;
    // Don't use a data-* attribute for init-guard: Turbo snapshots can preserve
    // dataset values while event listeners are lost, which would prevent re-init.
    if (table.__ctsInit) return;
    table.__ctsInit = true;

    const headers = table.querySelectorAll('th');

    headers.forEach((th, index) => {
        const sortableColumns = ['Nom', 'Numéro de TVA'];
        if (sortableColumns.includes(th.textContent.trim())) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                sortTableByColumn(table, index);
            });
            th.classList.add('sorted-asc');
        }
    });

    enableSubmitOnChange(table);
}

const boot = () => initSocietesList(document);
document.addEventListener('DOMContentLoaded', boot);
document.addEventListener('turbo:load', boot);
// Needed when Turbo restores a cached snapshot (event listeners are not preserved).
document.addEventListener('turbo:render', boot);
document.addEventListener('cts:list:content-updated', (e) => {
    initSocietesList(e?.detail?.container ?? document);
});
