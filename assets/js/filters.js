function openFilter(element) {
    const openedFilters = document.querySelectorAll('.filter-open');

    openedFilters.forEach((el) => {
        if (el !== element) {
            el.classList.remove('filter-open');
        }
    });

    const filtersMenu = element.nextElementSibling;

    if (element.classList.contains('filter-open')) {
        element.classList.remove('filter-open');
        filtersMenu.classList.remove('filter-open');
    } else {
        element.classList.add('filter-open');
        filtersMenu.classList.add('filter-open');
    }
}

function getCheckedValues(form, inputName) {
    return new Set(Array.from(form.querySelectorAll(`input[name="${inputName}"]:checked`)).map((input) => input.value));
}

let resultsRequestController = null;
let refreshDebounceTimer = null;
let activeResultsRequestToken = 0;
let pendingSocieteChange = false;
let pendingCentreChange = false;

function setAjaxLoading(isLoading) {
    const container = document.querySelector('[data-ajax-results]');
    if (!container) return;

    container.classList.toggle('ajax-loading', isLoading);
    container.setAttribute('aria-busy', isLoading ? 'true' : 'false');
}

function buildFormQueryString(form) {
    const formData = new FormData(form);
    const params = new URLSearchParams();

    for (const [key, value] of formData.entries()) {
        params.append(key, String(value));
    }

    return params.toString();
}

async function refreshResults(form) {
    const currentContainer = document.querySelector('[data-ajax-results]');
    if (!currentContainer) return;
    const requestToken = ++activeResultsRequestToken;

    const queryString = buildFormQueryString(form);
    const path = window.location.pathname;
    const url = queryString ? `${path}?${queryString}` : path;

    if (resultsRequestController) {
        resultsRequestController.abort();
    }

    resultsRequestController = new AbortController();
    setAjaxLoading(true);

    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: resultsRequestController.signal,
        });
        if (!response.ok) return;

        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const nextContainer = doc.querySelector('[data-ajax-results]');
        if (!nextContainer) return;

        currentContainer.replaceWith(nextContainer);
        document.dispatchEvent(new CustomEvent('suivi:results-updated'));
        window.history.replaceState({}, '', url);
    } catch (e) {
        if (e.name !== 'AbortError') {
            // Ignore errors to avoid UI disruption
        }
    } finally {
        if (requestToken === activeResultsRequestToken) {
            resultsRequestController = null;
            setAjaxLoading(false);
        }
    }
}

function scheduleRefreshResults(form, delay = 300) {
    if (refreshDebounceTimer) {
        clearTimeout(refreshDebounceTimer);
    }

    return new Promise((resolve) => {
        refreshDebounceTimer = setTimeout(async () => {
            refreshDebounceTimer = null;
            await refreshResults(form);
            resolve();
        }, delay);
    });
}

function scheduleFiltersRefresh(form, delay = 450) {
    if (refreshDebounceTimer) {
        clearTimeout(refreshDebounceTimer);
    }

    return new Promise((resolve) => {
        refreshDebounceTimer = setTimeout(async () => {
            refreshDebounceTimer = null;

            if (pendingSocieteChange) {
                await refreshDependentFilters(form, true, false);
            } else if (pendingCentreChange) {
                await refreshDependentFilters(form, false, true);
            }

            pendingSocieteChange = false;
            pendingCentreChange = false;

            await refreshResults(form);
            resolve();
        }, delay);
    });
}

function renderCheckboxList(container, inputName, values, checkedValues, labelBuilder) {
    container.innerHTML = '';

    values.forEach((value) => {
        const label = document.createElement('label');
        const input = document.createElement('input');

        input.type = 'checkbox';
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

    const selectedSocietes = Array.from(form.querySelectorAll('input[name="societe[]"]:checked')).map((input) => input.value);
    const selectedCentresList = Array.from(form.querySelectorAll('input[name="centre[]"]:checked')).map((input) => input.value);
    const selectedCentres = new Set(selectedCentresList);
    const selectedControleurs = getCheckedValues(form, 'controleur[]');

    const params = new URLSearchParams();
    selectedSocietes.forEach((societe) => params.append('societe[]', societe));
    if (includeCentresFilter) {
        selectedCentresList.forEach((centre) => params.append('centre[]', centre));
    }

    try {
        const response = await fetch(`${endpoint}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });

        if (!response.ok) return;

        const data = await response.json();

        if (updateCentres) {
            const centresMenu = form.querySelector('[data-filter-list="centre"]');
            if (centresMenu) {
                renderCheckboxList(
                    centresMenu,
                    'centre[]',
                    data.centres.map((centre) => ({value: centre.agr_centre, label: centre.label})),
                    selectedCentres,
                    (centre) => centre.label
                );
            }
        }

        const controleursMenu = form.querySelector('[data-filter-list="controleur"]');
        if (controleursMenu) {
            renderCheckboxList(
                controleursMenu,
                'controleur[]',
                data.controleurs.map((controleur) => ({
                    value: String(controleur.id),
                    nom: controleur.nom,
                    prenom: controleur.prenom,
                })),
                selectedControleurs,
                (controleur) => `${controleur.nom} ${controleur.prenom}`
            );
        }
    } catch (e) {
        // Ignore les erreurs réseau pour ne pas casser l'UI
    }
}

function setYearValue(form, yearValue) {
    const normalized = String(yearValue ?? '').trim();
    const existing = form.querySelector('input[name="annee"]');

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
    hidden.name = 'annee';
    hidden.value = normalized;
    form.appendChild(hidden);
}

function bindYearFilter(form) {
    const yearLinks = document.querySelectorAll('.annees-filter a[data-year-value]');
    if (!yearLinks.length) return;

    yearLinks.forEach((link) => {
        link.addEventListener('click', async (event) => {
            event.preventDefault();

            const yearValue = link.dataset.yearValue ?? '';
            setYearValue(form, yearValue);

            yearLinks.forEach((item) => item.classList.remove('active'));
            link.classList.add('active');

            await scheduleRefreshResults(form, 120);
        });
    });
}

function init() {
    const filters = document.querySelector('.sidenav');
    if (!filters) return;
    if (filters.dataset.filtersInit === '1') return;
    filters.dataset.filtersInit = '1';

    const filtersBtn = filters.querySelectorAll('.dropdown-toggle');
    filtersBtn.forEach((el) => {
        el.addEventListener('click', () => openFilter(el));
    });

    const form = filters.querySelector('.filter-form');
    if (!form) return;

    bindYearFilter(form);

    form.addEventListener('change', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) return;

        if (target.name === 'societe[]') {
            pendingSocieteChange = true;
            pendingCentreChange = false;
            await scheduleFiltersRefresh(form, 450);
            return;
        }

        if (target.name === 'centre[]') {
            if (!pendingSocieteChange) {
                pendingCentreChange = true;
            }
            await scheduleFiltersRefresh(form, 450);
            return;
        }

        await scheduleFiltersRefresh(form, 450);
    });

    const clearButton = form.querySelector('[data-clear-filters]');
    if (clearButton) {
        clearButton.addEventListener('click', async () => {
            if (refreshDebounceTimer) {
                clearTimeout(refreshDebounceTimer);
                refreshDebounceTimer = null;
            }
            pendingSocieteChange = false;
            pendingCentreChange = false;

            const checkedBoxes = form.querySelectorAll('input[type="checkbox"]:checked');
            checkedBoxes.forEach((input) => {
                input.checked = false;
            });

            await refreshDependentFilters(form, true, false);
            await refreshResults(form);
        });
    }
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('turbo:load', init);
