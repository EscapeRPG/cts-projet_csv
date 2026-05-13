function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function positionOverlay(anchor, overlay) {
    // Defer measurement to the next frame to ensure layout is up to date after portal/DOM changes.
    requestAnimationFrame(() => {
        const rect = anchor.getBoundingClientRect();
        const vw = window.innerWidth || document.documentElement.clientWidth || 0;
        const vh = window.innerHeight || document.documentElement.clientHeight || 0;
        const gap = 6;
        const margin = 8;

        const overlayRect = overlay.getBoundingClientRect();
        const oh = overlayRect.height || 0;
        const ow = overlayRect.width || 0;

        const preferBelow = rect.top < (vh * 0.5);
        const belowTop = rect.bottom + gap;
        const aboveTop = rect.top - gap - oh;

        const belowSpace = vh - rect.bottom;
        const aboveSpace = rect.top;

        const belowFits = (belowTop + oh) <= (vh - margin);
        const aboveFits = aboveTop >= margin;

        let top;
        if (preferBelow) {
            if (belowFits) top = belowTop;
            else if (aboveFits) top = aboveTop;
            else top = (belowSpace >= aboveSpace) ? belowTop : aboveTop;
        } else {
            if (aboveFits) top = aboveTop;
            else if (belowFits) top = belowTop;
            else top = (aboveSpace >= belowSpace) ? aboveTop : belowTop;
        }

        top = clamp(top, margin, Math.max(margin, vh - oh - margin));

        let left = rect.left;
        left = clamp(left, margin, Math.max(margin, vw - ow - margin));

        overlay.style.setProperty('position', 'fixed', 'important');
        overlay.style.setProperty('top', `${top}px`, 'important');
        overlay.style.setProperty('left', `${left}px`, 'important');
        overlay.style.setProperty('right', 'auto', 'important');
        overlay.style.setProperty('width', 'max-content', 'important');
        overlay.style.setProperty('z-index', '9999', 'important');
    });
}

function enhanceHoverOverlay(cell) {
    if (!(cell instanceof HTMLElement)) return;
    if (cell.dataset.userOverlayInit === '1') return;
    cell.dataset.userOverlayInit = '1';

    const overlay = cell.querySelector('.user-overlay');
    if (!(overlay instanceof HTMLElement)) return;

    const originalParent = overlay.parentNode;
    const originalNextSibling = overlay.nextSibling;

    let isOpen = false;
    let closeTimer = null;

    const restoreOverlay = () => {
        if (!originalParent) return;
        if (originalNextSibling && originalNextSibling.parentNode === originalParent) {
            originalParent.insertBefore(overlay, originalNextSibling);
        } else {
            originalParent.appendChild(overlay);
        }
        overlay.style.removeProperty('position');
        overlay.style.removeProperty('top');
        overlay.style.removeProperty('left');
        overlay.style.removeProperty('right');
        overlay.style.removeProperty('width');
        overlay.style.removeProperty('z-index');
    };

    const open = () => {
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        if (isOpen) {
            positionOverlay(cell, overlay);
            return;
        }
        isOpen = true;
        document.body.appendChild(overlay);
        overlay.classList.add('is-open');
        positionOverlay(cell, overlay);
    };

    const close = () => {
        if (!isOpen) return;
        isOpen = false;
        overlay.classList.remove('is-open');
        restoreOverlay();
    };

    const scheduleClose = () => {
        if (closeTimer) clearTimeout(closeTimer);
        closeTimer = setTimeout(() => close(), 120);
    };

    cell.addEventListener('pointerenter', open);
    cell.addEventListener('pointerleave', scheduleClose);
    overlay.addEventListener('pointerenter', () => {
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
    });
    overlay.addEventListener('pointerleave', scheduleClose);

    // Close on outside interactions and keep position aligned on scroll/resize.
    const closeOnOutsidePointerDown = (event) => {
        const target = event.target;
        if (!(target instanceof Node)) return;
        if (cell.contains(target) || overlay.contains(target)) return;
        close();
    };
    document.addEventListener('pointerdown', closeOnOutsidePointerDown, true);
    document.addEventListener('click', closeOnOutsidePointerDown, true);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });

    const repositionIfOpen = () => {
        if (isOpen) positionOverlay(cell, overlay);
    };
    window.addEventListener('scroll', repositionIfOpen, true);
    window.addEventListener('resize', repositionIfOpen);
}

function init(root = document) {
    root.querySelectorAll('td.hoverable').forEach((cell) => enhanceHoverOverlay(cell));
}

document.addEventListener('DOMContentLoaded', () => init(document));
document.addEventListener('turbo:load', () => init(document));
document.addEventListener('cts:list:content-updated', (e) => {
    const root = e?.detail?.container ?? document;
    init(root);
});

