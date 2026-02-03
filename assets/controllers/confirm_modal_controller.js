import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['overlay', 'modal'];

    connect() {
        this.form = null;
        this.onOutsideClick = this.onOutsideClick.bind(this);
    }

    show(form) {
        this.form = form;
        this.overlayTarget.classList.add('is-visible');

        setTimeout(() => {
            document.addEventListener('click', this.onOutsideClick);
        }, 0);
    }

    hide() {
        this.overlayTarget.classList.remove('is-visible');
        this.form = null;

        document.removeEventListener('click', this.onOutsideClick);
    }


    confirm() {
        if (this.form) {
            this.form.submit();
        }
    }

    onOutsideClick(event) {
        if (!this.modalTarget.contains(event.target)) {
            this.hide();
        }
    }
}
