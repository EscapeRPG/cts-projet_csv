document.addEventListener('turbo:load', () => {
    const apps = document.querySelectorAll('.app');

    apps.forEach(app => {
        app.addEventListener('mouseover', () => {
            const title = app.querySelector('.inner-div');
            if (!title) return;
            title.classList.add('hovered');
        });

        app.addEventListener('mouseleave', () => {
            const title = app.querySelector('.inner-div');
            if (!title) return;
            title.classList.remove('hovered');
        });
    })
});
