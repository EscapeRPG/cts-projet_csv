import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["btn", "panel", "count", "item", "emptyState"];

    connect() {
        this.outsideClick = this.outsideClick.bind(this);
    }

    notifications() {
        if (!this.btnTarget.classList.contains('open')) {
            this.show();
        } else {
            this.hide();
        }
    }

    // Affiche le menu
    show() {
        this.btnTarget.classList.add('open');
        this.panelTarget.classList.add('open');
        document.addEventListener('click', this.outsideClick)
    }

    // Cache le menu
    hide() {
        this.btnTarget.classList.remove('open');
        this.panelTarget.classList.remove('open');
        document.removeEventListener('click', this.outsideClick)
    }

    // Permet de cacher le menu en cliquant en dehors
    outsideClick(event) {
        if (!this.panelTarget.contains(event.target) && !this.element.contains(event.target)) {
            this.hide()
        }
    }

    async markAsRead(event) {
        event.preventDefault();

        const button = event.currentTarget;
        const response = await fetch(button.dataset.url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': button.dataset.token,
            },
        });

        if (!response.ok) {
            return;
        }

        const item = button.closest('.notif-item');
        if (item) {
            item.remove();
        }

        this.updateCount();
        this.toggleEmptyState();
    }

    updateCount() {
        if (!this.hasCountTarget) {
            return;
        }

        const remaining = this.itemTargets.length;

        if (remaining > 0) {
            this.countTarget.textContent = remaining.toString();
            this.countTarget.classList.add('active');
            return;
        }

        this.countTarget.textContent = '';
        this.countTarget.classList.remove('active');
    }

    toggleEmptyState() {
        if (!this.hasEmptyStateTarget) {
            return;
        }

        this.emptyStateTarget.hidden = this.itemTargets.length > 0;
    }
}
