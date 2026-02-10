import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['overlay', 'modal'];

    connect() {
        this.form = null;
        this.onOutsideClick = this.onOutsideClick.bind(this);
    }

    // Affiche une modal
    show(form) {
        this.form = form;
        this.overlayTarget.classList.add('is-visible');

        setTimeout(() => {
            document.addEventListener('click', this.onOutsideClick);
        }, 0);
    }

    // Cache une modal
    hide() {
        this.overlayTarget.classList.remove('is-visible');
        this.form = null;

        document.removeEventListener('click', this.onOutsideClick);
    }

    // Demande confirmation de l'action avant de soumettre le formulaire
    confirm() {
        if (this.form) {
            this.form.submit();
        }
    }

    // Permet de fermer la modal en cliquant en dehors
    onOutsideClick(event) {
        if (!this.modalTarget.contains(event.target)) {
            this.hide();
        }
    }
}
