const PANEL_FOCUS_SYMBOL = '\u{1F5D6}';
const PANEL_RESTORE_SYMBOL = '\u{1F5D7}';
const PANEL_FOCUS_STORAGE_PREFIX = 'suivi.panel_focus.';

/**
 * Returns focusable result panels within a container.
 *
 * @param {HTMLElement} container
 * @returns {HTMLElement[]}
 */
function getFocusPanels(container) {
    if (!(container instanceof HTMLElement)) return [];

    const panels = container.querySelectorAll(':scope > .stats-wrapper, :scope > .table-wrapper, :scope > .pro-stats-panel .graphique, [data-panel-focus-target="1"]');

    return Array.from(new Set(Array.from(panels)))
        .filter((el) => el instanceof HTMLElement);
}

/**
 * Returns top-level child panels for a container.
 *
 * @param {HTMLElement} container
 * @returns {HTMLElement[]}
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

function getPanelFocusStorageKey() {
    // Keep per-page state. Query params change often due to filters and should not create new entries.
    const path = window.location.pathname || '';
    return PANEL_FOCUS_STORAGE_PREFIX + path;
}

function persistPanelFocus(container) {
    if (!(container instanceof HTMLElement)) return;

    try {
        const key = getPanelFocusStorageKey();
        if (container.getAttribute('data-panel-focus') !== '1') {
            sessionStorage.removeItem(key);
            return;
        }

        const panels = getFocusPanels(container);
        const focusedIndex = panels.findIndex((panel) =>
            panel instanceof HTMLElement
            && (panel.classList.contains('panel-isolated') || panel.classList.contains('panel-inner-isolated'))
        );

        if (focusedIndex < 0) {
            sessionStorage.removeItem(key);
            return;
        }

        sessionStorage.setItem(key, JSON.stringify({index: focusedIndex}));
    } catch (e) {
        // Ignore storage failures (private mode, quota, etc.).
    }
}

function restorePanelFocus(container) {
    if (!(container instanceof HTMLElement)) return;

    try {
        const key = getPanelFocusStorageKey();
        const raw = sessionStorage.getItem(key);
        if (!raw) return;

        const data = JSON.parse(raw);
        const index = Number(data && data.index);
        if (!Number.isFinite(index) || index < 0) return;

        const panels = getFocusPanels(container).filter((panel) => panel instanceof HTMLElement);
        const target = panels[index];
        if (!(target instanceof HTMLElement)) return;

        const alreadyFocused = container.getAttribute('data-panel-focus') === '1' &&
            (target.classList.contains('panel-isolated') || target.classList.contains('panel-inner-isolated'));
        if (alreadyFocused) return;

        setPanelFocus(container, target);
    } catch (e) {
        // Ignore parse/storage failures.
    }
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
            current.querySelectorAll('.panel-inner-focus-ancestor').forEach((el) => el.classList.remove('panel-inner-focus-ancestor'));
            return;
        }

        current.querySelectorAll('.panel-inner-focus-ancestor').forEach((el) => el.classList.remove('panel-inner-focus-ancestor'));
        if (panel !== topLevelTarget) {
            current.classList.add('panel-inner-focus-mode');
            current.querySelectorAll('.panel-inner-isolated').forEach((el) => {
                if (el !== panel) {
                    el.classList.remove('panel-inner-isolated');
                }
            });

            let ancestor = panel.parentElement;
            while (ancestor instanceof HTMLElement && ancestor !== current) {
                ancestor.classList.add('panel-inner-focus-ancestor');
                ancestor = ancestor.parentElement;
            }

            panel.classList.add('panel-inner-isolated');
        } else {
            current.classList.remove('panel-inner-focus-mode');
            current.querySelectorAll('.panel-inner-isolated').forEach((el) => el.classList.remove('panel-inner-isolated'));
        }
    });

    const tools = panel.querySelector(':scope > .panel-tools');
    if (tools) {
        const button = tools.querySelector('[data-panel-focus-toggle]');
        if (button) {
            button.textContent = PANEL_RESTORE_SYMBOL;
        }
    }

    persistPanelFocus(container);
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
    container.querySelectorAll('.panel-inner-focus-ancestor').forEach((el) => el.classList.remove('panel-inner-focus-ancestor'));

    panels.forEach((panel) => {
        const tools = panel.querySelector(':scope > .panel-tools');
        if (!tools) return;

        const button = tools.querySelector('[data-panel-focus-toggle]');
        if (button) {
            button.textContent = PANEL_FOCUS_SYMBOL;
        }
    });

    persistPanelFocus(container);
    if (emitEvent) {
        document.dispatchEvent(new CustomEvent('suivi:panel-focus-changed'));
    }
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
 * Initializes panel toolbars and focus toggle buttons.
 *
 * @returns {void}
 */
export function initPanelTools() {
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

        // Re-apply persisted focus after AJAX refresh replaces the results container.
        restorePanelFocus(container);
    });
}
