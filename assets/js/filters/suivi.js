import {applyActivityTableColumnVisibility} from "../activity_table.js";
import {clearRefreshDebounceTimer, refreshResults, scheduleDebouncedTask, scheduleRefreshResults, syncUrlWithForm} from "./ajax_results.js";
import {setYearValue} from "./year_filter.js";

let dependentFiltersRequestController = null;
let activeDependentFiltersRequestToken = 0;
let pendingSocieteChange = false;
let pendingCentreChange = false;

function getCheckedValues(form, inputName) {
    return new Set(Array.from(form.querySelectorAll(`input[name="${inputName}"]:checked`)).map((input) => input.value));
}

function getSelectedActivityTypes(form) {
    return getCheckedValues(form, 'type[]');
}

function getSelectedActivityVehicles(form) {
    return getCheckedValues(form, 'vehicule[]');
}

function applyActivityColumnVisibility(form) {
    const table = document.querySelector('[data-activity-table]');
    if (!(table instanceof HTMLTableElement)) return;

    const selectedTypes = getSelectedActivityTypes(form);
    const selectedVehicles = getSelectedActivityVehicles(form);
    applyActivityTableColumnVisibility(table, selectedTypes, selectedVehicles);
}

function updateCentreDetailsPrintAction(form) {
    const actions = document.querySelectorAll('[data-centre-details-print-action]');
    if (!actions.length) return;

    const selectedCentreCount = form.querySelectorAll('input[name="centre[]"]:checked').length;
    actions.forEach((action) => {
        if (action instanceof HTMLElement) {
            if (selectedCentreCount === 1) action.classList.remove('hidden');
            else action.classList.add('hidden');
        }
    });
}

function renderChoiceList(container, inputName, values, checkedValues, labelBuilder) {
    container.innerHTML = '';

    const inputType = container.dataset.filterInputType === 'radio' ? 'radio' : 'checkbox';

    values.forEach((value) => {
        const label = document.createElement('label');
        const input = document.createElement('input');

        input.type = inputType;
        input.name = inputName;
        input.value = value.value;
        input.checked = checkedValues.has(value.value);

        label.appendChild(input);
        label.appendChild(document.createTextNode(` ${labelBuilder(value)}`));
        container.appendChild(label);
    });
}

async function refreshDependentFilters(form, updateCentres = true, includeCentresFilter = true) {
    const endpoint = form.dataset.dependentUrl;
    if (!endpoint) return;
    const requestToken = ++activeDependentFiltersRequestToken;

    const selectedSocietes = Array.from(form.querySelectorAll('input[name="societe[]"]:checked')).map((input) => input.value);
    const selectedCentresList = Array.from(form.querySelectorAll('input[name="centre[]"]:checked')).map((input) => input.value);
    const selectedCentres = new Set(selectedCentresList);
    const selectedControleurs = getCheckedValues(form, 'controleur[]');

    const params = new URLSearchParams();
    selectedSocietes.forEach((societe) => params.append('societe[]', societe));
    if (includeCentresFilter) {
        selectedCentresList.forEach((centre) => params.append('centre[]', centre));
    }

    if (dependentFiltersRequestController) {
        dependentFiltersRequestController.abort();
    }

    dependentFiltersRequestController = new AbortController();

    try {
        const response = await fetch(`${endpoint}?${params.toString()}`, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: dependentFiltersRequestController.signal,
        });
        if (!response.ok) return;
        if (requestToken !== activeDependentFiltersRequestToken) return;

        const data = await response.json();
        const centres = Array.isArray(data.centres) ? data.centres : [];
        const controleurs = Array.isArray(data.controleurs) ? data.controleurs : [];

        const centresContainer = form.querySelector('[data-filter-list="centre"]');
        if (updateCentres && centresContainer instanceof HTMLElement) {
            renderChoiceList(
                centresContainer,
                'centre[]',
                centres.map((c) => ({value: String(c.agr_centre ?? ''), label: String(c.label ?? '')})).filter((c) => c.value !== ''),
                selectedCentres,
                (c) => c.label || c.value
            );
        }

        const controleursContainer = form.querySelector('[data-filter-list="controleur"]');
        if (controleursContainer instanceof HTMLElement) {
            renderChoiceList(
                controleursContainer,
                'controleur[]',
                controleurs.map((c) => ({
                    value: String(c.id ?? ''),
                    nom: String(c.nom ?? ''),
                    prenom: String(c.prenom ?? ''),
                })).filter((c) => c.value !== ''),
                selectedControleurs,
                (c) => `${c.nom} ${c.prenom}`.trim() || c.value
            );
        }
    } catch (e) {
        // ignore
    } finally {
        if (requestToken === activeDependentFiltersRequestToken) {
            dependentFiltersRequestController = null;
        }
    }
}

function setMonthsMode(form, mode) {
    const normalized = String(mode ?? '').trim();
    const existing = form.querySelector('input[name="mois_mode"]');

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
    hidden.name = 'mois_mode';
    hidden.value = normalized;
    form.appendChild(hidden);
}

/**
 * Initializes suivi-specific filters behavior.
 *
 * @param {HTMLFormElement} form
 * @param {string} currentRoute
 * @returns {void}
 */
