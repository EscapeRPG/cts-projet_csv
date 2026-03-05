/**
 * Toggles a dropdown filter and closes others.
 *
 * @param {HTMLElement} element
 * @returns {void}
 */
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

/**
 * Returns checked values for a checkbox group.
 *
 * @param {HTMLFormElement} form
 * @param {string} inputName
 * @returns {Set<string>}
 */
function getCheckedValues(form, inputName) {
    return new Set(Array.from(form.querySelectorAll(`input[name="${inputName}"]:checked`)).map((input) => input.value));
}

let resultsRequestController = null;
let refreshDebounceTimer = null;
let activeResultsRequestToken = 0;
let pendingSocieteChange = false;
let pendingCentreChange = false;
let mobileFiltersState = null;
const PANEL_FOCUS_SYMBOL = '\u{1F5D6}';
const PANEL_RESTORE_SYMBOL = '\u{1F5D7}';
const MOBILE_FILTERS_QUERY = '(max-width: 1000px)';

/**
 * Returns focusable result panels within a container.
 *
 * @param {HTMLElement} container
 * @returns {T[]}
 */
function getFocusPanels(container) {
    if (!(container instanceof HTMLElement)) return [];

    return Array.from(container.querySelectorAll(':scope > .stats-wrapper, :scope > .table-wrapper, :scope > .pro-stats-panel .graphique'));
}

/**
 * Returns top-level child panels for a container.
 *
 * @param {HTMLElement} container
 * @returns {Element[]}
 */
function getTopLevelPanels(container) {
    if (!(container instanceof HTMLElement)) return [];
    return Array.from(container.children).filter((el) => el instanceof HTMLElement);
}

/**
 * Resolves the top-level panel that contains a target panel.
 *
 * @param {HTMLElement} container
 * @param {HTMLElement} panel
 * @returns {HTMLElement|null}
 */
function getTopLevelPanel(container, panel) {
    if (!(container instanceof HTMLElement) || !(panel instanceof HTMLElement)) return null;

    let current = panel;
    while (current && current.parentElement !== container) {
        current = current.parentElement;
    }

    return current instanceof HTMLElement ? current : null;
}

/**
 * Enables isolated focus mode for a specific panel.
 *
 * @param {HTMLElement} container
 * @param {HTMLElement} panel
 * @returns {void}
 */
function setPanelFocus(container, panel) {
    if (!(container instanceof HTMLElement) || !(panel instanceof HTMLElement)) return;

    const topLevelPanels = getTopLevelPanels(container);
    const topLevelTarget = getTopLevelPanel(container, panel);
    if (!topLevelTarget) return;

    container.classList.add('panel-focus-mode');
    container.setAttribute('data-panel-focus', '1');

    topLevelPanels.forEach((current) => {
        const isTopLevelTarget = current === topLevelTarget;
        current.classList.toggle('panel-isolated', isTopLevelTarget);
        current.classList.toggle('panel-hidden', !isTopLevelTarget);

        if (!isTopLevelTarget) {
            current.classList.remove('panel-inner-focus-mode');
            current.querySelectorAll('.panel-inner-isolated').forEach((el) => el.classList.remove('panel-inner-isolated'));
            return;
        }

        if (panel !== topLevelTarget) {
            current.classList.add('panel-inner-focus-mode');
            current.querySelectorAll(':scope > .panel-inner-isolated').forEach((el) => {
                if (el !== panel) {
                    el.classList.remove('panel-inner-isolated');
                }
            });
            panel.classList.add('panel-inner-isolated');
        } else {
            current.classList.remove('panel-inner-focus-mode');
            current.querySelectorAll(':scope > .panel-inner-isolated').forEach((el) => el.classList.remove('panel-inner-isolated'));
        }
    });

    const tools = panel.querySelector(':scope > .panel-tools');
    if (tools) {
        const button = tools.querySelector('[data-panel-focus-toggle]');
        if (button) {
            button.textContent = PANEL_RESTORE_SYMBOL;
        }
    }

    document.dispatchEvent(new CustomEvent('suivi:panel-focus-changed'));
}

