function initEncoursMontantsCollection(holder) {
    if (!(holder instanceof HTMLElement)) return;
    if (holder.dataset.encoursMontantsInit === '1') return;
    holder.dataset.encoursMontantsInit = '1';

    const prototype = holder.dataset.prototype;
    if (!prototype) return;

    const addBtn = holder.querySelector('[data-add-montant]');
    if (!(addBtn instanceof HTMLButtonElement)) return;

    const list = holder.querySelector('[data-montants-list]');
    if (!(list instanceof HTMLElement)) return;

    const nextIndex = () => {
        const raw = holder.dataset.index;
        const n = raw ? Number(raw) : list.children.length;
        const i = Number.isFinite(n) ? n : 0;
        holder.dataset.index = String(i + 1);
        return i;
    };

    const wireRemoveButtons = (root) => {
        root.querySelectorAll('[data-remove-montant]').forEach((btn) => {
            if (!(btn instanceof HTMLButtonElement)) return;
            if (btn.dataset.removeInit === '1') return;
            btn.dataset.removeInit = '1';
            btn.addEventListener('click', () => {
                const row = btn.closest('[data-montant-row]');
                row?.remove();
            });
        });
    };

    wireRemoveButtons(holder);

    addBtn.addEventListener('click', () => {
        const index = nextIndex();
        const html = prototype.replace(/__name__/g, String(index));

        // Wrap the prototype fields into a consistent "row" with a remove button.
        const protoWrapper = document.createElement('div');
        protoWrapper.innerHTML = html;

        const row = document.createElement('div');
        row.setAttribute('data-montant-row', '');
        row.className = 'encours-montant-item';

        // Move all generated nodes (typically the two field containers) into the row.
        Array.from(protoWrapper.childNodes).forEach((n) => row.appendChild(n));

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '-';
        removeBtn.setAttribute('data-remove-montant', '');
        removeBtn.setAttribute('aria-label', 'Supprimer');
        row.appendChild(removeBtn);

        // Insert just above the "+" button (UX request).
        list.insertBefore(row, null);
        wireRemoveButtons(row);
    });
}

function init() {
    document.querySelectorAll('[data-encours-montants]').forEach((holder) => {
        initEncoursMontantsCollection(holder);
    });
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('turbo:load', init);
