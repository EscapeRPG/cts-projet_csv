import { sortTableByColumn, enableSubmitOnChange, adjustColumnWidths } from "sort_tables";

function initTable(table) {
    adjustColumnWidths(table);
}

// Ajoute une fonction de tri sur certaines colonnes de la table de salariés et prépare la table pour un affichage optimisé
function init() {
    const table = document.querySelector('.centres-list');
    if (!table) return;

    const headers = table.querySelectorAll('th');

    // Ajout de la fonction de tri
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

document.addEventListener('turbo:load', init);