/**
 * Clears panel focus mode and restores default visibility.
 *
 * @param {HTMLElement} container
 * @param {boolean} [emitEvent=true]
 * @returns {void}
 */
function clearPanelFocus(container, emitEvent = true) {
    if (!(container instanceof HTMLElement)) return;

    const panels = getFocusPanels(container);
    const topLevelPanels = getTopLevelPanels(container);
    container.classList.remove('panel-focus-mode');
    container.removeAttribute('data-panel-focus');

    topLevelPanels.forEach((panel) => {
        panel.classList.remove('panel-isolated', 'panel-hidden', 'panel-inner-focus-mode');
    });
    container.querySelectorAll('.panel-inner-isolated').forEach((el) => el.classList.remove('panel-inner-isolated'));

    panels.forEach((panel) => {
        const tools = panel.querySelector(':scope > .panel-tools');
        if (!tools) return;

        const button = tools.querySelector('[data-panel-focus-toggle]');
        if (button) {
            button.textContent = PANEL_FOCUS_SYMBOL;
        }
    });

    if (emitEvent) {
        document.dispatchEvent(new CustomEvent('suivi:panel-focus-changed'));
    }
}

/**
 * Initializes panel toolbars and focus toggle buttons.
 *
 * @returns {void}
 */
function initPanelTools() {
    const containers = document.querySelectorAll('[data-ajax-results]');
    containers.forEach((container) => {
        if (!(container instanceof HTMLElement)) return;

        const panels = getFocusPanels(container);
        if (panels.length <= 1) {
            clearPanelFocus(container, false);
            panels.forEach((panel) => {
                if (!(panel instanceof HTMLElement)) return;
                const existingBar = panel.querySelector(':scope > .panel-tools');
                if (existingBar) {
                    existingBar.remove();
                }
                delete panel.dataset.panelToolsInit;
            });
            return;
        }

        panels.forEach((panel, index) => {
            if (!(panel instanceof HTMLElement)) return;
            if (panel.dataset.panelToolsInit === '1') return;
            panel.dataset.panelToolsInit = '1';

            const bar = document.createElement('div');
            bar.className = 'panel-tools';

            const title = document.createElement('span');
            title.className = 'panel-tools-title';
            title.textContent = extractPanelTitle(panel, index);
            bar.appendChild(title);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'panel-focus-btn';
            btn.setAttribute('data-panel-focus-toggle', '1');
            btn.textContent = PANEL_FOCUS_SYMBOL;
            btn.addEventListener('click', () => {
                const isFocused = container.getAttribute('data-panel-focus') === '1' &&
                    (panel.classList.contains('panel-isolated') || panel.classList.contains('panel-inner-isolated'));
                if (isFocused) {
                    clearPanelFocus(container);
                    return;
                }

                setPanelFocus(container, panel);
            });
            bar.appendChild(btn);

            panel.prepend(bar);
        });
    });
}

/**
 * Extracts a display title for a panel.
 *
 * @param {HTMLElement} panel
 * @param {number} index
 * @returns {string}
 */
function extractPanelTitle(panel, index) {
    if (!(panel instanceof HTMLElement)) return `Panneau ${index + 1}`;

    const panelHeading = panel.querySelector(':scope > .stats-panel > h2, :scope > h2');
    if (panelHeading instanceof HTMLElement) {
        const text = (panelHeading.textContent || '').trim();
        panelHeading.remove();
        return text || ``;
    }

    const canvas = panel.querySelector(':scope canvas[aria-label]');
    if (canvas instanceof HTMLCanvasElement) {
        const label = (canvas.getAttribute('aria-label') || '').trim();
        if (label !== '') return label;
    }

    return ``;
}

