import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['overlay', 'modal'];

    connect() {
        requestAnimationFrame(() => {
            this.overlayTarget.classList.add('is-visible');
        });

        this.onOutsideClick = this.onOutsideClick.bind(this);
        document.addEventListener('click', this.onOutsideClick);
    }

    // Cache la modal
    hide() {
        this.overlayTarget.classList.remove('is-visible');

        setTimeout(() => {
            this.element.remove();
        }, 250);
    }

    // Permet de cacher la modal en cliquant en dehors
    onOutsideClick(event) {
        if (!this.modalTarget.contains(event.target)) {
            this.hide();
        }
    }
}
