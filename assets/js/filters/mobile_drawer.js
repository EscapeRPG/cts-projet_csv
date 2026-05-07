const MOBILE_FILTERS_QUERY = '(max-width: 1280px)';

let mobileFiltersState = null;

function applyMobileFiltersLayout() {
    if (!mobileFiltersState) return;
    const {
        drawer,
        toggle,
        mediaQuery,
        sidenav,
        placeholder,
        content,
        yearFilter,
        yearPlaceholder,
        yearHost,
    } = mobileFiltersState;

    if (!mediaQuery.matches) {
        // Desktop: ensure filters stay in their original layout position.
        drawer.classList.remove('is-mobile');
        toggle.setAttribute('hidden', '');

        // If the sidenav was moved into the drawer, move it back before the placeholder.
        if (sidenav instanceof HTMLElement && placeholder instanceof HTMLElement && placeholder.parentElement) {
            if (sidenav.parentElement !== placeholder.parentElement || sidenav.nextElementSibling !== placeholder) {
                placeholder.parentElement.insertBefore(sidenav, placeholder);
            }
        }

        // Also ensure drawer is closed and inert on desktop.
        closeMobileFiltersDrawer();

        // Put the year filter back above the content (desktop layout).
        if (yearFilter instanceof HTMLElement && yearPlaceholder instanceof HTMLElement && yearPlaceholder.parentElement) {
            if (yearFilter.parentElement !== yearPlaceholder.parentElement || yearFilter.nextElementSibling !== yearPlaceholder) {
                yearPlaceholder.parentElement.insertBefore(yearFilter, yearPlaceholder);
            }
        }
        return;
    }

    // Mobile: move filters into drawer content.
    drawer.classList.add('is-mobile');
    toggle.removeAttribute('hidden');
    if (sidenav instanceof HTMLElement && content instanceof HTMLElement && sidenav.parentElement !== content) {
        content.appendChild(sidenav);
    }

    // Mobile: move year filter into drawer (so it doesn't stay visible above the table).
    if (yearFilter instanceof HTMLElement && yearHost instanceof HTMLElement && yearFilter.parentElement !== yearHost) {
        yearHost.appendChild(yearFilter);
    }
}

function closeMobileFiltersDrawer() {
    if (!mobileFiltersState) return;

    mobileFiltersState.drawer.setAttribute('inert', '');
    mobileFiltersState.drawer.classList.remove('is-open');
    mobileFiltersState.backdrop.classList.remove('is-open');
    mobileFiltersState.toggle.setAttribute('aria-expanded', 'false');
    mobileFiltersState.drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mobile-filters-open');
}

function openMobileFiltersDrawer() {
    if (!mobileFiltersState) return;

    mobileFiltersState.drawer.removeAttribute('inert');
    mobileFiltersState.drawer.classList.add('is-open');
    mobileFiltersState.backdrop.classList.add('is-open');
    mobileFiltersState.toggle.setAttribute('aria-expanded', 'true');
    mobileFiltersState.drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mobile-filters-open');
}

