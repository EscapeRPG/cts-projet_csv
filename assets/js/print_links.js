function normalizeBaseHref(a) {
    if (!(a instanceof HTMLAnchorElement)) return null;
    const fromData = a.dataset.printBaseHref;
    if (fromData && typeof fromData === 'string') return fromData;
    return a.getAttribute('href');
}

function withSearch(baseHref, search) {
    if (!search || search === '?') return baseHref;
    if (!baseHref) return baseHref;

    // If baseHref already has a query string, keep it and append current search.
    // In practice we use base paths without query, so this is mostly defensive.
    const hasQuery = baseHref.includes('?');
    if (!hasQuery) {
        return `${baseHref}${search.startsWith('?') ? search : `?${search}`}`;
    }

    const [path, existing] = baseHref.split('?', 2);
    const s1 = new URLSearchParams(existing || '');
    const s2 = new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
    for (const [k, v] of s2.entries()) {
        s1.set(k, v);
    }
    const merged = s1.toString();
    return merged ? `${path}?${merged}` : path;
}

document.addEventListener(
    'click',
    (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const a = target.closest('a[data-print-keep-filters="1"]');
        if (!(a instanceof HTMLAnchorElement)) return;

        const baseHref = normalizeBaseHref(a);
        if (!baseHref) return;

        a.href = withSearch(baseHref, window.location.search);
    },
    true
);

