function setTheme(body, button) {
    if (localStorage.getItem('theme') === 'dark') {
        localStorage.setItem('theme', 'light');
        body.classList.remove('dark');
        button.innerHTML = '&#9790';
    } else {
        localStorage.setItem('theme', 'dark');
        body.classList.add('dark');
        button.innerHTML = '&#9788';
    }

    document.dispatchEvent(new CustomEvent('theme:change', {
        detail: { theme: localStorage.getItem('theme') }
    }));
}

function init() {
    const body = document.body,
        themeBtn = document.querySelector('.theme-btn');

    if (!themeBtn) return;

    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark');
        themeBtn.innerHTML = '&#9788';
    } else {
        body.classList.remove('dark');
        themeBtn.innerHTML = '&#9790';
    }

    themeBtn.onclick = () => setTheme(body, themeBtn);
}

document.addEventListener('turbo:load', init);
