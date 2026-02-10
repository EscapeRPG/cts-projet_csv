// Affiche un loader le temps que toute la page soit chargée
function loadContent() {
    const loader = document.getElementById('loader');
    const wrapper = document.getElementById('loadingContent');
    loader.classList.add('hidden');
    wrapper.classList.add('visible');

    setTimeout(() => loader.remove(), 400);
}

document.addEventListener('turbo:load', loadContent);
