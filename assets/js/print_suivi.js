function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function nextFrame(count = 2) {
    for (let i = 0; i < count; i += 1) {
        // eslint-disable-next-line no-await-in-loop
        await new Promise((resolve) => requestAnimationFrame(resolve));
    }
}

function canvasToImage(canvas) {
    if (!(canvas instanceof HTMLCanvasElement)) return null;

    let dataUrl = null;
    try {
        dataUrl = canvas.toDataURL('image/png');
    } catch (e) {
        return null;
    }

    const rect = canvas.getBoundingClientRect();

    const img = document.createElement('img');
    img.src = dataUrl;
    img.alt = canvas.getAttribute('aria-label') || 'Graphique';
    img.className = 'print-canvas-image';

    // Keep the on-screen rendered size to avoid blurry prints / unexpected stretching.
    if (rect.width > 0) {
        img.style.width = `${Math.round(rect.width)}px`;
        img.style.height = 'auto';
        img.style.maxWidth = '100%';
    }

    return img;
}

function replaceCanvases() {
    const canvases = Array.from(document.querySelectorAll('canvas'));
    canvases.forEach((canvas) => {
        const img = canvasToImage(canvas);
        if (!img) return;

        // Keep the canvas in DOM (hidden) so any scripts relying on it don't crash later.
        canvas.style.display = 'none';
        canvas.insertAdjacentElement('afterend', img);
    });
}

function fitToWidth() {
    const targets = Array.from(document.querySelectorAll('[data-print-fit="1"]'));
    targets.forEach((el) => {
        if (!(el instanceof HTMLElement)) return;

        // Activity print uses font fitting to keep pagination sane (no zoom/transform).
        if (document.body?.classList?.contains('print-variant-activite')) {
            return;
        }

        const parent = el.parentElement;
        const available = parent?.clientWidth || document.documentElement.clientWidth || 0;
        const needed = el.scrollWidth || 0;

        if (!available || !needed) return;
        if (needed <= available + 1) return;

        const scale = Math.max(0.5, Math.min(1, available / needed));

        if (typeof CSS !== 'undefined' && typeof CSS.supports === 'function' && CSS.supports('zoom', '1')) {
            el.style.zoom = String(scale);
            el.style.transform = 'none';
        } else {
            el.style.transformOrigin = 'top left';
            el.style.transform = `scale(${scale})`;
        }
    });
}

function fitActivityTablesByFont() {
    if (!document.body?.classList?.contains('print-variant-activite')) return;

    const roots = Array.from(document.querySelectorAll('[data-print-fit="1"]'));
    roots.forEach((root) => {
        if (!(root instanceof HTMLElement)) return;
        const table = root.querySelector('table');
        if (!(table instanceof HTMLTableElement)) return;

        root.classList.remove('print-overflow-fallback');

        const parent = root.parentElement;
        const available = parent?.clientWidth || document.documentElement.clientWidth || 0;
        if (!available) return;

        const computed = window.getComputedStyle(table);
        const startPx = parseFloat(computed.fontSize || '8') || 8;
        const minPx = 5;

        // Use a small step so we don't degrade readability more than needed.
        let px = Math.min(10, Math.max(minPx, startPx));
        table.style.fontSize = `${px}px`;

        // Decrease until it fits (or we hit the minimum).
        // We also remove any zoom/transform that could have been set by earlier runs.
        root.style.zoom = '1';
        root.style.transform = 'none';

        // Force a reflow and check scroll width.
        // eslint-disable-next-line no-unused-expressions
        table.offsetWidth;

        let needed = root.scrollWidth || 0;
        if (!needed) return;

        while (needed > available + 1 && px > minPx) {
            px = Math.max(minPx, px - 0.25);
            table.style.fontSize = `${px}px`;
            // eslint-disable-next-line no-unused-expressions
            table.offsetWidth;
            needed = root.scrollWidth || 0;
        }

        // As a last resort, allow breaking long text to avoid overflow.
        if (needed > available + 1) {
            root.classList.add('print-overflow-fallback');
        }
    });
}

