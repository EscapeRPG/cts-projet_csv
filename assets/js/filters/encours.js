import {scheduleRefreshResults} from "./ajax_results.js";

/**
 * Initializes encours-specific filters behavior.
 *
 * @param {HTMLElement} filtersRoot
 * @param {HTMLFormElement} form
 * @param {string} currentRoute
 * @returns {void}
 */
export function initEncoursFilters(filtersRoot, form, currentRoute) {
    if (currentRoute !== 'app_encours_bancaires') return;

    const typeLinks = Array.from(filtersRoot.querySelectorAll('.onglets [data-encours-type]'))
        .filter((el) => el instanceof HTMLAnchorElement);

    const setTypeValue = (value) => {
        const normalized = String(value ?? '').trim();
        let input = form.querySelector('input[name="type"]');
        if (input instanceof HTMLInputElement) {
            input.value = normalized;
            return;
        }
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'type';
        input.value = normalized;
        form.appendChild(input);
    };

    const updateEncoursSocietesVisibility = () => {
        const container = document.querySelector('[data-ajax-results]');
        if (!(container instanceof HTMLElement)) return;
        const raw = String(container.dataset.encoursSocietes ?? '').trim();
        if (!raw) return;

        const allowed = new Set(raw.split(',').map((v) => v.trim()).filter((v) => v !== ''));
        const inputs = Array.from(filtersRoot.querySelectorAll('input[name="societe[]"]'))
            .filter((el) => el instanceof HTMLInputElement);

        inputs.forEach((input) => {
            const label = input.closest('label');
            if (!(label instanceof HTMLElement)) return;
            const show = allowed.has(String(input.value));
            label.style.display = show ? '' : 'none';
        });
    };

    typeLinks.forEach((link) => {
        link.addEventListener('click', async (event) => {
            event.preventDefault();

            const typeValue = link.dataset.encoursType ?? '';
            setTypeValue(typeValue);

            typeLinks.forEach((item) => item.classList.remove('active'));
            link.classList.add('active');

            await scheduleRefreshResults(form, 120);
        });
    });

    updateEncoursSocietesVisibility();
    document.addEventListener('suivi:results-updated', updateEncoursSocietesVisibility);
}

