function adjustColumnWidths(table) {
    if (!table) return;

    function getTextWidth(text, font) {
        const span = document.createElement('span');
        span.style.visibility = 'hidden';
        span.style.position = 'absolute';
        span.style.whiteSpace = 'pre';
        span.style.font = font;
        span.textContent = text;
        document.body.appendChild(span);
        const width = span.getBoundingClientRect().width;
        document.body.removeChild(span);
        return width;
    }

    const ths = table.querySelectorAll('thead th');
    ths.forEach((th, colIndex) => {
        let maxWidth = getTextWidth(th.textContent.trim(), getComputedStyle(th).font);

        table.querySelectorAll('tbody tr').forEach(tr => {
            const td = tr.children[colIndex];
            if (!td) return;

            const input = td.querySelector('input, select, textarea');
            if (!input) return;

            if (input.tagName.toLowerCase() === 'select') {
                for (let option of input.options) {
                    maxWidth = Math.max(maxWidth, getTextWidth(option.text, getComputedStyle(input).font) + 22);
                }
            } else if (input.type === 'date') {
                maxWidth = Math.max(maxWidth, getTextWidth('0000-00-00', getComputedStyle(input).font));
            } else {
                maxWidth = Math.max(maxWidth, getTextWidth(input.value || input.placeholder || ' ', getComputedStyle(input).font) + 8);
            }

            input.style.width = maxWidth + 'px';
        });
    });
}

function initTable(table) {
    adjustColumnWidths(table);
}

function sortTableByColumn(table, colIndex) {
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

function enableSubmitOnChange(table) {
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const inputs = row.querySelectorAll('input, select, textarea');
        const submitButton = row.querySelector('button[type="submit"]');
        if (!submitButton) return;

        // Stocker les valeurs initiales
        const initialValues = Array.from(inputs).map(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                return input.checked;
            }
            return input.value;
        });

        const checkChanged = () => {
            return Array.from(inputs).some((input, i) => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    return input.checked !== initialValues[i];
                }
                return input.value !== initialValues[i];
            });
        };

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                submitButton.disabled = !checkChanged();
            });
            input.addEventListener('change', () => {
                submitButton.disabled = !checkChanged();
            });
        });
    });
}

function init() {
    const table = document.querySelector('.salaries-list');
    if (!table) return;

    const headers = table.querySelectorAll('th');

    headers.forEach((th, index) => {
        const sortableColumns = ['Société', 'Nom', 'Nb heures', 'Salaire brut'];
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