/**
 * Sets the loading state on AJAX results container.
 *
 * @param {boolean} isLoading
 * @returns {void}
 */
function setAjaxLoading(isLoading) {
    const container = document.querySelector('[data-ajax-results]');
    if (!container) return;

    container.classList.toggle('ajax-loading', isLoading);
    container.setAttribute('aria-busy', isLoading ? 'true' : 'false');
}

/**
 * Builds a query string from form fields.
 *
 * @param {HTMLFormElement} form
 * @returns {string}
 */
function buildFormQueryString(form) {
    const formData = new FormData(form);
    const params = new URLSearchParams();

    for (const [key, value] of formData.entries()) {
        params.append(key, String(value));
    }

    return params.toString();
}

/**
 * Refreshes results container via AJAX and updates browser URL.
 *
 * @param {HTMLFormElement} form
 * @returns {Promise<void>}
 */
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

/**
 * Clears active debounce timer.
 *
 * @returns {void}
 */
function clearRefreshDebounceTimer() {
    if (refreshDebounceTimer) {
        clearTimeout(refreshDebounceTimer);
        refreshDebounceTimer = null;
    }
}

/**
 * Schedules an asynchronous task with debounce behavior.
 *
 * @param {() => Promise<void>} task
 * @param {number} delay
 * @returns {Promise<void>}
 */
function scheduleDebouncedTask(task, delay) {
    clearRefreshDebounceTimer();

    return new Promise((resolve) => {
        refreshDebounceTimer = setTimeout(async () => {
            refreshDebounceTimer = null;
            await task();
            resolve();
        }, delay);
    });
}

/**
 * Schedules an AJAX results refresh.
 *
 * @param {HTMLFormElement} form
 * @param {number} [delay=300]
 * @returns {Promise<void>}
 */
function scheduleRefreshResults(form, delay = 300) {
    return scheduleDebouncedTask(() => refreshResults(form), delay);
}

/**
 * Schedules dependent filters refresh, then result refresh.
 *
 * @param {HTMLFormElement} form
 * @param {number} [delay=450]
 * @returns {Promise<void>}
 */
function scheduleFiltersRefresh(form, delay = 450) {
    return scheduleDebouncedTask(async () => {
        if (pendingSocieteChange) {
            await refreshDependentFilters(form, true, false);
        } else if (pendingCentreChange) {
            await refreshDependentFilters(form, false, true);
        }

        pendingSocieteChange = false;
        pendingCentreChange = false;

        await refreshResults(form);
    }, delay);
}

/**
 * Renders a checkbox list in a target container.
 *
 * @param {HTMLElement} container
 * @param {string} inputName
 * @param {Array<{value:string,label?:string,nom?:string,prenom?:string}>} values
 * @param {Set<string>} checkedValues
 * @param {(value:any) => string} labelBuilder
 * @returns {void}
 */
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

/**
 * Refreshes dependent center/controller filters from backend endpoint.
 *
 * @param {HTMLFormElement} form
 * @param {boolean} [updateCentres=true]
 * @param {boolean} [includeCentresFilter=true]
 * @returns {Promise<void>}
 */
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
        // Ignore network errors to keep the UI responsive.
    }
}

/**
 * Sets or clears hidden year input in filters form.
 *
 * @param {HTMLFormElement} form
 * @param {string|number|null|undefined} yearValue
 * @returns {void}
 */
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

/**
 * Opens the mobile filters drawer.
 *
 * @returns {void}
 */
function openMobileFiltersDrawer() {
    if (!mobileFiltersState) return;

    mobileFiltersState.drawer.removeAttribute('inert');
    mobileFiltersState.drawer.classList.add('is-open');
    mobileFiltersState.backdrop.classList.add('is-open');
    mobileFiltersState.toggle.setAttribute('aria-expanded', 'true');
    mobileFiltersState.drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mobile-filters-open');
}

/**
 * Closes the mobile filters drawer.
 *
 * @returns {void}
 */