function initMobileFiltersDrawerDom() {
    const sidenav = document.querySelector('.sidenav');
    if (!(sidenav instanceof HTMLElement)) return null;

    const existing = document.querySelector('[data-mobile-filters-drawer="1"]');
    if (existing instanceof HTMLElement) {
        // Drawer already exists (Turbo cache). Rebuild state by locating placeholder/content.
        const placeholder = document.querySelector('[data-mobile-filters-placeholder="1"]');
        const content = existing.querySelector('[data-mobile-filters-content="1"]');
        const yearFilter = document.querySelector('.annees-filter');
        const yearPlaceholder = document.querySelector('[data-mobile-year-placeholder="1"]');
        const yearHost = existing.querySelector('[data-mobile-year-host="1"]');
        return {drawer: existing, sidenav, placeholder, content, yearFilter, yearPlaceholder, yearHost};
    }

    // Placeholder marks where the sidenav should be on desktop.
    const placeholder = document.createElement('div');
    placeholder.setAttribute('data-mobile-filters-placeholder', '1');
    // Keep it layout-neutral.
    placeholder.style.display = 'none';
    sidenav.insertAdjacentElement('afterend', placeholder);

    const drawer = document.createElement('div');
    drawer.className = 'mobile-filters-drawer';
    drawer.setAttribute('data-mobile-filters-drawer', '1');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.setAttribute('inert', '');

    const header = document.createElement('div');
    header.className = 'mobile-filters-header';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'mobile-filters-close';
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', closeMobileFiltersDrawer);
    header.appendChild(closeBtn);

    drawer.appendChild(header);

    // Host for year filter (moved in/out depending on layout).
    const yearHost = document.createElement('div');
    yearHost.setAttribute('data-mobile-year-host', '1');
    drawer.appendChild(yearHost);

    const content = document.createElement('div');
    content.className = 'mobile-filters-content';
    content.setAttribute('data-mobile-filters-content', '1');
    drawer.appendChild(content);

    document.body.appendChild(drawer);

    // Year filter placeholder: keep its desktop position cleanly.
    const yearFilter = document.querySelector('.annees-filter');
    let yearPlaceholder = document.querySelector('[data-mobile-year-placeholder="1"]');
    if (yearFilter instanceof HTMLElement && !(yearPlaceholder instanceof HTMLElement)) {
        yearPlaceholder = document.createElement('div');
        yearPlaceholder.setAttribute('data-mobile-year-placeholder', '1');
        yearPlaceholder.style.display = 'none';
        yearFilter.insertAdjacentElement('afterend', yearPlaceholder);
    }

    return {drawer, sidenav, placeholder, content, yearFilter, yearPlaceholder, yearHost};
}

export function destroyMobileFiltersDrawer() {
    if (!mobileFiltersState) return;

    try {
        mobileFiltersState.mediaQuery.removeEventListener('change', applyMobileFiltersLayout);
    } catch (e) {
        // ignore
    }

    if (mobileFiltersState.keydownHandler) {
        document.removeEventListener('keydown', mobileFiltersState.keydownHandler);
    }

    // Ensure sidenav is back in place before Turbo caches the page.
    try {
        const {sidenav, placeholder, yearFilter, yearPlaceholder} = mobileFiltersState;
        if (sidenav instanceof HTMLElement && placeholder instanceof HTMLElement && placeholder.parentElement) {
            placeholder.parentElement.insertBefore(sidenav, placeholder);
        }
        if (yearFilter instanceof HTMLElement && yearPlaceholder instanceof HTMLElement && yearPlaceholder.parentElement) {
            yearPlaceholder.parentElement.insertBefore(yearFilter, yearPlaceholder);
        }
        closeMobileFiltersDrawer();
    } catch (e) {
        // ignore
    }

    mobileFiltersState.backdrop?.remove();
    mobileFiltersState.toggle?.remove();

    // Keep drawer DOM: turbo cache will restore it; but reset state.
    mobileFiltersState = null;
}

export function initMobileFiltersDrawer() {
    const mediaQuery = window.matchMedia(MOBILE_FILTERS_QUERY);
    const dom = initMobileFiltersDrawerDom();
    if (!dom) return;
    const {drawer, sidenav, placeholder, content, yearFilter, yearPlaceholder, yearHost} = dom;
    if (!(drawer instanceof HTMLElement)) return;

    if (mobileFiltersState) return;

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'mobile-filters-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.textContent = 'Filtres';
    toggle.addEventListener('click', openMobileFiltersDrawer);
    toggle.setAttribute('hidden', '');
    document.body.appendChild(toggle);

    const backdrop = document.createElement('div');
    backdrop.className = 'mobile-filters-backdrop';
    backdrop.addEventListener('click', closeMobileFiltersDrawer);
    document.body.appendChild(backdrop);

    const keydownHandler = (event) => {
        if (event.key === 'Escape') {
            closeMobileFiltersDrawer();
        }
    };
    document.addEventListener('keydown', keydownHandler);

    mobileFiltersState = {
        drawer,
        toggle,
        backdrop,
        mediaQuery,
        keydownHandler,
        sidenav,
        placeholder,
        content,
        yearFilter,
        yearPlaceholder,
        yearHost,
    };

    mediaQuery.addEventListener('change', applyMobileFiltersLayout);
    applyMobileFiltersLayout();
}
