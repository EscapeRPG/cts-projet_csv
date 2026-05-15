let resultsRequestController = null;
let refreshDebounceTimer = null;
let activeResultsRequestToken = 0;

/**
 * Sets the loading state on AJAX results container.
 *
 * @param {boolean} isLoading
 * @returns {void}
 */
export function setAjaxLoading(isLoading) {
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
export function buildFormQueryString(form) {
    const formData = new FormData(form);
    const params = new URLSearchParams();

    const monthMode = String(formData.get('mois_mode') ?? '').trim();

    for (const [key, value] of formData.entries()) {
        if ((monthMode === 'all' || monthMode === 'ytd') && key === 'mois[]') {
            continue;
        }
        params.append(key, String(value));
    }

    return params.toString();
}

function applyPreservedQueryParams(form, params) {
    if (!(params instanceof URLSearchParams)) return params;
    if (!form || !(form instanceof HTMLFormElement)) return params;

    const preserveRaw = String(form.dataset.preserveQueryParams ?? '').trim();
    if (!preserveRaw) return params;

    const keys = preserveRaw.split(',').map((s) => s.trim()).filter(Boolean);
    if (!keys.length) return params;

    const current = new URLSearchParams(window.location.search.startsWith('?') ? window.location.search.slice(1) : window.location.search);
    keys.forEach((key) => {
        if (!key) return;
        if (params.has(key)) return;
        if (!current.has(key)) return;
        params.set(key, current.get(key) ?? '');
    });

    return params;
}

function buildUrlFromForm(form) {
    const queryString = buildFormQueryString(form);
    const params = applyPreservedQueryParams(form, new URLSearchParams(queryString));
    const path = window.location.pathname;
    const nextQuery = params.toString();
    return nextQuery ? `${path}?${nextQuery}` : path;
}

/**
 * Updates browser URL from current form state without fetching server results.
 *
 * @param {HTMLFormElement} form
 * @returns {void}
 */
export function syncUrlWithForm(form) {
    window.history.replaceState({}, '', buildUrlFromForm(form));
}

/**
 * Refreshes results container via AJAX and updates browser URL.
 *
 * @param {HTMLFormElement} form
 * @returns {Promise<void>}
 */
export async function refreshResults(form) {
    const currentContainer = document.querySelector('[data-ajax-results]');
    if (!currentContainer) return;
    const requestToken = ++activeResultsRequestToken;

    const url = buildUrlFromForm(form);

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
            // ignore
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
export function clearRefreshDebounceTimer() {
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
export function scheduleDebouncedTask(task, delay) {
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
export function scheduleRefreshResults(form, delay = 300) {
    return scheduleDebouncedTask(() => refreshResults(form), delay);
}
