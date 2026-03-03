import {sortTableByColumn} from "sort_tables";

const MONTH_LABELS = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'];
const SERIES_COLORS = ['#ff2424', '#ff9b18', '#028eb1'];
const HOVER_DISTANCE_PX = 12;
let proChartsState = null;
let proChartsResizeObserver = null;
let proChartsRaf = null;

/**
 * Initializes table sorting and chart rendering for the professional clients page.
 *
 * @returns {void}
 */
function init() {
    const table = document.querySelector('.table-pros');
    if (table) {
        const headers = table.querySelectorAll('th');

        // Enable click sorting on each table header.
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

/**
 * Initializes chart state from DOM dataset payload.
 *
 * @returns {void}
 */
function initProCharts() {
    const chartRoot = document.querySelector('.stats-wrapper.graphique[data-pro-charts]');
    if (!chartRoot) {
        cleanupProCharts();
        return;
    }

    const canvas = chartRoot.querySelector('.pro-chart-canvas');
    if (!(canvas instanceof HTMLCanvasElement)) {
        cleanupProCharts();
        return;
    }

    let chartData = null;
    try {
        chartData = JSON.parse(chartRoot.dataset.proCharts || '{}');
    } catch (e) {
        cleanupProCharts();
        return;
    }

    const years = Array.isArray(chartData?.years) ? chartData.years : [];
    if (!years.length) {
        cleanupProCharts();
        return;
    }

    const toggles = chartRoot.querySelectorAll('.chart-toggle');

    if (proChartsState?.root !== chartRoot) {
        cleanupProCharts();
    }

    proChartsState = {
        root: chartRoot,
        canvas,
        chartData,
        years,
        currentKind: proChartsState?.currentKind === 'volumes' ? 'volumes' : 'ca',
        points: [],
        hoveredPoint: null,
    };

    proChartsState.render = () => {
        if (!proChartsState || !proChartsState.root.isConnected) return;

        const source = proChartsState.chartData?.[proChartsState.currentKind] || {};
        const series = proChartsState.years.map((year) => {
            const valuesByMonth = source[String(year)] || source[year] || {};
            const values = [];
            for (let month = 1; month <= 12; month += 1) {
                const raw = valuesByMonth[String(month)] ?? valuesByMonth[month] ?? 0;
                values.push(Number(raw) || 0);
            }
            return {year, values};
        });

        proChartsState.points = drawLineChart(proChartsState.canvas, {
            title: proChartsState.currentKind === 'ca' ? 'CA mensuel' : 'Controles mensuels',
            unit: proChartsState.currentKind === 'ca' ? 'EUR' : 'nb',
            series,
        }, proChartsState.hoveredPoint);
    };

    toggles.forEach((button) => {
        button.addEventListener('click', () => {
            const kind = button.dataset.chartKind;
            if (kind !== 'ca' && kind !== 'volumes') return;

            proChartsState.currentKind = kind;
            toggles.forEach((btn) => btn.classList.toggle('active', btn === button));
            scheduleProChartsRender();
        });

        button.classList.toggle('active', button.dataset.chartKind === proChartsState.currentKind);
    });

    setupProChartsResizeObserver();
    setupProChartsInteractions();
    scheduleProChartsRender();
}

/**
 * Clears chart observers, animation frame, and local state.
 *
 * @returns {void}
 */
function cleanupProCharts() {
    if (proChartsResizeObserver) {
        proChartsResizeObserver.disconnect();
        proChartsResizeObserver = null;
    }

    proChartsState = null;

    if (proChartsRaf) {
        cancelAnimationFrame(proChartsRaf);
        proChartsRaf = null;
    }
}

/**
 * Schedules chart rendering on the next animation frame.
 *
 * @returns {void}
 */
function scheduleProChartsRender() {
    if (!proChartsState?.render) return;

    if (proChartsRaf) {
        cancelAnimationFrame(proChartsRaf);
    }

    proChartsRaf = requestAnimationFrame(() => {
        proChartsRaf = null;
        proChartsState?.render?.();
    });
}

/**
 * Installs a resize observer to re-render charts when dimensions change.
 *
 * @returns {void}
 */
function setupProChartsResizeObserver() {
    if (!proChartsState?.canvas) return;

    const resizeTarget = proChartsState.canvas.parentElement;
    if (!resizeTarget) return;

    if (proChartsResizeObserver) {
        proChartsResizeObserver.disconnect();
        proChartsResizeObserver = null;
    }

    if (typeof ResizeObserver === 'undefined') return;

    proChartsResizeObserver = new ResizeObserver(() => {
        scheduleProChartsRender();
    });
    proChartsResizeObserver.observe(resizeTarget);
}

/**
 * Installs hover interactions for chart points and tooltips.
 *
 * @returns {void}
 */
function setupProChartsInteractions() {
    if (!proChartsState?.canvas) return;
    const canvas = proChartsState.canvas;
    if (canvas.dataset.chartHoverInit === '1') return;
    canvas.dataset.chartHoverInit = '1';

    canvas.addEventListener('mousemove', (event) => {
        if (!proChartsState?.points?.length) return;

        const rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return;

        const mouseX = event.clientX - rect.left;
        const mouseY = event.clientY - rect.top;
        const nextPoint = findNearestPoint(proChartsState.points, mouseX, mouseY, HOVER_DISTANCE_PX);
        const previousId = proChartsState.hoveredPoint?.id || null;
        const nextId = nextPoint?.id || null;
        if (previousId === nextId) return;

        proChartsState.hoveredPoint = nextPoint;
        scheduleProChartsRender();
    });

    canvas.addEventListener('mouseleave', () => {
        if (!proChartsState?.hoveredPoint) return;
        proChartsState.hoveredPoint = null;
        scheduleProChartsRender();
    });
}

/**
 * Returns the nearest point within the provided distance threshold.
 *
 * @param {Array<{id:string,x:number,y:number,value:number,monthIndex:number,year:number|string,color:string,unit:string}>} points
 * @param {number} x
 * @param {number} y
 * @param {number} maxDistance
 * @returns {{id:string,x:number,y:number,value:number,monthIndex:number,year:number|string,color:string,unit:string}|null}
 */
function findNearestPoint(points, x, y, maxDistance) {
    let nearest = null;
    let nearestDistSq = maxDistance * maxDistance;

    points.forEach((point) => {
        const dx = point.x - x;
        const dy = point.y - y;
        const distSq = dx * dx + dy * dy;
        if (distSq <= nearestDistSq) {
            nearest = point;
            nearestDistSq = distSq;
        }
    });

    return nearest;
}

/**
 * Draws the chart and returns rendered point metadata.
 *
 * @param {HTMLCanvasElement} canvas
 * @param {{title:string,unit:'EUR'|'nb',series:Array<{year:number|string,values:number[]}>}} payload
 * @param {{id:string}|null} hoveredPoint
 * @returns {Array<{id:string,x:number,y:number,value:number,monthIndex:number,year:number|string,color:string,unit:string}>|undefined}
 */
function drawLineChart(canvas, payload, hoveredPoint = null) {
    const root = canvas.parentElement;
    if (!root) return;

    const dpr = window.devicePixelRatio || 1;
    const width = Math.floor(root.clientWidth);
    const height = Math.floor(root.clientHeight || 280);
    if (width <= 0 || height <= 0) return;

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

    const points = [];
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
            const pointId = `${serie.year}-${i}`;
            points.push({
                id: pointId,
                x,
                y,
                value,
                monthIndex: i,
                year: serie.year,
                color,
                unit: payload.unit,
            });

            ctx.beginPath();
            ctx.arc(x, y, 4.4, 0, Math.PI * 2);
            ctx.fill();
        });
    });

    drawLegend(ctx, orderedYears, colorByYear, width, padding);

    if (hoveredPoint) {
        const activePoint = points.find((point) => point.id === hoveredPoint.id);
        if (activePoint) {
            drawHoveredPoint(ctx, activePoint);
            drawTooltip(ctx, activePoint, width, height, padding);
        }
    }
    return points;
}

