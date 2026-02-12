import { sortTableByColumn } from "sort_tables";

// Ajoute une fonction de tri sur certaines colonnes de la table de salariés et prépare la table pour un affichage optimisé
function init() {
    const table = document.querySelector('.table-pros');
    if (!table) return;

    const headers = table.querySelectorAll('th');

    // Ajout de la fonction de tri
    headers.forEach((th, index) => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', () => {
            sortTableByColumn(table, index);
        });
        th.classList.add('sorted-asc');
    });
}

document.addEventListener('turbo:load', init);
