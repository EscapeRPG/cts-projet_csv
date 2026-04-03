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

async function prepareAndPrint() {
    // Let charts render (clients_pros.js draws on RAF and can re-render on resize).
    await nextFrame(3);
    await sleep(250);
    await nextFrame(2);

    replaceCanvases();
    fitToWidth();
    document.documentElement.classList.add('print-ready');

    // Give the browser time to reflow/re-paginate after zoom/scale before printing.
    await nextFrame(2);
    await sleep(250);

    const autoPrint = document.body?.dataset?.autoPrint === '1';
    if (autoPrint) {
        window.print();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    prepareAndPrint();
});
