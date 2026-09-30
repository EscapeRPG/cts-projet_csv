const MONTH_NAMES = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

function parseIsoDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date();
}

function formatIsoDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function addDays(date, amount) {
    const result = new Date(date);
    result.setDate(result.getDate() + amount);
    return result;
}

function mondayOf(date) {
    const result = new Date(date);
    const day = result.getDay();
    result.setDate(result.getDate() - day + (day === 0 ? -6 : 1));
    return result;
}

function sameDay(first, second) {
    return formatIsoDate(first) === formatIsoDate(second);
}

let stationRequestController = null;
const calendarCleanups = new WeakMap();

async function loadStation(radio) {
    const currentContainer = document.querySelector('[data-station-results]');
    const activity = document.querySelector('[data-releve-activity]');

    if (!(currentContainer instanceof HTMLElement)) return;

    stationRequestController?.abort();
    const requestController = new AbortController();
    stationRequestController = requestController;

    const url = new URL(window.location.href);
    url.searchParams.set('station', radio.value);

    currentContainer.classList.add('is-loading');
    currentContainer.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(url, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: requestController.signal,
        });

        if (!response.ok) throw new Error(`Réponse HTTP ${response.status}`);

        const documentHtml = new DOMParser().parseFromString(await response.text(), 'text/html');
        const nextContainer = documentHtml.querySelector('[data-station-results]');
        if (!(nextContainer instanceof HTMLElement)) {
            throw new Error('Conteneur des relevés absent de la réponse');
        }

        currentContainer.querySelectorAll('[data-station-calendar]').forEach((calendar) => {
            calendarCleanups.get(calendar)?.();
        });
        currentContainer.replaceWith(nextContainer);
        if (activity instanceof HTMLElement) activity.textContent = '';
        window.history.replaceState({}, '', url);

        nextContainer.querySelectorAll('[data-station-calendar]').forEach(initCalendar);
    } catch (error) {
        if (error.name !== 'AbortError' && activity instanceof HTMLElement) {
            activity.textContent = 'Impossible de charger les relevés de cette station.';
        }
    } finally {
        if (stationRequestController === requestController) {
            stationRequestController = null;
            const activeContainer = document.querySelector('[data-station-results]');
            activeContainer?.classList.remove('is-loading');
            activeContainer?.removeAttribute('aria-busy');
        }
    }
}

function initStationSelector() {
    document.querySelectorAll('[data-station-selector] input[type="radio"][name="station"]').forEach((radio) => {
        if (!(radio instanceof HTMLInputElement) || radio.dataset.stationInit === '1') return;
        radio.dataset.stationInit = '1';
        radio.addEventListener('change', async () => {
            if (!radio.checked) return;
            if (radio.dataset.stationUrl) {
                window.location.assign(radio.dataset.stationUrl);
                return;
            }
            await loadStation(radio);
        });
    });
}

