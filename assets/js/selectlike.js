function enhanceSelectlike(select) {
    if (!(select instanceof HTMLSelectElement)) return;
    if (select.multiple) return;
    if (select.dataset.selectlikeInit === '1') return;
    select.dataset.selectlikeInit = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'selectlike';

    const control = document.createElement('button');
    control.type = 'button';
    control.className = 'selectlike__control';
    control.setAttribute('aria-haspopup', 'listbox');
    control.setAttribute('aria-expanded', 'false');

    const dropdown = document.createElement('div');
    dropdown.className = 'selectlike__dropdown';
    dropdown.hidden = true;

    const list = document.createElement('div');
    list.className = 'selectlike__list';
    list.setAttribute('role', 'listbox');

    const optionToItem = new Map();
    const items = [];

    const getLabelForOption = (option) => (option?.textContent ?? '').trim();
    const getSelectedOption = () => select.selectedOptions?.[0] ?? select.options?.[select.selectedIndex] ?? null;

    const updateControlLabel = () => {
        const selected = getSelectedOption();
        const placeholder = (select.dataset.selectlikePlaceholder || '').trim();
        const label = selected && selected.value !== '' ? getLabelForOption(selected) : placeholder || getLabelForOption(selected) || '';
        control.textContent = label.toUpperCase();
    };

    const setSelected = (option) => {
        if (!(option instanceof HTMLOptionElement)) return;
        if (option.disabled) return;
        select.value = option.value;
        select.dispatchEvent(new Event('change', {bubbles: true}));
        updateControlLabel();
        items.forEach((item) => item.classList.toggle('is-selected', item.dataset.value === option.value));
    };

    const buildFromSelect = () => {
        list.textContent = '';
        optionToItem.clear();
        items.length = 0;

        Array.from(select.options).forEach((option) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'selectlike__item';
            item.setAttribute('role', 'option');
            item.dataset.value = option.value;
            item.disabled = option.disabled;
            item.textContent = getLabelForOption(option).toUpperCase();
            item.classList.toggle('is-selected', option.selected);

            item.addEventListener('click', () => {
                setSelected(option);
                close();
                control.focus();
            });

            optionToItem.set(option, item);
            items.push(item);
            list.appendChild(item);
        });
    };

    const open = () => {
        dropdown.hidden = false;
        dropdown.style.display = 'block';
        control.setAttribute('aria-expanded', 'true');
        positionDropdown();

        const selected = getSelectedOption();
        const selectedItem = selected ? optionToItem.get(selected) : null;
        (selectedItem || items[0])?.focus?.();
    };

    const close = () => {
        dropdown.hidden = true;
        dropdown.style.display = 'none';
        control.setAttribute('aria-expanded', 'false');
    };

    let positionRaf = 0;
    const positionDropdown = () => {
        const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

        if (positionRaf) cancelAnimationFrame(positionRaf);
        positionRaf = requestAnimationFrame(() => {
            positionRaf = 0;
            const rect = control.getBoundingClientRect();
            const vw = window.innerWidth || document.documentElement.clientWidth || 0;
            const vh = window.innerHeight || document.documentElement.clientHeight || 0;
            const gap = 6;
            const margin = 8;

            const dropdownRect = dropdown.getBoundingClientRect();
            const dh = dropdownRect.height || 0;

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

            top = clamp(top, margin, Math.max(margin, vh - dh - margin));

            let left = rect.left;
            left = clamp(left, margin, Math.max(margin, vw - rect.width - margin));

            dropdown.style.setProperty('position', 'fixed', 'important');
            dropdown.style.setProperty('top', `${top}px`, 'important');
            dropdown.style.setProperty('left', `${left}px`, 'important');
            dropdown.style.setProperty('right', 'auto', 'important');
            // Pin width to the control to avoid runaway growth due to repeated measuring.
            dropdown.style.setProperty('width', `${rect.width}px`, 'important');
            dropdown.style.setProperty('z-index', '9999', 'important');
        });
    };

    control.addEventListener('click', () => {
        if (dropdown.hidden) open();
        else close();
    });

    // Some global button styles add hover transitions that can slightly change geometry.
    // When the dropdown is open, keep it pinned to the control.
    control.addEventListener('pointerenter', () => {
        if (!dropdown.hidden) positionDropdown();
    });
    control.addEventListener('transitionend', () => {
        if (!dropdown.hidden) positionDropdown();
    });
    // Note: no pointermove; it can spam requestAnimationFrame() calls on some pages.

    const closeOnOutsidePointerDown = (event) => {
        const target = event.target;
        if (!(target instanceof Node)) return;
        if (wrapper.contains(target) || dropdown.contains(target)) return;
        if (!dropdown.hidden) close();
    };

    document.addEventListener('pointerdown', closeOnOutsidePointerDown, true);
    document.addEventListener('click', closeOnOutsidePointerDown, true);

    document.addEventListener('keydown', (event) => {
        if (dropdown.hidden) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            control.focus();
            return;
        }
    });

    const repositionIfOpen = () => {
        if (!dropdown.hidden) positionDropdown();
    };
    window.addEventListener('scroll', repositionIfOpen, true);
    window.addEventListener('resize', repositionIfOpen);

    select.addEventListener('change', () => {
        items.forEach((item) => item.classList.toggle('is-selected', item.dataset.value === select.value));
        updateControlLabel();
    });

    buildFromSelect();
    updateControlLabel();

    dropdown.appendChild(list);

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    wrapper.appendChild(control);
    wrapper.appendChild(dropdown);

    select.classList.add('selectlike__native');
    dropdown.style.display = 'none';
}

function init() {
    // Default behavior: enhance all single selects.
    // Opt-out by setting `data-selectlike="0"`.
    document.querySelectorAll('select:not([multiple]):not([data-selectlike="0"])').forEach((select) => {
        enhanceSelectlike(select);
    });
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('turbo:load', init);
document.addEventListener('cts:list:content-updated', (e) => {
    const root = e?.detail?.container ?? document;
    root.querySelectorAll('select:not([multiple]):not([data-selectlike="0"])').forEach((select) => {
        enhanceSelectlike(select);
    });
});
