import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["container", "burger", "menu"];

    connect() {
        this.outsideClick = this.outsideClick.bind(this);
    }

    // Appelle les fonctions d'ouverture et de fermeture du menu
    menu() {
        if (this.burgerTarget.className === '') {
            this.show();
        } else {
            this.hide();
        }
    }

    // Affiche le menu
    show() {
        this.containerTarget.classList.add('open');
        this.burgerTarget.className = 'open';
        this.menuTarget.className = 'open';
        document.addEventListener('click', this.outsideClick)
    }

    // Cache le menu
    hide() {
        this.containerTarget.classList.remove('open');
        this.burgerTarget.className = '';
        this.menuTarget.className = '';
        document.removeEventListener('click', this.outsideClick)
    }

    // Permet de cacher le menu en cliquant en dehors
    outsideClick(event) {
        if (!this.menuTarget.contains(event.target) && !this.element.contains(event.target)) {
            this.hide()
        }
    }
}
