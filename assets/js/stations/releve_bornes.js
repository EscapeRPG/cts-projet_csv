const MONEY_FIELDS = new Set(['cb', 'especes', 'cheque', 'bl', 'enCompte', 'contrat']);

function decimalValue(input) {
    const value = Number.parseFloat(input.value.replace(',', '.'));
    return Number.isFinite(value) ? value : 0;
}

function centsValue(input) {
    return Math.round(decimalValue(input) * 100);
}

function formatMoney(cents) {
    return `${(cents / 100).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €`;
}

function formatInputMoney(cents) {
    return (cents / 100).toFixed(2).replace('.', ',');
}

function emptyTotals() {
    return {cb: 0, especes: 0, cheque: 0, jetons: 0, bl: 0, enCompte: 0, contrat: 0, total: 0};
}

function calculateBornes(form) {
    form.querySelectorAll('[data-borne-total]').forEach((totalInput) => {
        if (!(totalInput instanceof HTMLInputElement)) return;
        const name = totalInput.dataset.borneTotal;
        const portique = form.querySelector(`#${CSS.escape(totalInput.dataset.portiqueInput ?? '')}`);
        const output = form.querySelector(`#${CSS.escape(totalInput.dataset.borneOutputInput ?? '')}`);
        if (!name || !(portique instanceof HTMLInputElement) || !(output instanceof HTMLInputElement)) return;
        if (totalInput.value.trim() === '') {
            output.value = '';
            return;
        }
        output.value = name === 'jetons'
            ? String(Math.trunc(decimalValue(totalInput) - decimalValue(portique)))
            : formatInputMoney(centsValue(totalInput) - centsValue(portique));
    });

    form.querySelectorAll('[data-borne-total-row]').forEach((row) => {
        let total = 0;
        row.querySelectorAll('[data-borne-total]').forEach((input) => {
            if (input instanceof HTMLInputElement && input.dataset.borneTotal !== 'jetons') total += centsValue(input);
        });
        const output = row.querySelector('[data-row-total]');
        if (output instanceof HTMLElement) output.textContent = formatMoney(total);
    });
}

function calculateRows(form) {
    const sections = new Map();
    const groups = new Map();

    form.querySelectorAll('[data-revenue-row]').forEach((row) => {
        const sectionName = row.dataset.revenueSection;
        if (!sectionName) return;
        const section = sections.get(sectionName) ?? emptyTotals();
        const groupName = row.dataset.equipmentGroup;
        const group = groupName ? (groups.get(groupName) ?? emptyTotals()) : null;
        let rowTotal = 0;

        row.querySelectorAll('[data-revenue-input]').forEach((input) => {
            if (!(input instanceof HTMLInputElement)) return;
            const field = input.dataset.revenueInput;
            if (!field) return;
            const value = field === 'jetons' ? Math.trunc(decimalValue(input)) : centsValue(input);
            section[field] += value;
            if (group) group[field] += value;
            if (MONEY_FIELDS.has(field)) rowTotal += value;
        });

        section.total += rowTotal;
        if (group) group.total += rowTotal;
        sections.set(sectionName, section);
        if (groupName && group) groups.set(groupName, group);
        const output = row.querySelector('[data-row-total]');
        if (output instanceof HTMLElement) output.textContent = formatMoney(rowTotal);
    });

    sections.forEach((totals, name) => updateTotalRow(form.querySelector(`[data-section-total="${name}"]`), totals));
    groups.forEach((totals, name) => updateTotalRow(form.querySelector(`[data-subtotal="${name}"]`), totals));
    const dayRevenue = [...sections.values()].reduce((sum, totals) => sum + totals.total, 0);
    const output = form.querySelector('[data-ca-day]');
    const outputHT = form.querySelector('[data-ca-day-ht]');
    if (output instanceof HTMLElement) output.textContent = formatMoney(dayRevenue);
    if (outputHT instanceof HTMLElement) outputHT.textContent = formatMoney(dayRevenue / 1.2);
}

function updateTotalRow(row, totals) {
    if (!(row instanceof HTMLElement)) return;
    row.querySelectorAll('[data-total-field]').forEach((cell) => {
        const field = cell.dataset.totalField;
        if (!field) return;
        cell.textContent = field === 'jetons' ? String(totals[field]) : formatMoney(totals[field]);
    });
}

function recalculate(form) {
    calculateBornes(form);
    calculateRows(form);
}

function initReleveCalculations(root = document) {
    root.querySelectorAll('[data-releve-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement) || form.dataset.calculationsInitialized === '1') return;
        form.dataset.calculationsInitialized = '1';
        form.addEventListener('input', () => recalculate(form));
        recalculate(form);
    });
}

document.addEventListener('DOMContentLoaded', () => initReleveCalculations());
document.addEventListener('turbo:load', () => initReleveCalculations());
document.addEventListener('station:details-loaded', (event) => initReleveCalculations(event.target));
