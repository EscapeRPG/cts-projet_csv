import {getDocument, GlobalWorkerOptions} from "pdfjs-dist";

const pdfCache = new Map(); // url -> Promise<PDFDocumentProxy>
const canvasRenderState = new WeakMap(); // HTMLCanvasElement -> {chain: Promise<void>, task: any}

function getCanvasState(canvas) {
    const existing = canvasRenderState.get(canvas);
    if (existing) return existing;
    const state = {chain: Promise.resolve(), task: null};
    canvasRenderState.set(canvas, state);
    return state;
}

function cancelCanvasRender(canvas) {
    const state = getCanvasState(canvas);
    if (state.task && typeof state.task.cancel === 'function') {
        try { state.task.cancel(); } catch (e) { /* ignore */ }
    }
}

function enqueueCanvasRender(canvas, job) {
    const state = getCanvasState(canvas);
    // Cancel any in-flight render ASAP; job will run after the chain settles.
    cancelCanvasRender(canvas);
    state.chain = state.chain
        .catch(() => {})
        .then(job)
        .catch(() => {});
    return state.chain;
}

function ensureWorkerConfigured(workerUrl) {
    if (GlobalWorkerOptions.workerSrc) return;
    const normalized = String(workerUrl ?? '').trim();
    if (normalized) {
        GlobalWorkerOptions.workerSrc = normalized;
    }
}

async function loadPdf(pdfUrl) {
    const url = String(pdfUrl ?? '').trim();
    if (!url) throw new Error('Missing PDF URL');
    if (!pdfCache.has(url)) {
        pdfCache.set(url, getDocument({url, withCredentials: true}).promise);
    }
    return await pdfCache.get(url);
}

async function renderPdfPageToCanvas({pdfUrl, pageNumber, canvas, maxCssWidth, maxCssHeight, workerUrl, fitMode = 'contain'}) {
    if (!pdfUrl || !pageNumber || !(canvas instanceof HTMLCanvasElement)) return;

    ensureWorkerConfigured(workerUrl);

    return enqueueCanvasRender(canvas, async () => {
        let pdf;
        try {
            pdf = await loadPdf(pdfUrl);
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error('Failed to load organigram PDF', {pdfUrl, e});
            return;
        }

        const page = await pdf.getPage(pageNumber);

        const rotation = page.rotate || 0;
        const unscaledViewport = page.getViewport({scale: 1, rotation});

        const fit = String(fitMode || 'contain');
        const scale = fit === 'fitWidth'
            ? (maxCssWidth / unscaledViewport.width)
            : Math.min(
                maxCssWidth / unscaledViewport.width,
                maxCssHeight / unscaledViewport.height
            );

        const viewport = page.getViewport({scale: scale > 0 ? scale : 1, rotation});
        const outputScale = Math.min(2, Math.max(1, window.devicePixelRatio || 1));

        const context = canvas.getContext('2d', {alpha: true});
        if (!context) return;

        canvas.width = Math.floor(viewport.width * outputScale);
        canvas.height = Math.floor(viewport.height * outputScale);
        // Let CSS do the responsive clamping while preserving aspect ratio.
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = 'auto';
        canvas.style.maxWidth = '100%';
        canvas.style.maxHeight = `${Math.floor(maxCssHeight)}px`;
        canvas.style.aspectRatio = `${viewport.width} / ${viewport.height}`;
        canvas.style.background = 'white';

        // Make sure the canvas isn't affected by any CSS transforms.
        canvas.style.transform = 'none';

        // Reset transform after resizing (resizing resets the context state, but be explicit).
        context.setTransform(outputScale, 0, 0, outputScale, 0, 0);
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, viewport.width, viewport.height);

        const state = getCanvasState(canvas);
        const task = page.render({canvasContext: context, viewport});
        state.task = task;
        try {
            await task.promise;
        } catch (e) {
            if (e?.name !== 'RenderingCancelledException') {
                // eslint-disable-next-line no-console
                console.error('PDF render failed', e);
            }
        } finally {
            if (state.task === task) state.task = null;
        }
    });
}

async function renderSinglePage({pdfUrl, pageNumber, canvas, container}) {
    if (!pdfUrl || !pageNumber || !(canvas instanceof HTMLCanvasElement)) return;

    const workerUrl = String(container?.dataset?.pdfWorkerUrl ?? '').trim();

    const containerRect = container?.getBoundingClientRect?.() ?? null;
    const pageLayout = document.querySelector('.organigram-container');
    const layoutRect = pageLayout instanceof HTMLElement ? pageLayout.getBoundingClientRect() : null;
    const sidenav = document.querySelector('.organigram-container .sidenav');
    const sidenavW = sidenav instanceof HTMLElement ? sidenav.getBoundingClientRect().width : 0;
    const availableLayoutWidth = layoutRect?.width ? Math.max(0, layoutRect.width - sidenavW - 24) : 0;
    const measuredWidth = containerRect?.width ? Math.max(0, containerRect.width) : 0;
    const fallbackWidth = Math.max(320, window.innerWidth - sidenavW - 24);
    const maxCssWidth = Math.max(
        320,
        measuredWidth >= 200 ? measuredWidth : 0,
        availableLayoutWidth >= 200 ? availableLayoutWidth : 0,
        fallbackWidth
    );
    const maxCssHeight = window.innerHeight * 0.8;

    await renderPdfPageToCanvas({
        pdfUrl,
        pageNumber,
        canvas,
        maxCssWidth,
        maxCssHeight,
        workerUrl,
        fitMode: 'contain',
    });
}

