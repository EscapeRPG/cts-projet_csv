function init() {
    const buttons = document.querySelectorAll('.organigram-filters__button');
    if (!buttons) return;

    const img = document.querySelector('.organigram');

    buttons.forEach(button => button.addEventListener('click', () => {
        buttons.forEach(btn => btn.classList.remove('active'));

        button.classList.add('active');

        const filter = button.dataset.filter;

        if (filter === 'immobilier') {
            img.src = '/img/organigramme/organigramme-structurel-immobilier.jpg';
        } else {
            img.src = '/img/organigramme/organigramme-structurel.jpg';
        }
    }));
}

document.addEventListener('turbo:load', init);
document.addEventListener('DOMContentLoaded', init);
