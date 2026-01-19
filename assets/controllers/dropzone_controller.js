import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "area", "files"];

    connect() {
        this.areaTarget.addEventListener("click", () => this.inputTarget.click());

        this.areaTarget.addEventListener("dragover", (e) => this.onDragOver(e));
        this.areaTarget.addEventListener("dragleave", () => this.onDragLeave());
        this.areaTarget.addEventListener("drop", (e) => this.onDrop(e));

        this.inputTarget.addEventListener("change", () => this.onFilesSelected());
    }

    onDragOver(event) {
        event.preventDefault();
        this.areaTarget.classList.add("dragover");
    }

    onDragLeave() {
        this.areaTarget.classList.remove("dragover");
    }

    onDrop(event) {
        event.preventDefault();
        this.areaTarget.classList.remove("dragover");

        const files = Array.from(event.dataTransfer.files);
        this.inputTarget.files = event.dataTransfer.files;

        this.showFiles(files);
    }

    onFilesSelected() {
        const files = Array.from(this.inputTarget.files);
        this.showFiles(files);
    }

    showFiles(files) {
        this.filesTarget.innerHTML = "";

        files.forEach(file => {
            const el = document.createElement("div");
            el.textContent = file.name;
            this.filesTarget.appendChild(el);
        });
    }
}
