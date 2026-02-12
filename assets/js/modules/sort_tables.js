// Trie les colonnes par ordre alphabétique, ou inverse si on re-clique
export function sortTableByColumn(table, colIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    // Déterminer l'ordre
    let asc = !tbody.dataset.sortAsc || tbody.dataset.sortCol != colIndex || tbody.dataset.sortAsc === 'false';
    tbody.dataset.sortAsc = asc;
    tbody.dataset.sortCol = colIndex;

    // Ajouter la classe de tri à la colonne active
    const th = table.querySelectorAll('th')[colIndex];
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

        // Comparaison numérique si possible
        const aNum = parseFloat(aText.replace(/\s/g, '').replace(',', '.'));
        const bNum = parseFloat(bText.replace(/\s/g, '').replace(',', '.'));

        if (!isNaN(aNum) && !isNaN(bNum)) {
            return asc ? aNum - bNum : bNum - aNum;
        }

        return asc ? aText.localeCompare(bText, 'fr') : bText.localeCompare(aText, 'fr');
    });

    // Réinjecter les lignes triées
    rows.forEach(row => tbody.appendChild(row));
}
