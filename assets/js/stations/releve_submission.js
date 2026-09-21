function createButton(label, className, attributes = {}) {
    const button = document.createElement('button');
    button.type = attributes.type ?? 'button';
    button.className = className;
    button.textContent = label;

    Object.entries(attributes).forEach(([name, value]) => {
        if (name !== 'type') button.setAttribute(name, value);
    });

    return button;
}

async function replaceDayDetails(url, options = {}) {
    const container = document.querySelector('[data-day-details]');
    if (!(container instanceof HTMLElement)) return;

    container.classList.add('is-loading');
    try {
        const response = await fetch(url, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            ...options,
        });
        container.innerHTML = await response.text();
        container.dispatchEvent(new CustomEvent('station:details-loaded', {bubbles: true}));
    } finally {
        container.classList.remove('is-loading');
    }
}

function synchronizeActions(root = document) {
    const state = root.querySelector('[data-releve-state]');
    const actions = document.querySelector('[data-releve-page-actions]');
    if (!(state instanceof HTMLElement) || !(actions instanceof HTMLElement)) return;

    actions.replaceChildren();

    if (state.dataset.releveEditing === '1') {
        actions.append(
            createButton('Enregistrer', 'btn-link', {
                type: 'submit',
                form: 'releve-journalier-form',
                name: 'intent',
                value: 'save',
                'data-releve-intent': 'save',
            }),
            createButton('Valider', 'btn-link add', {
                type: 'submit',
                form: 'releve-journalier-form',
                name: 'intent',
                value: 'validate',
                'data-releve-intent': 'validate',
            }),
        );
        return;
    }

    const editButton = createButton('Modifier', 'btn-link edit', {'data-edit-releve': ''});
    editButton.addEventListener('click', () => {
        const editUrl = state.dataset.releveEditUrl;
        if (editUrl) replaceDayDetails(editUrl);
    });
    actions.append(editButton);
}

function synchronizeActivity(root = document) {
    const state = root.querySelector('[data-releve-state]');
    const activity = document.querySelector('[data-releve-activity]');
    if (!(state instanceof HTMLElement) || !(activity instanceof HTMLElement)) return;

    activity.textContent = state.dataset.releveActivity ?? '';
}

function synchronizePage(root = document) {
    synchronizeActions(root);
    synchronizeActivity(root);
}

async function submitReleve(form, intent) {
    const data = new FormData(form);
    data.set('intent', intent);
    await replaceDayDetails(form.action, {method: 'POST', body: data});
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-releve-intent]');
    if (!(button instanceof HTMLButtonElement)) return;

    const form = button.form;
    if (form instanceof HTMLFormElement) {
        form.dataset.submitIntent = button.dataset.releveIntent ?? 'save';
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-releve-form]')) return;

    event.preventDefault();
    const submitterIntent = event.submitter instanceof HTMLButtonElement
        ? event.submitter.dataset.releveIntent
        : undefined;
    const intent = submitterIntent ?? form.dataset.submitIntent ?? 'save';
    delete form.dataset.submitIntent;

    submitReleve(form, intent).catch(() => {
        window.alert('Impossible d’enregistrer le relevé.');
    });
});

document.addEventListener('DOMContentLoaded', () => synchronizePage());
document.addEventListener('turbo:load', () => synchronizePage());
document.addEventListener('station:details-loaded', (event) => synchronizePage(event.target));