function closeMobileFiltersDrawer() {
    if (!mobileFiltersState) return;

    if (mobileFiltersState.drawer.contains(document.activeElement)) {
        if (mobileFiltersState.toggle.isConnected) {
            mobileFiltersState.toggle.focus();
        }
    }

    mobileFiltersState.drawer.classList.remove('is-open');
    mobileFiltersState.backdrop.classList.remove('is-open');
    mobileFiltersState.toggle.setAttribute('aria-expanded', 'false');
    mobileFiltersState.drawer.setAttribute('aria-hidden', 'true');
    mobileFiltersState.drawer.setAttribute('inert', '');
    document.body.classList.remove('mobile-filters-open');
}

/**
 * Destroys mobile drawer state and restores the original filter DOM.
 *
 * @returns {void}
 */
function destroyMobileFiltersDrawer() {
    if (!mobileFiltersState) {
        document.body.classList.remove('mobile-filters-open');
        return;
    }

    closeMobileFiltersDrawer();
    restoreFiltersFromMobileDrawer();

    mobileFiltersState.mediaQuery.removeEventListener('change', applyMobileFiltersLayout);
    if (mobileFiltersState.keydownHandler) {
        document.removeEventListener('keydown', mobileFiltersState.keydownHandler);
    }

    if (mobileFiltersState.host.isConnected) {
        mobileFiltersState.host.remove();
    }

    mobileFiltersState = null;
}

/**
 * Moves year and side filters into the mobile drawer.
 *
 * @returns {void}
 */
function mountFiltersInMobileDrawer() {
    if (!mobileFiltersState || mobileFiltersState.isMounted) return;

    const {yearsFilter, sideNav, drawerContent} = mobileFiltersState;

    if (yearsFilter) {
        drawerContent.appendChild(yearsFilter);
    }
    drawerContent.appendChild(sideNav);

    mobileFiltersState.isMounted = true;
}

/**
 * Restores year and side filters to their original DOM locations.
 *
 * @returns {void}
 */
function restoreFiltersFromMobileDrawer() {
    if (!mobileFiltersState || !mobileFiltersState.isMounted) return;

    const {yearsFilter, sideNav, yearsPlaceholder, sideNavPlaceholder} = mobileFiltersState;

    if (yearsFilter) {
        yearsPlaceholder.parentNode?.insertBefore(yearsFilter, yearsPlaceholder);
    }
    sideNavPlaceholder.parentNode?.insertBefore(sideNav, sideNavPlaceholder);

    mobileFiltersState.isMounted = false;
}

/**
 * Applies mobile/desktop drawer mode based on viewport width.
 *
 * @returns {void}
 */
function applyMobileFiltersLayout() {
    if (!mobileFiltersState) return;

    if (mobileFiltersState.mediaQuery.matches) {
        mobileFiltersState.host.hidden = false;
        mobileFiltersState.host.classList.add('is-mobile');
        mountFiltersInMobileDrawer();
        return;
    }

    closeMobileFiltersDrawer();
    restoreFiltersFromMobileDrawer();
    mobileFiltersState.host.hidden = true;
    mobileFiltersState.host.classList.remove('is-mobile');
}

/**
 * Initializes mobile drawer behavior for filters on suivi pages.
 *
 * @returns {void}
 */
