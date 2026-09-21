function initCollectionButton(button) {
    if (!(button instanceof HTMLButtonElement) || button.dataset.collectionInitialized === '1') return;

    const wrapper = button.closest('.releve-table-wrapper');
    const collection = wrapper?.querySelector('[data-releve-collection]');
    if (!(collection instanceof HTMLTableSectionElement) || !collection.dataset.prototype) return;

    button.dataset.collectionInitialized = '1';

    collection.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-releve-row]');
        if (!(removeButton instanceof HTMLButtonElement)) return;

        removeButton.closest('[data-revenue-row]')?.remove();
        collection.closest('form')?.dispatchEvent(new Event('input', {bubbles: true}));
    });

    button.addEventListener('click', () => {
        const index = Number.parseInt(collection.dataset.index ?? '0', 10);
        const nextIndex = Number.isNaN(index) ? 0 : index;
        collection.insertAdjacentHTML(
            'beforeend',
            collection.dataset.prototype.replace(/__name__/g, String(nextIndex)),
        );
        collection.dataset.index = String(nextIndex + 1);

        const addedRow = collection.lastElementChild;
        addedRow?.querySelectorAll('input[type="number"]').forEach((input) => {
            if (input instanceof HTMLInputElement && input.value === '') input.value = '0';
        });
        const firstInput = addedRow?.querySelector('input');
        if (firstInput instanceof HTMLInputElement) firstInput.focus();

        collection.closest('form')?.dispatchEvent(new Event('input', {bubbles: true}));
    });
}

function initReleveCollections(root = document) {
    root.querySelectorAll('[data-add-releve-row]').forEach(initCollectionButton);
}

document.addEventListener('DOMContentLoaded', () => initReleveCollections());
document.addEventListener('turbo:load', () => initReleveCollections());
document.addEventListener('station:details-loaded', (event) => initReleveCollections(event.target));