function debounce(fn, delay) {
    let t = null;
    return (...args) => {
        if (t) clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
    };
}

function getOrCreateFullscreenModal() {
    let overlay = document.querySelector('[data-organigram-fullscreen-overlay="1"]');
    if (overlay instanceof HTMLElement) {
        const img = overlay.querySelector('img[data-organigram-fullscreen-img="1"]');
        return {overlay, img};
    }

    overlay = document.createElement('div');
    overlay.className = 'modal-overlay organigram-fullscreen-overlay';
    overlay.setAttribute('data-organigram-fullscreen-overlay', '1');
    overlay.setAttribute('aria-hidden', 'true');

    const modal = document.createElement('div');
    modal.className = 'organigram-fullscreen-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');

    const header = document.createElement('div');
    header.className = 'organigram-fullscreen-header';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'confirm-btn organigram-fullscreen-close';
    closeBtn.textContent = 'X';
    header.appendChild(closeBtn);

    modal.appendChild(header);

    const img = document.createElement('img');
    img.setAttribute('data-organigram-fullscreen-img', '1');
    img.className = 'organigram-fullscreen-img';
    img.alt = 'Organigramme';
    modal.appendChild(img);

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    const close = () => {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('organigram-fullscreen-open');
        if (img instanceof HTMLImageElement) img.src = '';
    };

    closeBtn.addEventListener('click', close);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('is-visible')) close();
    });

    return {overlay, img};
}

async function initOrganigramView() {
    const root = document.querySelector('[data-organigram-view="1"]');
    if (!(root instanceof HTMLElement)) return;
    // Turbo can restore the page with a blank canvas; always re-render.
    root.dataset.organigramInit = '1';

    const pdfUrl = String(root.dataset.pdfUrl ?? '').trim();
    const pageNumber = Number(String(root.dataset.page ?? '').trim());
    if (!pdfUrl || !Number.isFinite(pageNumber) || pageNumber <= 0) return;

    const canvas = root.querySelector('[data-organigram-canvas="1"]');
    if (!(canvas instanceof HTMLCanvasElement)) return;

    const doRender = async () => {
        try {
            await renderSinglePage({pdfUrl, pageNumber, canvas, container: root});
        } catch (e) {
            // ignore
        }
    };

    const schedule = (fn) => new Promise((r) => requestAnimationFrame(() => r(fn())));

    // Wait a couple of frames so layout (especially width) is stable under Turbo + loader transitions.
    await schedule(() => {});
    await schedule(() => {});
    await doRender();

    // Avoid a second automatic re-render: if layout is still settling, ResizeObserver will catch it.
    window.addEventListener('resize', debounce(doRender, 250));

    // Re-render when the container width changes. Debounce hard to avoid "flash then different size".
    if ('ResizeObserver' in window) {
        const ro = new ResizeObserver(debounce(() => doRender(), 350));
        ro.observe(root);
        const cleanup = () => ro.disconnect();
        document.addEventListener('turbo:before-cache', cleanup, {once: true});
    }

    // Fullscreen modal on click.
    canvas.style.cursor = 'zoom-in';
    canvas.addEventListener('click', async () => {
        const {overlay, img} = getOrCreateFullscreenModal();
        if (!(overlay instanceof HTMLElement) || !(img instanceof HTMLImageElement)) return;

        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('organigram-fullscreen-open');

        // Reuse the already-rendered canvas as an image: avoids re-render differences (flip) in modal.
        try {
            img.src = canvas.toDataURL('image/png');
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error('Failed to snapshot organigram canvas for fullscreen modal', e);
        }
    });
}

document.addEventListener('DOMContentLoaded', initOrganigramView);
document.addEventListener('turbo:load', initOrganigramView);
document.addEventListener('turbo:before-cache', () => {
    const root = document.querySelector('[data-organigram-view="1"]');
    if (!(root instanceof HTMLElement)) return;
    delete root.dataset.organigramInit;
    const canvas = root.querySelector('[data-organigram-canvas="1"]');
    if (canvas instanceof HTMLCanvasElement) {
        canvas.width = 0;
        canvas.height = 0;
    }
});
