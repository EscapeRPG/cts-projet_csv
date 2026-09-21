function initEquipementsCollection(holder) {
    if (!(holder instanceof HTMLElement)) {
        return;
    }

    if (holder.dataset.initialized === 'true') {
        return;
    }

    holder.dataset.initialized = 'true';

    const list = holder.querySelector('[data-equipements-list]');
    const addButton = holder.querySelector('[data-add-equipement]');
    const prototype = holder.dataset.prototype;

    if (
        !(list instanceof HTMLElement)
        || !(addButton instanceof HTMLButtonElement)
        || !prototype
    ) {
        return;
    }

    /**
     * Retourne un index qui ne sera pas réutilisé après une suppression.
     */
    function nextIndex() {
        const index = Number.parseInt(holder.dataset.index ?? '0', 10);
        const next = Number.isNaN(index) ? 0 : index;

        holder.dataset.index = String(next + 1);

        return String(next);
    }

    /**
     * Retrouve un champ dans une ligne à partir de la fin de son nom.
     */
    function getField(row, fieldName) {
        return row.querySelector(`[name$="[${fieldName}]"]`);
    }

    /**
     * Retourne les portiques actuellement présents dans la collection.
     */
    function collectPortiques() {
        const portiques = [];

        list.querySelectorAll('[data-equipement-row]').forEach((row) => {
            if (!(row instanceof HTMLElement)) {
                return;
            }

            const categorie = getField(row, 'categorie');
            const libelle = getField(row, 'libelle');
            const index = row.dataset.index;

            if (
                !(categorie instanceof HTMLSelectElement)
                || !(libelle instanceof HTMLInputElement)
                || index === undefined
                || categorie.value !== 'portique'
            ) {
                return;
            }

            const nom = libelle.value.trim() || `Portique ligne ${index}`;

            portiques.push({
                value: index,
                label: `${nom} — ligne ${index}`,
            });
        });

        return portiques;
    }

    /**
     * Met à jour les selects et leur visibilité.
     */
    function refreshPortiqueSelects() {
        const portiques = collectPortiques();

        list.querySelectorAll('[data-equipement-row]').forEach((row) => {
            if (!(row instanceof HTMLElement)) {
                return;
            }

            const categorie = getField(row, 'categorie');
            const select = getField(row, 'portiqueTemporaire');
            const fieldContainer = row.querySelector('[data-portique-field]');
            const importNumber = getField(row, 'numeroPortiqueImport');
            const importFieldContainer = row.querySelector('[data-portique-import-field]');

            if (
                !(categorie instanceof HTMLSelectElement)
                || !(select instanceof HTMLSelectElement)
                || !(fieldContainer instanceof HTMLElement)
                || !(importNumber instanceof HTMLInputElement)
                || !(importFieldContainer instanceof HTMLElement)
            ) {
                return;
            }

            const isBorne = categorie.value === 'borne';
            const isPortique = categorie.value === 'portique';
            const previousValue = select.value;

            if (isBorne) {
                fieldContainer.classList.remove('hidden');
            } else {
                fieldContainer.classList.add('hidden');
            }

            select.disabled = !isBorne;

            if (isPortique) {
                importFieldContainer.classList.remove('hidden');
            } else {
                importFieldContainer.classList.add('hidden');
                importNumber.value = '';
            }

            importNumber.disabled = !isPortique;

            select.replaceChildren();

            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = '- Aucun portique -';
            select.appendChild(emptyOption);

            for (const portique of portiques) {
                const option = document.createElement('option');
                option.value = portique.value;
                option.textContent = portique.label;
                select.appendChild(option);
            }

            if (
                isBorne
                && portiques.some((portique) => portique.value === previousValue)
            ) {
                select.value = previousValue;
            } else {
                select.value = '';
            }
        });
    }

    function wireRow(row) {
        if (!(row instanceof HTMLElement)) {
            return;
        }

        const removeButton = row.querySelector('[data-remove-equipement]');

        if (removeButton instanceof HTMLButtonElement) {
            removeButton.addEventListener('click', () => {
                row.remove();
                refreshPortiqueSelects();
            });
        }
    }

    addButton.addEventListener('click', () => {
        const index = nextIndex();
        const generatedHtml = prototype.replace(/__name__/g, index);

        const prototypeContainer = document.createElement('div');
        prototypeContainer.innerHTML = generatedHtml;

        const row = document.createElement('div');
        row.className = 'equipement-row';
        row.dataset.equipementRow = '';
        row.dataset.index = index;

        while (prototypeContainer.firstChild) {
            row.appendChild(prototypeContainer.firstChild);
        }

        /*
         * Le prototype Symfony ne contient pas les marqueurs personnalisés
         * utilisés autour des lignes déjà rendues. On entoure donc le champ
         * portique après sa génération.
         */
        const portiqueSelect = getField(row, 'portiqueTemporaire');
        const portiqueImportNumber = getField(row, 'numeroPortiqueImport');

        if (portiqueSelect instanceof HTMLSelectElement) {
            const originalContainer = portiqueSelect.parentElement;

            if (originalContainer instanceof HTMLElement) {
                originalContainer.dataset.portiqueField = '';
            }
        }

        if (portiqueImportNumber instanceof HTMLInputElement) {
            const originalContainer = portiqueImportNumber.parentElement;

            if (originalContainer instanceof HTMLElement) {
                originalContainer.dataset.portiqueImportField = '';
            }
        }

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.textContent = '-';
        removeButton.dataset.removeEquipement = '';

        row.appendChild(removeButton);
        list.appendChild(row);

        wireRow(row);
        refreshPortiqueSelects();
    });

    /*
     * Délégation d’événements :
     * - changement de catégorie ;
     * - modification du nom d’un portique.
     */
    list.addEventListener('change', (event) => {
        const target = event.target;

        if (
            target instanceof HTMLSelectElement
            && target.name.endsWith('[categorie]')
        ) {
            refreshPortiqueSelects();
        }
    });

    list.addEventListener('input', (event) => {
        const target = event.target;

        if (
            target instanceof HTMLInputElement
            && target.name.endsWith('[libelle]')
        ) {
            refreshPortiqueSelects();
        }
    });

    list.querySelectorAll('[data-equipement-row]').forEach(wireRow);

    refreshPortiqueSelects();
}

function initStationEquipements() {
    document
        .querySelectorAll('[data-equipements-collection]')
        .forEach(initEquipementsCollection);
}

document.addEventListener('DOMContentLoaded', initStationEquipements);
document.addEventListener('turbo:load', initStationEquipements);