/**
 * Draws the chart legend.
 *
 * @param {CanvasRenderingContext2D} ctx
 * @param {Array<{year:number|string}>} series
 * @param {Map<string,string>} colorByYear
 * @param {number} width
 * @param {{top:number,right:number,bottom:number,left:number}} padding
 * @returns {void}
 */
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

/**
 * Computes a rounded axis upper bound.
 *
 * @param {number} value
 * @returns {number}
 */
function niceUpper(value) {
    if (value <= 10) return 10;
    const exponent = Math.floor(Math.log10(value));
    const base = 10 ** exponent;
    return Math.ceil(value / base) * base;
}

/**
 * Formats Y-axis tick labels according to the active unit.
 *
 * @param {number} value
 * @param {'EUR'|'nb'} unit
 * @returns {string}
 */
function formatTick(value, unit) {
    if (unit === 'EUR') {
        if (value >= 1000) {
            return `${Math.round(value / 1000)}k`;
        }
        return `${Math.round(value)}`;
    }

    return `${Math.round(value)}`;
}

/**
 * Draws the highlighted hovered point.
 *
 * @param {CanvasRenderingContext2D} ctx
 * @param {{x:number,y:number,color:string}} point
 * @returns {void}
 */
function drawHoveredPoint(ctx, point) {
    ctx.beginPath();
    ctx.fillStyle = '#ffffff';
    ctx.arc(point.x, point.y, 7.2, 0, Math.PI * 2);
    ctx.fill();

    ctx.beginPath();
    ctx.fillStyle = point.color;
    ctx.arc(point.x, point.y, 5, 0, Math.PI * 2);
    ctx.fill();
}

