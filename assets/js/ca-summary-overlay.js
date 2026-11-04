function init(root = document) {
    root.querySelectorAll('.hoverable-summary').forEach((cell) => {
        if (!(cell instanceof HTMLElement)) return;
        if (cell.dataset.summaryOverlayInit === '1') return;
        if (!cell.querySelector('.hovered-summary')) return;
        cell.dataset.summaryOverlayInit = '1';

        cell.addEventListener('mouseenter', () => cell.classList.add('is-showing-details'));
        cell.addEventListener('mouseleave', () => cell.classList.remove('is-showing-details'));
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
} else {
    init();
}
document.addEventListener('turbo:load', () => init());
document.addEventListener('station:details-loaded', (event) => init(event.target));
document.addEventListener('turbo:before-cache', () => {
    document.querySelectorAll('.hoverable-summary.is-showing-details').forEach((cell) => {
        cell.classList.remove('is-showing-details');
    });
});