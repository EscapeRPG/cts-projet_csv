import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["burger", "menu"];

    connect() {
        this.outsideClick = this.outsideClick.bind(this);
    }

    menu() {
        if (this.burgerTarget.className === '') {
            this.show();
        } else {
            this.hide();
        }
    }

    show() {
        this.burgerTarget.className = 'open';
        this.menuTarget.className = 'open';
        document.addEventListener('click', this.outsideClick)
    }

    hide() {
        this.burgerTarget.className = '';
        this.menuTarget.className = '';
        document.removeEventListener('click', this.outsideClick)
    }

    outsideClick(event) {
        if (!this.menuTarget.contains(event.target) && !this.element.contains(event.target)) {
            this.hide()
        }
    }
}