function initCalendar(root) {
    if (!(root instanceof HTMLElement) || root.dataset.calendarInit === '1') return;

    const stationId = root.dataset.stationId;
    const detailsUrl = root.dataset.detailsUrl;
    const yearDisplay = root.querySelector('#yearDisplay');
    const monthDisplay = root.querySelector('#monthDisplay');
    const daysContainer = root.querySelector('#daysContainer');
    const detailsContainer = document.querySelector('[data-day-details]');

    if (!stationId || !detailsUrl || !(yearDisplay instanceof HTMLElement)
        || !(monthDisplay instanceof HTMLElement) || !(daysContainer instanceof HTMLElement)
        || !(detailsContainer instanceof HTMLElement)) return;

    root.dataset.calendarInit = '1';

    let selectedDate = parseIsoDate(root.dataset.selectedDate);
    let startDate = mondayOf(selectedDate);
    let requestController = null;

    const updateHeader = () => {
        yearDisplay.textContent = String(selectedDate.getFullYear());
        monthDisplay.textContent = MONTH_NAMES[selectedDate.getMonth()];
    };

    const yearDropMenu = document.createElement('div');
    yearDropMenu.id = 'yearDropMenu';
    yearDropMenu.className = 'calendar-drop-menu drop-menu-hidden';
    yearDropMenu.style.zIndex = '999';
    root.appendChild(yearDropMenu);

    const monthDropMenu = document.createElement('div');
    monthDropMenu.id = 'monthDropMenu';
    monthDropMenu.className = 'calendar-drop-menu drop-menu-hidden';
    monthDropMenu.style.zIndex = '999';
    root.appendChild(monthDropMenu);

    for (let i = 0; i < 12; i++) {
        const button = document.createElement('button');
        button.type = 'button';
        if (i === selectedDate.getMonth()) button.classList.add("now");
        button.innerText = MONTH_NAMES[i];

        button.addEventListener("click", () => {
            startDate = mondayOf(new Date(selectedDate.getFullYear(), i, 1));
            selectDate(new Date(selectedDate.getFullYear(), i, 1));
            monthDropMenu.classList.add('drop-menu-hidden');
            document.querySelectorAll('button.now').forEach(button => button.classList.remove('now'));
            button.classList.add("now");
        });

        monthDropMenu.appendChild(button);
    }

    yearDisplay.addEventListener('click', handleYearClick);
    monthDisplay.addEventListener('click', handleMonthClick);

    const closeOnOutsideClick = (event) => {
        const target = event.target;
        if (!(target instanceof Node)) return;
        if (yearDisplay.contains(target) || yearDropMenu.contains(target) || monthDisplay.contains(target) || monthDropMenu.contains(target)) return;
        yearDropMenu.classList.add('drop-menu-hidden');
        monthDropMenu.classList.add('drop-menu-hidden');
    };
    document.addEventListener('click', closeOnOutsideClick, true);
    calendarCleanups.set(root, () => {
        document.removeEventListener('click', closeOnOutsideClick, true);
        requestController?.abort();
        calendarCleanups.delete(root);
    });

    function handleYearClick() {
        if (yearDropMenu.classList.contains('drop-menu-hidden')) {
            const selectedYear = selectedDate.getFullYear();
            const startYear = selectedYear % 10 === 0 ? selectedYear - 10 : selectedYear - (selectedYear % 10);
            const bounds = yearDisplay.getBoundingClientRect();
            yearDropMenu.style.top = `${bounds.bottom}px`;
            yearDropMenu.style.left = `${bounds.left}px`;
            generateYears(startYear, selectedYear);
        }

        yearDropMenu.classList.toggle('drop-menu-hidden');
        if (!monthDropMenu.classList.contains('drop-menu-hidden')) {
            monthDropMenu.classList.add('drop-menu-hidden');
        }
    }

    function handleMonthClick() {
        const bounds = monthDisplay.getBoundingClientRect();
        monthDropMenu.style.top = `${bounds.bottom}px`;
        monthDropMenu.style.left = `${bounds.left}px`;

        monthDropMenu.classList.toggle('drop-menu-hidden');
        if (!yearDropMenu.classList.contains('drop-menu-hidden')) {
            yearDropMenu.classList.add('drop-menu-hidden');
        }
    }

    function generateYears(startYear, selectedYear) {
        yearDropMenu.innerHTML = "";

        for (let i = startYear - 1; i < startYear + 11; i++) {
            const button = document.createElement('button');
            button.type = 'button';

            if (i === startYear - 1) {
                button.innerHTML = '&lt;';
                button.addEventListener("click", () => {
                    generateYears(startYear - 10, selectedYear);
                })
            } else if (i === startYear + 10) {
                button.innerHTML = '&gt;';
                button.addEventListener("click", () => {
                    generateYears(startYear + 10, selectedYear);
                })
            } else {
                if (i === selectedYear) button.classList.add("now");
                button.innerText = String(i);
                button.addEventListener("click", () => {
                    startDate = mondayOf(new Date(i, 0, 1));
                    selectDate(new Date(i, 0, 1));
                    yearDropMenu.classList.add('drop-menu-hidden');
                    handleMonthClick();
                });
            }

            yearDropMenu.appendChild(button);
        }
    }

    function renderDays() {
        const today = new Date();
        daysContainer.replaceChildren();

        for (let offset = 0; offset < 14; offset += 1) {
            const date = addDays(startDate, offset);
            const button = document.createElement('button');
            const dayName = new Intl.DateTimeFormat('fr-FR', {weekday: 'long'}).format(date);
            const dayNameCapitalized = dayName.charAt(0).toUpperCase() + dayName.slice(1);
            button.type = 'button';
            button.className = 'day';
            if (dayName === "samedi" || dayName === "dimanche") {
                button.classList.add('weekend');
            }
            button.dataset.date = formatIsoDate(date);
            button.setAttribute('aria-label', `Afficher le relevé du ${date.toLocaleDateString('fr-FR')}`);
            button.innerHTML = `<span>${dayNameCapitalized}</span><span class="date">${date.getDate()}</span><span>${MONTH_NAMES[date.getMonth()]}</span>`;
            if (sameDay(date, today)) button.classList.add('today');
            if (sameDay(date, selectedDate)) {
                button.classList.add('selected');
                button.setAttribute('aria-current', 'date');
            }
            if (date.getDay() === 0 || date.getDay() === 6) button.classList.add('weekend');
            button.addEventListener('click', () => selectDate(date));
            daysContainer.appendChild(button);
        }
    }

    async function loadDetails() {
        requestController?.abort();
        requestController = new AbortController();
        const selectedDateValue = formatIsoDate(selectedDate);
        const url = new URL(detailsUrl, window.location.origin);
        url.searchParams.set('station', stationId);
        url.searchParams.set('date', selectedDateValue);

        const pageUrl = new URL(window.location.href);
        pageUrl.searchParams.set('station', stationId);
        pageUrl.searchParams.set('date', selectedDateValue);
        window.history.replaceState({}, '', pageUrl);

        detailsContainer.classList.add('is-loading');
        root.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                signal: requestController.signal,
            });
            if (!response.ok) throw new Error(`Réponse HTTP ${response.status}`);
            detailsContainer.innerHTML = await response.text();
            detailsContainer.dispatchEvent(new CustomEvent('station:details-loaded', {bubbles: true}));
        } catch (error) {
            if (error.name !== 'AbortError') {
                detailsContainer.innerHTML = '<div class="centre-detail-alert"><p>Impossible de charger le relevé demandé.</p></div>';
            }
        } finally {
            detailsContainer.classList.remove('is-loading');
            root.removeAttribute('aria-busy');
        }
    }

    function selectDate(date) {
        selectedDate = new Date(date);
        updateHeader();
        renderDays();
        loadDetails();
    }

    function selectFirstDayOfMonth(offset) {
        selectedDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth() + offset, 1);
        startDate = mondayOf(selectedDate);
        updateHeader();
        renderDays();
        loadDetails();
    }

    function selectFirstDayOfYear(offset) {
        selectedDate = new Date(selectedDate.getFullYear() + offset, 0, 1);
        startDate = mondayOf(selectedDate);
        updateHeader();
        renderDays();
        loadDetails();
    }

    function backToToday() {
        selectedDate = new Date();
        startDate = mondayOf(selectedDate);
        updateHeader();
        renderDays();
        loadDetails();
    }

    root.querySelector('#prevYear')?.addEventListener('click', () => selectFirstDayOfYear(-1));
    root.querySelector('#nextYear')?.addEventListener('click', () => selectFirstDayOfYear(1));
    root.querySelector('#prevMonth')?.addEventListener('click', () => selectFirstDayOfMonth(-1));
    root.querySelector('#nextMonth')?.addEventListener('click', () => selectFirstDayOfMonth(1));
    root.querySelector('#backToday')?.addEventListener('click', () => backToToday());
    root.querySelector('#prevDay')?.addEventListener('click', () => {
        startDate = addDays(startDate, -1);
        renderDays();
    });
    root.querySelector('#nextDay')?.addEventListener('click', () => {
        startDate = addDays(startDate, 1);
        renderDays();
    });

    updateHeader();
    renderDays();
    loadDetails();
}

function init() {
    initStationSelector();
    document.querySelectorAll('[data-station-calendar]').forEach(initCalendar);
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('turbo:load', init);
