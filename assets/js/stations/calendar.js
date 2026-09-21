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

function initStationSelector() {
    document.querySelectorAll('[data-station-selector] input[type="radio"][name="station"]').forEach((radio) => {
        if (!(radio instanceof HTMLInputElement) || radio.dataset.stationInit === '1') return;
        radio.dataset.stationInit = '1';
        radio.addEventListener('change', () => {
            if (!radio.checked) return;
            const url = new URL(window.location.href);
            url.searchParams.set('station', radio.value);
            window.location.assign(url);
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

    function renderDays() {
        const today = new Date();
        daysContainer.replaceChildren();

        for (let offset = 0; offset < 14; offset += 1) {
            const date = addDays(startDate, offset);
            const button = document.createElement('button');
            const dayName = new Intl.DateTimeFormat('fr-FR', {weekday: 'long'}).format(date);
            button.type = 'button';
            button.className = 'day';
            button.dataset.date = formatIsoDate(date);
            button.setAttribute('aria-label', `Afficher le relevé du ${date.toLocaleDateString('fr-FR')}`);
            button.innerHTML = `<span class="date">${date.getDate()}</span><span>${dayName}</span>`;
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
    root.querySelector('#prevDay')?.addEventListener('click', () => { startDate = addDays(startDate, -1); renderDays(); });
    root.querySelector('#nextDay')?.addEventListener('click', () => { startDate = addDays(startDate, 1); renderDays(); });

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
