import { sortTableByColumn, enableSubmitOnChange } from "sort_tables";

/**
 * Initializes sortable columns and inline submit behavior for salaries table.
 *
 * @param {ParentNode} root
 * @returns {void}
 */
export function initSalariesList(root = document) {
    const table = root.querySelector('.salaries-list');
    if (!table) return;
    if (table.dataset.ctsInit === '1') return;
    table.dataset.ctsInit = '1';

    const headers = table.querySelectorAll('th');

    // Enable sorting only for selected columns.
    headers.forEach((th, index) => {
        const sortableColumns = ['Société', 'Nom', 'Nb heures', 'Salaire brut', 'Agr', 'Agr CL', 'Actif', 'Actif ?'];
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

document.addEventListener('turbo:load', () => {
    initSalariesList(document);
});

document.addEventListener('cts:list:content-updated', (e) => {
    initSalariesList(e?.detail?.container ?? document);
});