export function initSuiviFilters(form, currentRoute) {
    // Months actions are present only on suivi pages.
    const monthInputs = Array.from(form.querySelectorAll('input[type="checkbox"][name="mois[]"]'))
        .filter((el) => el instanceof HTMLInputElement);

    if (monthInputs.length) {
        const urlParams = new URLSearchParams(window.location.search || '');
        const hasExplicitMonths = urlParams.has('mois') || urlParams.has('mois[]');
        const hasMonthMode = urlParams.has('mois_mode');
        if (!hasExplicitMonths && !hasMonthMode) {
            setMonthsMode(form, 'ytd');
            syncUrlWithForm(form);
        }
    }

    const toggleAllMonths = form.querySelector('[data-months-toggle-all]');
    if (toggleAllMonths instanceof HTMLInputElement && monthInputs.length) {
        const updateToggleState = () => {
            const allChecked = monthInputs.every((i) => i.checked);
            const someChecked = monthInputs.some((i) => i.checked);
            toggleAllMonths.checked = allChecked;
            toggleAllMonths.indeterminate = !allChecked && someChecked;
        };
        updateToggleState();

        toggleAllMonths.addEventListener('change', async () => {
            const checked = toggleAllMonths.checked;
            monthInputs.forEach((input) => {
                input.checked = checked;
            });

            setMonthsMode(form, checked ? 'all' : '');
            syncUrlWithForm(form);
            await scheduleRefreshResults(form, 120);
        });

        form.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) return;
            if (target.name === 'mois[]') {
                updateToggleState();
            }
        });
    }

    const selectYtdMonthsBtn = form.querySelector('[data-months-select-ytd]');
    if (selectYtdMonthsBtn && monthInputs.length) {
        selectYtdMonthsBtn.addEventListener('click', async () => {
            const currentMonth = new Date().getMonth() + 1;
            monthInputs.forEach((input) => {
                const v = Number(input.value);
                input.checked = Number.isFinite(v) && v >= 1 && v <= currentMonth;
            });

            setMonthsMode(form, 'ytd');
            syncUrlWithForm(form);
            await scheduleRefreshResults(form, 120);
        });
    }

    applyActivityColumnVisibility(form);
    document.addEventListener('suivi:results-updated', () => applyActivityColumnVisibility(form));

    if (currentRoute === 'app_suivi_centre_details') {
        updateCentreDetailsPrintAction(form);
        document.addEventListener('suivi:results-updated', () => updateCentreDetailsPrintAction(form));
    }

    form.addEventListener('change', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) return;

        if (currentRoute === 'app_suivi_centre_details') {
            updateCentreDetailsPrintAction(form);
        }

        const isActivityLocalColumnFilter = currentRoute === 'app_suivi_activite'
            && (target.name === 'type[]' || target.name === 'vehicule[]');

        if (isActivityLocalColumnFilter) {
            applyActivityColumnVisibility(form);
            syncUrlWithForm(form);
            return;
        }

        syncUrlWithForm(form);

        if (target.name === 'societe[]') {
            pendingSocieteChange = true;
            pendingCentreChange = false;
            await scheduleDebouncedTask(async () => {
                await refreshDependentFilters(form, true, false);
                pendingSocieteChange = false;
                pendingCentreChange = false;
                await refreshResults(form);
            }, 450);
            return;
        }

        if (target.name === 'centre[]') {
            if (!pendingSocieteChange) {
                pendingCentreChange = true;
            }
            await scheduleDebouncedTask(async () => {
                await refreshDependentFilters(form, false, true);
                pendingSocieteChange = false;
                pendingCentreChange = false;
                await refreshResults(form);
            }, 450);
            return;
        }

        if (target.name === 'mois[]') {
            setMonthsMode(form, '');
            await scheduleRefreshResults(form, 120);
            return;
        }

        await scheduleDebouncedTask(() => refreshResults(form), 450);
    });

    const clearButton = form.querySelector('[data-clear-filters]');
    if (clearButton) {
        clearButton.addEventListener('click', async () => {
            clearRefreshDebounceTimer();
            pendingSocieteChange = false;
            pendingCentreChange = false;

            const preserveCentreOnClear = form.dataset.preserveCentreOnClear === '1';
            const checkedBoxes = form.querySelectorAll('input[type="checkbox"]:checked');
            checkedBoxes.forEach((input) => {
                if (preserveCentreOnClear && input instanceof HTMLInputElement && input.name === 'centre[]') {
                    return;
                }
                input.checked = false;
            });

            // Clear year only for encours page; on suivis the year filter is a primary view context.
            if (currentRoute === 'app_encours_bancaires') {
                setYearValue(form, 'annee_debut', '');
                setYearValue(form, 'annee_fin', '');

                const clearYearControl = (selector) => {
                    const control = form.querySelector(selector);
                    if (!(control instanceof HTMLElement)) return;

                    if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement) {
                        control.value = '';
                        return;
                    }

                    const radios = Array.from(control.querySelectorAll('input[type="radio"]'));
                    const emptyRadio = radios.find((r) => r instanceof HTMLInputElement && (r.value ?? '') === '');
                    if (emptyRadio instanceof HTMLInputElement) {
                        emptyRadio.checked = true;
                    } else {
                        radios.forEach((r) => {
                            if (r instanceof HTMLInputElement) r.checked = false;
                        });
                    }
                };

                clearYearControl('[data-encours-year-from]');
                clearYearControl('[data-encours-year-to]');
            }

            if (monthInputs.length) {
                monthInputs.forEach((input) => input.checked = true);
                setMonthsMode(form, 'all');
                const allToggle = form.querySelector('[data-months-toggle-all]');
                if (allToggle instanceof HTMLInputElement) {
                    allToggle.checked = true;
                    allToggle.indeterminate = false;
                }
            }

            if (currentRoute === 'app_suivi_centre_details') {
                updateCentreDetailsPrintAction(form);
            }

            await refreshDependentFilters(form, true, preserveCentreOnClear);
            await refreshResults(form);
        });
    }
}