function initMobileFiltersDrawer() {
    if (mobileFiltersState && !mobileFiltersState.sideNav.isConnected) {
        destroyMobileFiltersDrawer();
    }

    if (mobileFiltersState) {
        applyMobileFiltersLayout();
        return;
    }

    const sideNav = document.querySelector('.sidenav');
    if (!(sideNav instanceof HTMLElement)) {
        destroyMobileFiltersDrawer();
        return;
    }

    const yearsFilter = document.querySelector('.annees-filter');
    const yearsElement = yearsFilter instanceof HTMLElement ? yearsFilter : null;

    const sideNavPlaceholder = document.createComment('mobile-filters-sidenav-placeholder');
    sideNav.parentNode?.insertBefore(sideNavPlaceholder, sideNav);

    let yearsPlaceholder = null;
    if (yearsElement) {
        yearsPlaceholder = document.createComment('mobile-filters-years-placeholder');
        yearsElement.parentNode?.insertBefore(yearsPlaceholder, yearsElement);
    }

    const suiviContainer = sideNav.closest('.suivi-container');
    const host = document.createElement('div');
    host.className = 'mobile-filters-host';
    host.setAttribute('data-mobile-filters-host', '1');
    host.hidden = true;

    if (suiviContainer && suiviContainer.parentNode) {
        suiviContainer.parentNode.insertBefore(host, suiviContainer);
    } else if (sideNav.parentNode) {
        sideNav.parentNode.insertBefore(host, sideNav);
    } else {
        return;
    }

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'mobile-filters-toggle';
    toggle.setAttribute('data-mobile-filters-toggle', '1');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-controls', 'mobile-filters-drawer');
    toggle.textContent = 'Filtres';

    const drawer = document.createElement('div');
    drawer.id = 'mobile-filters-drawer';
    drawer.className = 'mobile-filters-drawer';
    drawer.setAttribute('aria-hidden', 'true');
    drawer.setAttribute('inert', '');

    const drawerContent = document.createElement('div');
    drawerContent.className = 'mobile-filters-drawer-content';

    drawer.appendChild(drawerContent);

    const drawerFooter = document.createElement('div');
    drawerFooter.className = 'mobile-filters-drawer-footer';

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'mobile-filters-close';
    closeButton.setAttribute('aria-label', 'Fermer les filtres');
    closeButton.textContent = 'Fermer';

    drawerFooter.appendChild(closeButton);
    drawer.appendChild(drawerFooter);

    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'mobile-filters-backdrop';
    backdrop.setAttribute('aria-label', 'Fermer le panneau des filtres');

    host.appendChild(toggle);
    host.appendChild(drawer);
    host.appendChild(backdrop);

    const mediaQuery = window.matchMedia(MOBILE_FILTERS_QUERY);

    mobileFiltersState = {
        sideNav,
        yearsFilter: yearsElement,
        sideNavPlaceholder,
        yearsPlaceholder,
        host,
        toggle,
        drawer,
        drawerContent,
        backdrop,
        mediaQuery,
        isMounted: false,
    };

    toggle.addEventListener('click', () => {
        if (drawer.classList.contains('is-open')) {
            closeMobileFiltersDrawer();
            return;
        }

        openMobileFiltersDrawer();
    });
    closeButton.addEventListener('click', closeMobileFiltersDrawer);
    backdrop.addEventListener('click', closeMobileFiltersDrawer);

    const keydownHandler = (event) => {
        if (event.key === 'Escape') {
            closeMobileFiltersDrawer();
        }
    };
    document.addEventListener('keydown', keydownHandler);

    mobileFiltersState.keydownHandler = keydownHandler;

    mediaQuery.addEventListener('change', applyMobileFiltersLayout);
    applyMobileFiltersLayout();
}

/**
 * Binds year shortcut links to form updates and result refresh.
 *
 * @param {HTMLFormElement} form
 * @returns {void}
 */
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

/**
 * Initializes filters interactions and AJAX refresh bindings.
 *
 * @returns {void}
 */
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
            clearRefreshDebounceTimer();
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

/**
 * Bootstraps filters UI and panel tools.
 *
 * @returns {void}
 */
function bootstrapFiltersUi() {
    initMobileFiltersDrawer();
    init();
    initPanelTools();
}

document.addEventListener('DOMContentLoaded', bootstrapFiltersUi);
document.addEventListener('turbo:load', bootstrapFiltersUi);
document.addEventListener('turbo:before-cache', destroyMobileFiltersDrawer);
document.addEventListener('suivi:results-updated', initPanelTools);
