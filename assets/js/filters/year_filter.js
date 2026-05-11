import {scheduleRefreshResults, syncUrlWithForm} from "./ajax_results.js";

/**
 * Sets or clears hidden year input in filters form.
 *
 * @param {HTMLFormElement} form
 * @param {string} inputName
 * @param {string|number|null|undefined} yearValue
 * @returns {void}
 */
export function setYearValue(form, inputName, yearValue) {
    const normalized = String(yearValue ?? '').trim();
    const name = String(inputName || 'annee').trim() || 'annee';
    const existing = form.querySelector(`input[name="${CSS.escape(name)}"]`);

    if (normalized === '') {
        if (existing) {
            existing.remove();
        }
        return;
    }

    if (existing) {
        existing.value = normalized;
        return;
    }

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = name;
    hidden.value = normalized;
    form.appendChild(hidden);
}

/**
 * Binds year shortcut links to form updates and result refresh.
 *
 * @param {HTMLFormElement} form
 * @returns {void}
 */
export function bindYearFilter(form) {
    const yearLinks = document.querySelectorAll('.annees-filter a[data-year-value]');
    if (!yearLinks.length) return;

    yearLinks.forEach((link) => {
        link.addEventListener('click', async (event) => {
            event.preventDefault();

            const yearValue = link.dataset.yearValue ?? '';
            setYearValue(form, 'annee', yearValue);

            yearLinks.forEach((item) => item.classList.remove('active'));
            link.classList.add('active');

            await scheduleRefreshResults(form, 120);
        });
    });
}

/**
 * Binds year range inputs (from/to) for encours.
 *
 * Expected markup inside ".annees-filter":
 * - input[data-encours-year-from] and input[data-encours-year-to]
 *
 * @param {HTMLFormElement} form
 * @returns {void}
 */
export function bindYearRangeFilter(form) {
    const fromControl = form.querySelector('[data-encours-year-from]');
    const toControl = form.querySelector('[data-encours-year-to]');
    if (!(fromControl instanceof HTMLElement) && !(toControl instanceof HTMLElement)) return;

    const normalize = (v) => {
        const s = String(v ?? '').trim();
        if (s === '') return '';
        const n = Number(s);
        return Number.isFinite(n) && n > 0 ? String(Math.trunc(n)) : '';
    };

    const readValue = (control) => {
        if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement) {
            return control.value;
        }
        if (control instanceof HTMLElement) {
            const checked = control.querySelector('input[type="radio"]:checked');
            if (checked instanceof HTMLInputElement) return checked.value;
        }
        return '';
    };

    const apply = async () => {
        if (fromControl instanceof HTMLElement) {
            setYearValue(form, 'annee_debut', normalize(readValue(fromControl)));
        }
        if (toControl instanceof HTMLElement) {
            setYearValue(form, 'annee_fin', normalize(readValue(toControl)));
        }
        syncUrlWithForm(form);
        await scheduleRefreshResults(form, 200);
    };

    if (fromControl instanceof HTMLInputElement || fromControl instanceof HTMLSelectElement) {
        fromControl.addEventListener('change', apply);
        if (fromControl instanceof HTMLInputElement) {
            fromControl.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') apply();
            });
        }
    } else if (fromControl instanceof HTMLElement) {
        fromControl.addEventListener('change', apply);
    }
    if (toControl instanceof HTMLInputElement || toControl instanceof HTMLSelectElement) {
        toControl.addEventListener('change', apply);
        if (toControl instanceof HTMLInputElement) {
            toControl.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') apply();
            });
        }
    } else if (toControl instanceof HTMLElement) {
        toControl.addEventListener('change', apply);
    }
}
