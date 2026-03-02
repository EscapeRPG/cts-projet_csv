import { sortTableByColumn } from "sort_tables";

const MONTH_LABELS = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'];
const SERIES_COLORS = ['#ff2424', '#ff9b18', '#028eb1'];

// Ajoute une fonction de tri sur certaines colonnes de la table de salariés et prépare la table pour un affichage optimisé
function init() {
    const table = document.querySelector('.table-pros');
    if (table) {
        const headers = table.querySelectorAll('th');

        // Ajout de la fonction de tri
        headers.forEach((th, index) => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                sortTableByColumn(table, index);
            });
            th.classList.add('sorted-asc');
        });
    }

    initProCharts();
}

function initProCharts() {
    const chartRoot = document.querySelector('.stats-wrapper.graphique[data-pro-charts]');
    if (!chartRoot) return;

    const canvas = chartRoot.querySelector('.pro-chart-canvas');
    if (!(canvas instanceof HTMLCanvasElement)) return;

    let chartData = null;
    try {
        chartData = JSON.parse(chartRoot.dataset.proCharts || '{}');
    } catch (e) {
        return;
    }

    const years = Array.isArray(chartData?.years) ? chartData.years : [];
    if (!years.length) return;

    const toggles = chartRoot.querySelectorAll('.chart-toggle');
    let currentKind = 'ca';

    const render = () => {
        const source = chartData?.[currentKind] || {};
        const series = years.map((year) => {
            const valuesByMonth = source[String(year)] || source[year] || {};
            const values = [];
            for (let month = 1; month <= 12; month += 1) {
                const raw = valuesByMonth[String(month)] ?? valuesByMonth[month] ?? 0;
                values.push(Number(raw) || 0);
            }
            return {year, values};
        });

        drawLineChart(canvas, {
            title: currentKind === 'ca' ? 'CA mensuel' : 'Controles mensuels',
            unit: currentKind === 'ca' ? 'EUR' : 'nb',
            series,
        });
    };

    toggles.forEach((button) => {
        button.addEventListener('click', () => {
            const kind = button.dataset.chartKind;
            if (kind !== 'ca' && kind !== 'volumes') return;

            currentKind = kind;
            toggles.forEach((btn) => btn.classList.toggle('active', btn === button));
            render();
        });
    });

    render();
}

function drawLineChart(canvas, payload) {
    const root = canvas.parentElement;
    if (!root) return;

    const dpr = window.devicePixelRatio || 1;
    const width = Math.max(320, root.clientWidth);
    const height = Math.max(240, root.clientHeight || 280);

    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, width, height);

    const padding = {top: 32, right: 20, bottom: 34, left: 54};
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;

    const allValues = payload.series.flatMap((serie) => serie.values);
    const maxValue = Math.max(1, ...allValues);
    const gridSteps = 5;
    const yMax = niceUpper(maxValue);

    ctx.fillStyle = '#1d2830';
    ctx.font = '600 13px Arial';
    ctx.fillText(payload.title, padding.left, 18);

    ctx.strokeStyle = '#d9e2ea';
    ctx.lineWidth = 1;
    for (let i = 0; i <= gridSteps; i += 1) {
        const y = padding.top + (chartHeight * i) / gridSteps;
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(width - padding.right, y);
        ctx.stroke();

        const value = yMax - (yMax * i) / gridSteps;
        ctx.fillStyle = '#51606d';
        ctx.font = '11px Arial';
        ctx.fillText(formatTick(value, payload.unit), 6, y + 4);
    }

    ctx.strokeStyle = '#8ca0b2';
    ctx.beginPath();
    ctx.moveTo(padding.left, padding.top);
    ctx.lineTo(padding.left, padding.top + chartHeight);
    ctx.lineTo(padding.left + chartWidth, padding.top + chartHeight);
    ctx.stroke();

    for (let i = 0; i < 12; i += 1) {
        const x = padding.left + (chartWidth * i) / 11;
        ctx.fillStyle = '#51606d';
        ctx.font = '11px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(MONTH_LABELS[i], x, height - 10);
    }

    const orderedYears = [...payload.series].sort((a, b) => Number(a.year) - Number(b.year));
    const colorByYear = new Map(
        orderedYears.map((serie, index) => [String(serie.year), SERIES_COLORS[index % SERIES_COLORS.length]])
    );

    orderedYears.forEach((serie, index) => {
        const color = colorByYear.get(String(serie.year));
        ctx.strokeStyle = color;
        ctx.lineWidth = 4;
        ctx.beginPath();

        serie.values.forEach((value, i) => {
            const x = padding.left + (chartWidth * i) / 11;
            const y = padding.top + chartHeight - (value / yMax) * chartHeight;
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.stroke();

        ctx.fillStyle = color;
        serie.values.forEach((value, i) => {
            const x = padding.left + (chartWidth * i) / 11;
            const y = padding.top + chartHeight - (value / yMax) * chartHeight;
            ctx.beginPath();
            ctx.arc(x, y, 4.4, 0, Math.PI * 2);
            ctx.fill();
        });
    });

    drawLegend(ctx, orderedYears, colorByYear, width, padding);
}

function drawLegend(ctx, series, colorByYear, width, padding) {
    let x = width - padding.right - 8;
    const y = 18;

    ctx.textAlign = 'right';

    const legendSeries = [...series].sort((a, b) => Number(b.year) - Number(a.year));
    legendSeries.forEach((serie) => {
        const color = colorByYear.get(String(serie.year));
        const label = String(serie.year);
        const textWidth = ctx.measureText(label).width;

        ctx.fillStyle = '#1d2830';
        ctx.fillText(label, x, y);
        x -= textWidth + 8;

        ctx.fillStyle = color;
        ctx.fillRect(x - 10, y - 9, 10, 10);
        x -= 18;
    });
}

function niceUpper(value) {
    if (value <= 10) return 10;
    const exponent = Math.floor(Math.log10(value));
    const base = 10 ** exponent;
    return Math.ceil(value / base) * base;
}

function formatTick(value, unit) {
    if (unit === 'EUR') {
        if (value >= 1000) {
            return `${Math.round(value / 1000)}k`;
        }
        return `${Math.round(value)}`;
    }

    return `${Math.round(value)}`;
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('turbo:load', init);
document.addEventListener('suivi:results-updated', init);