function stretchActivityTablesToWidth() {
    if (!document.body?.classList?.contains('print-variant-activite')) return;

    const roots = Array.from(document.querySelectorAll('[data-print-fit="1"]'));
    roots.forEach((root) => {
        if (!(root instanceof HTMLElement)) return;
        const table = root.querySelector('table');
        if (!(table instanceof HTMLTableElement)) return;

        const parent = root.parentElement;
        const available = parent?.clientWidth || document.documentElement.clientWidth || 0;
        if (!available) return;

        // Measure natural width with max-content first.
        table.style.width = 'max-content';
        table.style.maxWidth = 'none';
        // eslint-disable-next-line no-unused-expressions
        table.offsetWidth;

        let needed = root.scrollWidth || 0;
        if (!needed) return;

        if (needed <= available * 0.98) {
            table.style.width = '100%';
            table.style.maxWidth = '100%';
        } else {
            table.style.width = 'max-content';
            table.style.maxWidth = 'none';
        }
    });
}

function growActivityTablesFontToUseWidth() {
    if (!document.body?.classList?.contains('print-variant-activite')) return;

    const roots = Array.from(document.querySelectorAll('[data-print-fit="1"]'));
    roots.forEach((root) => {
        if (!(root instanceof HTMLElement)) return;
        const table = root.querySelector('table');
        if (!(table instanceof HTMLTableElement)) return;

        const parent = root.parentElement;
        const available = parent?.clientWidth || document.documentElement.clientWidth || 0;
        if (!available) return;

        const computed = window.getComputedStyle(table);
        let px = parseFloat(computed.fontSize || '8') || 8;
        const maxPx = 10.5;

        // Ensure width is allowed to expand (otherwise scrollWidth won't grow).
        table.style.width = '100%';
        table.style.maxWidth = '100%';

        // eslint-disable-next-line no-unused-expressions
        table.offsetWidth;

        let needed = root.scrollWidth || 0;
        if (!needed) return;

        // Increase font a bit if there is a lot of unused width.
        while (needed < available * 0.9 && px < maxPx) {
            px = Math.min(maxPx, px + 0.25);
            table.style.fontSize = `${px}px`;
            // eslint-disable-next-line no-unused-expressions
            table.offsetWidth;
            needed = root.scrollWidth || 0;
        }

        // If we overshot, step back once.
        if (needed > available + 1 && px > 5) {
            px = Math.max(5, px - 0.25);
            table.style.fontSize = `${px}px`;
        }
    });
}

function collectParamVariants(params, baseName) {
    const values = [];
    params.getAll(baseName).forEach((v) => values.push(v));
    params.getAll(`${baseName}[]`).forEach((v) => values.push(v));
    return values
        .map((v) => String(v || '').trim())
        .filter((v) => v !== '');
}

async function prepareAndPrint() {
    const isActivity = document.body?.classList?.contains('print-variant-activite');

    if (isActivity) {
        // Apply filters immediately to avoid flashing all columns.
        const table = document.querySelector('[data-activity-table]');
        if (table instanceof HTMLTableElement) {
            const {applyActivityTableColumnVisibility} = await import('./activity_table.js');
            const params = new URLSearchParams(window.location.search || '');
            const selectedTypes = new Set(collectParamVariants(params, 'type'));
            const selectedVehicles = new Set(collectParamVariants(params, 'vehicule'));
            applyActivityTableColumnVisibility(table, selectedTypes, selectedVehicles);
        }

        fitActivityTablesByFont();
        stretchActivityTablesToWidth();
        growActivityTablesFontToUseWidth();
        document.documentElement.classList.add('print-ready');

        await nextFrame(1);

        const autoPrint = document.body?.dataset?.autoPrint === '1';
        if (autoPrint) {
            window.print();
        }
        return;
    }

    // Let charts render (clients_pros.js draws on RAF and can re-render on resize).
    await nextFrame(3);
    await sleep(250);
    await nextFrame(2);

    replaceCanvases();
    fitToWidth();
    document.documentElement.classList.add('print-ready');

    await nextFrame(2);

    const autoPrint = document.body?.dataset?.autoPrint === '1';
    if (autoPrint) {
        window.print();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    prepareAndPrint();
});

// Some browsers recompute layout when entering print preview; re-apply activity fit right before printing.
window.addEventListener('beforeprint', () => {
    fitActivityTablesByFont();
    stretchActivityTablesToWidth();
    growActivityTablesFontToUseWidth();
});
