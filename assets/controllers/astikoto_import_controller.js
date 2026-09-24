import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["centre", "content", "history"];

    connect() {
        this.refresh();
    }

    refresh() {
        const history = this.historyTargets.find(template => template.dataset.centreId === this.centreTarget.value);
        if (history) {
            this.contentTarget.replaceChildren(history.content.cloneNode(true));
        } else {
            this.contentTarget.textContent = "Sélectionnez une station pour afficher ses derniers imports.";
        }
    }
}
