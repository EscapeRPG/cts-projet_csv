import {getDocument, GlobalWorkerOptions} from "pdfjs-dist";

let isRunning = false;

function ensureWorkerConfigured(workerUrl) {
    if (GlobalWorkerOptions.workerSrc) return;
    const normalized = String(workerUrl ?? '').trim();
    if (normalized) {
        GlobalWorkerOptions.workerSrc = normalized;
    }
}

function setSelectedLabel(root, key, pageNum) {
    const el = root.querySelector(`[data-organigram-selected="${CSS.escape(key)}"]`);
    if (!(el instanceof HTMLElement)) return;
    el.textContent = pageNum ? `page ${pageNum}` : '';
}

function setHidden(root, key, pageNum) {
    const input = root.querySelector(`[data-organigram-target="${CSS.escape(key)}"]`);
    if (!(input instanceof HTMLInputElement)) return;
    input.value = pageNum ? String(pageNum) : '';
    setSelectedLabel(root, key, pageNum);
}

function markButtons(root) {
    const selected = {
        structurel: (root.querySelector('[data-organigram-target="structurel"]')?.value ?? '').trim(),
        immobilier: (root.querySelector('[data-organigram-target="immobilier"]')?.value ?? '').trim(),
        hierarchique: (root.querySelector('[data-organigram-target="hierarchique"]')?.value ?? '').trim(),
    };

    root.querySelectorAll('[data-assign-key]').forEach((btn) => {
        if (!(btn instanceof HTMLButtonElement)) return;
        const key = String(btn.dataset.assignKey ?? '');
        const page = String(btn.dataset.assignPage ?? '');
        btn.classList.toggle('active', selected[key] !== '' && selected[key] === page);
    });
}

async function renderThumb(page, canvas) {
    const viewport = page.getViewport({scale: 1});
    const targetWidth = 340;
    const scale = targetWidth / viewport.width;
    const scaledViewport = page.getViewport({scale: scale > 0 ? scale : 1});

    const outputScale = Math.min(2, window.devicePixelRatio || 1);
    const ctx = canvas.getContext('2d', {alpha: false});
    if (!ctx) return;

    canvas.width = Math.floor(scaledViewport.width * outputScale);
    canvas.height = Math.floor(scaledViewport.height * outputScale);
    canvas.style.width = `${Math.floor(scaledViewport.width)}px`;
    canvas.style.height = `${Math.floor(scaledViewport.height)}px`;

    ctx.setTransform(outputScale, 0, 0, outputScale, 0, 0);
    await page.render({canvasContext: ctx, viewport: scaledViewport}).promise;
}

async function initOrganigramConfigure() {
    const form = document.querySelector('form[data-organigram-config="1"]');
    if (!(form instanceof HTMLFormElement)) return;
    if (isRunning) return;
    isRunning = true;

    const pagesHost = form.querySelector('[data-organigram-pages="1"]');
    try {
        if (!(pagesHost instanceof HTMLElement)) return;
        // Turbo can restore this page from cache with blank canvases; always rebuild.
        pagesHost.innerHTML = '';
        form.dataset.organigramInit = '1';

        const pdfUrl = String(form.dataset.pdfUrl ?? '').trim();
        if (!pdfUrl) return;

        ensureWorkerConfigured(form.dataset.pdfWorkerUrl);

        // Initialize labels from hidden inputs.
        ['structurel', 'immobilier', 'hierarchique'].forEach((k) => {
            const v = (form.querySelector(`[data-organigram-target="${k}"]`)?.value ?? '').trim();
            setSelectedLabel(form, k, v !== '' ? Number(v) : null);
        });

        let pdf;
        try {
            pdf = await getDocument({url: pdfUrl, withCredentials: true}).promise;
        } catch (e) {
            const msg = document.createElement('div');
            msg.style.color = 'red';
            msg.style.fontWeight = '600';
            msg.textContent = 'Impossible de charger le PDF (verifier l\'URL et les droits).';
            pagesHost.appendChild(msg);
            // eslint-disable-next-line no-console
            console.error('Failed to load organigram PDF', {pdfUrl, e});
            return;
        }

        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const card = document.createElement('div');
            card.className = 'organigram-config__page';
            card.dataset.page = String(pageNum);

            const title = document.createElement('div');
            title.textContent = `Page ${pageNum}`;
            title.style.fontWeight = '600';
            card.appendChild(title);

            const canvas = document.createElement('canvas');
            card.appendChild(canvas);

            const actions = document.createElement('div');
            actions.className = 'organigram-config__page-actions';

            const mkBtn = (key, label) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = label;
                btn.dataset.assignKey = key;
                btn.dataset.assignPage = String(pageNum);
                btn.addEventListener('click', () => {
                    setHidden(form, key, pageNum);
                    markButtons(form);
                });
                return btn;
            };

            actions.appendChild(mkBtn('structurel', 'Structurel'));
            actions.appendChild(mkBtn('immobilier', 'Immobilier'));
            actions.appendChild(mkBtn('hierarchique', 'Hiérarchique'));
            card.appendChild(actions);

            pagesHost.appendChild(card);

            // Render thumbnail (sequential to keep memory stable).
            try {
                const page = await pdf.getPage(pageNum);
                await renderThumb(page, canvas);
            } catch (e) {
                // eslint-disable-next-line no-console
                console.error('Failed to render PDF page thumbnail', {pageNum, e});
            }
        }

        markButtons(form);
    } finally {
        isRunning = false;
    }
}

document.addEventListener('DOMContentLoaded', initOrganigramConfigure);
document.addEventListener('turbo:load', initOrganigramConfigure);
document.addEventListener('turbo:before-cache', () => {
    const form = document.querySelector('form[data-organigram-config="1"]');
    if (!(form instanceof HTMLFormElement)) return;
    delete form.dataset.organigramInit;
    isRunning = false;
    const pagesHost = form.querySelector('[data-organigram-pages="1"]');
    if (pagesHost instanceof HTMLElement) {
        pagesHost.innerHTML = '';
    }
});
