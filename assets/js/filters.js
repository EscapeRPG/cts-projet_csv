import {bindYearFilter, bindYearRangeFilter} from "./filters/year_filter.js";
import {openFilter} from "./filters/dropdowns.js";
import {initEncoursFilters} from "./filters/encours.js";
import {initSuiviFilters} from "./filters/suivi.js";
import {initPanelTools} from "./filters/panels.js";
import {destroyMobileFiltersDrawer, initMobileFiltersDrawer} from "./filters/mobile_drawer.js";

function initFiltersUi() {
    const filtersRoot = document.querySelector('.sidenav');
    if (!(filtersRoot instanceof HTMLElement)) return;
    if (filtersRoot.dataset.filtersInit === '1') return;
    filtersRoot.dataset.filtersInit = '1';

    filtersRoot.querySelectorAll('.dropdown-toggle').forEach((el) => {
        if (!(el instanceof HTMLElement)) return;
        el.addEventListener('click', () => openFilter(el));
    });

    const form = filtersRoot.querySelector('.filter-form');
    if (!(form instanceof HTMLFormElement)) return;

    const currentRoute = String(form.dataset.currentRoute ?? '');

    bindYearFilter(form);
    if (currentRoute === 'app_encours_bancaires') {
        bindYearRangeFilter(form);
    }
    initSuiviFilters(form, currentRoute);
    initEncoursFilters(filtersRoot, form, currentRoute);
}

function bootstrapFiltersUi() {
    initMobileFiltersDrawer();
    initFiltersUi();
    initPanelTools();
}

document.addEventListener('DOMContentLoaded', bootstrapFiltersUi);
document.addEventListener('turbo:load', bootstrapFiltersUi);
document.addEventListener('turbo:before-cache', destroyMobileFiltersDrawer);
document.addEventListener('suivi:results-updated', initPanelTools);
