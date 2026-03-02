import { sortTableByColumn, enableSubmitOnChange, adjustColumnWidths } from "sort_tables";

function initTable(table) {
    adjustColumnWidths(table);
}

function init() {
    const table = document.querySelector('.societes-list');
    if (!table) return;

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

    initTable(table);
    enableSubmitOnChange(table);
}

document.addEventListener('turbo:load', init);
