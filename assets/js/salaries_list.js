import { sortTableByColumn } from "sort_tables";

// Ajuste la largeur des colonnes à la valeur la plus longue de chacune d'elles
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

// Permet de rendre le bouton de soumission du formulaire cliquable si une information a été changée.
// Revient en disabled si les informations entrées sont les mêmes qu'au chargement
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

// Ajoute une fonction de tri sur certaines colonnes de la table de salariés et prépare la table pour un affichage optimisé
function init() {
    const table = document.querySelector('.salaries-list');
    if (!table) return;

    const headers = table.querySelectorAll('th');

    // Ajout de la fonction de tri
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
