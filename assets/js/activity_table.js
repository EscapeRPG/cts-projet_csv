/**
 * Utilities for the "Suivi activite" table: column visibility and total recalculation.
 */

/**
 * Expands a row's cells into a flat array aligned with table header columns, taking colspans into account.
 *
 * @param {HTMLTableRowElement} row
 * @param {number} columnsCount
 * @returns {(HTMLTableCellElement|null)[]}
 */
function expandRowCells(row, columnsCount) {
    const expanded = new Array(columnsCount).fill(null);

    let col = 0;
    Array.from(row.cells).forEach((cell) => {
        const span = Math.max(1, Number.parseInt(cell.getAttribute('colspan') || '1', 10) || 1);
        for (let i = 0; i < span && col + i < columnsCount; i += 1) {
            expanded[col + i] = cell;
        }
        col += span;
    });

    return expanded;
}

/**
 * Parses an integer value from a table cell (handles spaces).
 *
 * @param {HTMLTableCellElement} cell
 * @returns {number}
 */
function parseCellInt(cell) {
    const raw = (cell.textContent || '').replace(/\s+/g, '').trim();
    const n = Number.parseInt(raw, 10);
    return Number.isFinite(n) ? n : 0;
}

/**
 * Parses a French-formatted decimal number from a table cell (e.g. "1 234,56€").
 *
 * @param {HTMLTableCellElement} cell
 * @returns {number}
 */
function parseCellFrenchFloat(cell) {
    const raw = (cell.textContent || '')
        .replace(/\u00a0/g, ' ')
        .replace(/€/g, '')
        .replace(/\s+/g, '')
        .trim()
        .replace(',', '.');

    const n = Number.parseFloat(raw);
    return Number.isFinite(n) ? n : 0;
}

/**
 * Formats a currency value like Twig's `number_format(2, ',', ' ')` followed by "€".
 *
 * @param {number} value
 * @returns {string}
 */
function formatFrenchCurrency(value) {
    return `${Number(value).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}€`;
}

/**
 * Recalculates the "Total" (counts) and "Total CA" cells based on visible activity columns.
 *
 * @param {HTMLTableElement} table
 * @returns {void}
 */
export function recalculateActivityTotals(table) {
    const headerRow = table.tHead?.rows?.[0];
    if (!(headerRow instanceof HTMLTableRowElement)) return;

    const headerCells = Array.from(headerRow.cells);
    if (!headerCells.length) return;

    const normalize = (s) => String(s || '').replace(/\s+/g, ' ').trim().toUpperCase();
    const totalCountIdx = headerCells.findIndex((th) => normalize(th.textContent) === 'TOTAL');
    const totalCaIdx = headerCells.findIndex((th) => normalize(th.textContent) === 'TOTAL CA');
    if (totalCountIdx < 0 || totalCaIdx < 0) return;

    const totalCols = headerCells.length;
    const countStart = 2;
    const countEnd = totalCountIdx - 1;
    const caStart = totalCountIdx + 1;
    const caEnd = totalCaIdx - 1;
    if (countEnd < countStart || caEnd < caStart) return;

    const rows = Array.from(table.tBodies).flatMap((tb) => Array.from(tb.rows));
    rows.forEach((row) => {
        if (!(row instanceof HTMLTableRowElement)) return;
        const expanded = expandRowCells(row, totalCols);

        let sumCount = 0;
        for (let i = countStart; i <= countEnd; i += 1) {
            const cell = expanded[i];
            if (!(cell instanceof HTMLTableCellElement)) continue;
            if (cell.hidden) continue;
            sumCount += parseCellInt(cell);
        }

        let sumCa = 0;
        for (let i = caStart; i <= caEnd; i += 1) {
            const cell = expanded[i];
            if (!(cell instanceof HTMLTableCellElement)) continue;
            if (cell.hidden) continue;
            sumCa += parseCellFrenchFloat(cell);
        }

        const totalCountCell = expanded[totalCountIdx];
        if (totalCountCell instanceof HTMLTableCellElement && !totalCountCell.hidden) {
            totalCountCell.textContent = String(sumCount);
        }

        const totalCaCell = expanded[totalCaIdx];
        if (totalCaCell instanceof HTMLTableCellElement && !totalCaCell.hidden) {
            totalCaCell.textContent = formatFrenchCurrency(sumCa);
        }
    });
}

/**
 * Applies column visibility based on selected activity type/vehicle families and recalculates totals.
 *
 * @param {HTMLTableElement} table
 * @param {Set<string>} selectedTypes
 * @param {Set<string>} selectedVehicles
 * @returns {void}
 */
export function applyActivityTableColumnVisibility(table, selectedTypes, selectedVehicles) {
    const hasTypeSelection = selectedTypes.size > 0;
    const hasVehicleSelection = selectedVehicles.size > 0;

    table.querySelectorAll('[data-activity-column]').forEach((cell) => {
        if (!(cell instanceof HTMLElement)) return;

        const typeFamily = cell.dataset.typeFamily || '';
        const vehicleFamily = cell.dataset.vehicleFamily || '';
        const matchesType = !hasTypeSelection || selectedTypes.has(typeFamily);
        const matchesVehicle = !hasVehicleSelection || selectedVehicles.has(vehicleFamily);

        cell.hidden = !(matchesType && matchesVehicle);
    });

    recalculateActivityTotals(table);
}

