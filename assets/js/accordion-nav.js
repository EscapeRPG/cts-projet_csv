document.addEventListener('turbo:load', () => {
    document.querySelectorAll('.toggle').forEach(title => {
        if (title.dataset.bound) return;
        title.dataset.bound = "true";

        title.addEventListener('click', () => {
            const span = title.querySelector('.open-btn');
            const menu = title.nextElementSibling;

            document.querySelectorAll('.menu').forEach(m => {
                if (m !== menu) m.style.maxHeight = null;
            });

            document.querySelectorAll('.toggle span').forEach(s => {
                if (s !== span) s.classList.remove('open');
            });

            document.querySelectorAll('.toggle').forEach(t => {
                if (t !== title) t.classList.remove('open');
            });

            if (menu.style.maxHeight) {
                menu.style.maxHeight = null;
                span?.classList.remove('open');
                title.classList.remove('open');
            } else {
                menu.style.maxHeight = menu.scrollHeight + "px";
                span?.classList.add('open');
                title.classList.add('open');
            }
        });
    });
});
