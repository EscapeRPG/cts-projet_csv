function debounce(fn, delayMs) {
    let t = null;
    return (...args) => {
        if (t) window.clearTimeout(t);
        t = window.setTimeout(() => fn(...args), delayMs);
    };
}

function normalizeParams(params) {
    const p = new URLSearchParams(params);
    const q = (p.get('q') || '').trim();
    if (q === '') {
        p.delete('q');
    } else {
        p.set('q', q);
    }
    return p;
}

function buildUrl(base, params) {
    const u = new URL(base, window.location.origin);
    u.search = normalizeParams(params).toString();
    return u.toString();
}

function initSearchInput(input) {
    if (input.dataset.listSearchInit === '1') return;
    input.dataset.listSearchInit = '1';

    const partialBase = input.dataset.listSearchPartialUrl;
    const pushBase = input.dataset.listSearchPushUrl;
    const resultsSelector = input.dataset.listSearchResults;

    if (!partialBase || !pushBase || !resultsSelector) return;

    const resultsEl = document.querySelector(resultsSelector);
    if (!resultsEl) return;

    const blockedMsgEl = input.parentElement?.querySelector('[data-list-search-blocked-message]') || null;
    const scopeEl = input.closest?.('.search-list') || input.parentElement;
    const extraParamsEls = scopeEl ? Array.from(scopeEl.querySelectorAll('[data-list-search-param="1"]')) : [];

    const isDirty = () => {
        // Only consider row save buttons (not delete forms etc).
        return Boolean(resultsEl.querySelector('button[data-row-save-button="1"]:not([disabled])'));
    };

    const syncDirtyState = () => {
        const dirty = isDirty();
        input.disabled = dirty;
        if (blockedMsgEl) {
            blockedMsgEl.hidden = !dirty;
        }
    };

    // Keep the initial state consistent (e.g. when browser restores form values).
    syncDirtyState();

    // If any row becomes dirty/clean, reflect it on the search input.
    resultsEl.addEventListener('input', syncDirtyState, true);
    resultsEl.addEventListener('change', syncDirtyState, true);
    resultsEl.addEventListener('click', syncDirtyState, true);
    document.addEventListener('cts:list:content-updated', (e) => {
        // Re-sync after AJAX swaps.
        if (e?.detail?.resultsSelector && e.detail.resultsSelector !== resultsSelector) return;
        syncDirtyState();
    });

    const fetchAndSwap = async (params) => {
        if (isDirty()) {
            syncDirtyState();
            return;
        }

        const partialUrl = buildUrl(partialBase, params);
        const res = await fetch(partialUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            // Fallback to full navigation on errors.
            window.location.href = buildUrl(pushBase, params);
            return;
        }

        const html = await res.text();
        resultsEl.innerHTML = html;

        const pushUrl = buildUrl(pushBase, params);
        window.history.replaceState({}, '', pushUrl);

        document.dispatchEvent(new CustomEvent('cts:list:content-updated', {
            detail: { container: resultsEl, resultsSelector }
        }));

        syncDirtyState();
    };

    const applyExtraParams = (params) => {
        extraParamsEls.forEach((el) => {
            if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement)) {
                return;
            }

            const name = el.getAttribute('name');
            if (!name) return;

            if (el instanceof HTMLInputElement && el.type === 'checkbox') {
                // Always send 1/0 so unchecked state can override server defaults.
                params.set(name, el.checked ? (el.value || '1') : '0');
                return;
            }

            const value = (el.value || '').trim();
            if (value === '') {
                params.delete(name);
            } else {
                params.set(name, value);
            }
        });
    };

    const onInput = debounce(() => {
        const params = new URLSearchParams(window.location.search);
        const q = (input.value || '').trim();
        if (q === '') {
            params.delete('q');
        } else {
            params.set('q', q);
        }
        applyExtraParams(params);
        // Reset page each time the query changes (only when pagination is present/used).
        if (params.has('page') || resultsEl.querySelector('.pagination-nav')) {
            params.set('page', '1');
        }
        fetchAndSwap(params);
    }, 500);

    input.addEventListener('input', onInput);

    extraParamsEls.forEach((el) => {
        el.addEventListener('change', () => {
            const params = new URLSearchParams(window.location.search);
            const q = (input.value || '').trim();
            if (q === '') {
                params.delete('q');
            } else {
                params.set('q', q);
            }
            applyExtraParams(params);
            if (params.has('page') || resultsEl.querySelector('.pagination-nav')) {
                params.set('page', '1');
            }
            fetchAndSwap(params);
        });
    });

    // AJAX pagination.
    resultsEl.addEventListener('click', (ev) => {
        const a = ev.target?.closest?.('a');
        if (!a) return;
        if (!a.closest('.pagination-nav')) return;

        const href = a.getAttribute('href');
        if (!href) return;

        ev.preventDefault();

        const url = new URL(href, window.location.origin);
        fetchAndSwap(url.searchParams);
    });
}

function init() {
    const inputs = document.querySelectorAll('input[data-list-search-input="1"]');
    inputs.forEach((input) => initSearchInput(input));
}

document.addEventListener('turbo:load', init);
document.addEventListener('DOMContentLoaded', init);
