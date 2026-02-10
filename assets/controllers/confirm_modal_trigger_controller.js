import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
    }

    // Affiche la modal et empêche la soumission du formulaire pour demande de confirmation
    open(event) {
        event.preventDefault();

        const modalElement = document.querySelector('[data-controller="confirm-modal"]');
        if (!modalElement) return;

        const modalController = this.application.getControllerForElementAndIdentifier(modalElement, 'confirm-modal');
        if (!modalController) return;

        modalController.show(this.element);
    }
}