/**
 * Draws a tooltip near the hovered point.
 *
 * @param {CanvasRenderingContext2D} ctx
 * @param {{value:number,unit:'EUR'|'nb',monthIndex:number,year:number|string,x:number,y:number,color:string}} point
 * @param {number} width
 * @param {number} height
 * @param {{top:number,right:number,bottom:number,left:number}} padding
 * @returns {void}
 */
function drawTooltip(ctx, point, width, height, padding) {
    const month = MONTH_LABELS[point.monthIndex] || '';
    const valueLabel = formatTooltipValue(point.value, point.unit);
    const line1 = `${month} ${point.year}`;
    const line2 = `${valueLabel}${point.unit === 'EUR' ? ' EUR' : ''}`;

    ctx.font = '600 12px Arial';
    const line1Width = ctx.measureText(line1).width;
    ctx.font = '12px Arial';
    const line2Width = ctx.measureText(line2).width;

    const tooltipWidth = Math.ceil(Math.max(line1Width, line2Width) + 20);
    const tooltipHeight = 44;
    const gap = 12;

    let x = point.x + gap;
    let y = point.y - tooltipHeight - gap;

    if (x + tooltipWidth > width - padding.right) {
        x = point.x - tooltipWidth - gap;
    }
    if (x < padding.left) {
        x = padding.left;
    }

    if (y < padding.top) {
        y = point.y + gap;
    }
    if (y + tooltipHeight > height - padding.bottom) {
        y = height - padding.bottom - tooltipHeight;
    }

    ctx.fillStyle = 'rgba(29, 40, 48, 0.95)';
    roundRect(ctx, x, y, tooltipWidth, tooltipHeight, 6);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.font = '600 12px Arial';
    ctx.textAlign = 'left';
    ctx.fillText(line1, x + 10, y + 16);
    ctx.font = '12px Arial';
    ctx.fillText(line2, x + 10, y + 33);
}

/**
 * Formats values shown inside tooltips.
 *
 * @param {number} value
 * @param {'EUR'|'nb'} unit
 * @returns {string}
 */
function formatTooltipValue(value, unit) {
    if (unit === 'EUR') {
        return Number(value).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    return Number(value).toLocaleString('fr-FR', {maximumFractionDigits: 0});
}

/**
 * Builds a rounded rectangle path on the canvas context.
 *
 * @param {CanvasRenderingContext2D} ctx
 * @param {number} x
 * @param {number} y
 * @param {number} width
 * @param {number} height
 * @param {number} radius
 * @returns {void}
 */
function roundRect(ctx, x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + width - r, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + r);
    ctx.lineTo(x + width, y + height - r);
    ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
    ctx.lineTo(x + r, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('turbo:load', init);
document.addEventListener('suivi:results-updated', init);
window.addEventListener('resize', scheduleProChartsRender);
document.addEventListener('suivi:panel-focus-changed', scheduleProChartsRender);
