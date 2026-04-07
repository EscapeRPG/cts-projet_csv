function enhanceCentresSelect(select) {
    if (!(select instanceof HTMLSelectElement)) return;
    if (select.dataset.centresSelectlikeInit === '1') return;
    select.dataset.centresSelectlikeInit = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'selectlike-multi';

    const control = document.createElement('button');
    control.type = 'button';
    control.className = 'selectlike-multi__control';
    control.setAttribute('aria-haspopup', 'listbox');
    control.setAttribute('aria-expanded', 'false');

    const dropdown = document.createElement('div');
    dropdown.className = 'selectlike-multi__dropdown';
    dropdown.hidden = true;

    const list = document.createElement('div');
    list.className = 'selectlike-multi__list';
    list.setAttribute('role', 'listbox');

    const optionToCheckbox = new Map();

    const addGroup = (label, options) => {
        const group = document.createElement('div');
        group.className = 'selectlike-multi__group';

        const title = document.createElement('div');
        title.className = 'selectlike-multi__group-title';
        title.textContent = label;
        group.appendChild(title);

        options.forEach((option) => {
            const item = document.createElement('label');
            item.className = 'selectlike-multi__item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = option.selected;
            checkbox.disabled = option.disabled;

            const text = document.createElement('span');
            text.className = 'selectlike-multi__item-label';
            text.textContent = option.textContent ?? '';

            item.appendChild(checkbox);
            item.appendChild(text);
            group.appendChild(item);

            optionToCheckbox.set(option, checkbox);

            checkbox.addEventListener('change', () => {
                option.selected = checkbox.checked;
                updateControlLabel();
                // Keep any listeners in sync.
                select.dispatchEvent(new Event('change', {bubbles: true}));
            });
        });

        list.appendChild(group);
    };

    const buildFromSelect = () => {
        list.innerHTML = '';
        optionToCheckbox.clear();

        const directOptions = [];

        Array.from(select.children).forEach((child) => {
            if (child instanceof HTMLOptGroupElement) {
                const groupOptions = Array.from(child.querySelectorAll('option'));
                addGroup(child.label || '', groupOptions);
                return;
            }
            if (child instanceof HTMLOptionElement) {
                directOptions.push(child);
            }
        });

        if (directOptions.length) {
            addGroup('', directOptions);
        }
    };

    const updateControlLabel = () => {
        const selected = Array.from(select.selectedOptions || []);
        if (!selected.length) {
            control.textContent = '- Choisir -';
            return;
        }
        if (selected.length === 1) {
            control.textContent = selected[0].textContent || '- Choisir -';
            return;
        }
        control.textContent = `${selected.length} centres sélectionnés`;
    };

    const open = () => {
        dropdown.hidden = false;
        dropdown.style.display = 'block';
        control.setAttribute('aria-expanded', 'true');
        wrapper.classList.add('is-open');

        // Portal the dropdown to <body> to avoid table-row stacking contexts (e.g. filter on hover).
        if (!dropdown.dataset.portalActive) {
            dropdown.dataset.portalActive = '1';
            dropdown.dataset.portalRestoreNext = '';
            dropdown.dataset.portalRestoreParent = '';
        }

        if (!dropdown.__portal) {
            const restoreParent = dropdown.parentNode;
            const restoreNext = dropdown.nextSibling;
            dropdown.__portal = { restoreParent, restoreNext };
        }

        document.body.appendChild(dropdown);
        positionDropdown();
    };

    const close = () => {
        dropdown.hidden = true;
        dropdown.style.display = 'none';
        control.setAttribute('aria-expanded', 'false');
        wrapper.classList.remove('is-open');

        // Restore dropdown back into the wrapper to keep DOM tidy.
        if (dropdown.__portal && dropdown.__portal.restoreParent) {
            const parent = dropdown.__portal.restoreParent;
            const next = dropdown.__portal.restoreNext;
            if (next && next.parentNode === parent) {
                parent.insertBefore(dropdown, next);
            } else {
                parent.appendChild(dropdown);
            }
        }
    };

    const positionDropdown = () => {
        const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

        // Defer measurement to the next frame to ensure layout is up to date after portal/DOM changes.
        requestAnimationFrame(() => {
            const rect = control.getBoundingClientRect();
            const vw = window.innerWidth || document.documentElement.clientWidth || 0;
            const vh = window.innerHeight || document.documentElement.clientHeight || 0;
            const gap = 6;
            const margin = 8;

            const dropdownRect = dropdown.getBoundingClientRect();
            const dh = dropdownRect.height || 0;
            const dw = dropdownRect.width || 0;

            const preferBelow = rect.top < (vh * 0.5);

            const belowTop = rect.bottom + gap;
            const aboveTop = rect.top - gap - dh;

            const belowSpace = vh - rect.bottom;
            const aboveSpace = rect.top;

            const belowFits = (belowTop + dh) <= (vh - margin);
            const aboveFits = aboveTop >= margin;

            let top;
            if (preferBelow) {
                if (belowFits) top = belowTop;
                else if (aboveFits) top = aboveTop;
                else top = (belowSpace >= aboveSpace) ? belowTop : aboveTop;
            } else {
                if (aboveFits) top = aboveTop;
                else if (belowFits) top = belowTop;
                else top = (aboveSpace >= belowSpace) ? aboveTop : belowTop;
            }

            // Clamp within viewport to avoid rendering off-screen.
            top = clamp(top, margin, Math.max(margin, vh - dh - margin));

            // Horizontal clamp too (dropdown can be wider than the control if width is max-content).
            let left = rect.left;
            left = clamp(left, margin, Math.max(margin, vw - dw - margin));

            // Use !important to override any user CSS using !important on these properties.
            dropdown.style.setProperty('position', 'fixed', 'important');
            dropdown.style.setProperty('top', `${top}px`, 'important');
            dropdown.style.setProperty('left', `${left}px`, 'important');
            dropdown.style.setProperty('right', 'auto', 'important');
            dropdown.style.setProperty('width', `max-content`, 'important');
            dropdown.style.setProperty('z-index', '9999', 'important');
        });
    };

    control.addEventListener('click', () => {
        if (dropdown.hidden) open();
        else close();
    });

    const closeOnOutsidePointerDown = (event) => {
        const target = event.target;
        if (!(target instanceof Node)) return;
        if (wrapper.contains(target) || dropdown.contains(target)) return;
        if (!dropdown.hidden) close();
    };

    // Use capture so other handlers calling stopPropagation() can't prevent closing.
    document.addEventListener('pointerdown', closeOnOutsidePointerDown, true);
    document.addEventListener('click', closeOnOutsidePointerDown, true);

    document.addEventListener('focusin', (event) => {
        const target = event.target;
        if (!(target instanceof Node)) return;
        if (wrapper.contains(target) || dropdown.contains(target)) return;
        if (!dropdown.hidden) close();
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dropdown.hidden) {
            close();
        }
    });

    // Keep portal dropdown aligned with the control on scroll/resize.
    const repositionIfOpen = () => {
        if (!dropdown.hidden) {
            positionDropdown();
        }
    };
    window.addEventListener('scroll', repositionIfOpen, true);
    window.addEventListener('resize', repositionIfOpen);

    // If someone changes the native select programmatically.
    select.addEventListener('change', () => {
        Array.from(optionToCheckbox.entries()).forEach(([option, checkbox]) => {
            checkbox.checked = option.selected;
        });
        updateControlLabel();
    });

    buildFromSelect();
    updateControlLabel();

    dropdown.appendChild(list);

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    wrapper.appendChild(control);
    wrapper.appendChild(dropdown);

    select.classList.add('selectlike-multi__native');
    // Ensure the dropdown starts closed even if CSS forces a display value.
    dropdown.style.display = 'none';
}

function init() {
    document.querySelectorAll('select[multiple][data-centres-selectlike="1"]').forEach((select) => {
        enhanceCentresSelect(select);
    });
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('turbo:load', init);
document.addEventListener('cts:list:content-updated', (e) => {
    const root = e?.detail?.container ?? document;
    root.querySelectorAll('select[multiple][data-centres-selectlike="1"]').forEach((select) => {
        enhanceCentresSelect(select);
    });
});
