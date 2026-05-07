/**
 * Toggles a dropdown filter and closes others.
 *
 * @param {HTMLElement} element
 * @returns {void}
 */
export function openFilter(element) {
    const openedFilters = document.querySelectorAll('.filter-open');

    openedFilters.forEach((el) => {
        if (el !== element) {
            el.classList.remove('filter-open');
        }
    });

    const filtersMenu = element.nextElementSibling;

    if (element.classList.contains('filter-open')) {
        element.classList.remove('filter-open');
        filtersMenu?.classList?.remove('filter-open');
    } else {
        element.classList.add('filter-open');
        filtersMenu?.classList?.add('filter-open');
    }
}

