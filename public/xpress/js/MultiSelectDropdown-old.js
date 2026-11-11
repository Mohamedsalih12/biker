export default class MultiSelectDropdown {

    constructor(options) {
        this.wrapper = document.querySelector(options.selector);
        this.buttonLabel = options.buttonLabel || "Select Items";
        this.inputName = options.inputName || "selected_values";

        // internal selectors
        this.btn = this.wrapper.querySelector(".msd-btn");
        this.search = this.wrapper.querySelector(".msd-search");
        this.selectAll = this.wrapper.querySelector(".msd-select-all");
        this.list = this.wrapper.querySelector(".msd-list");
        this.hiddenInput = this.wrapper.querySelector(".msd-hidden");
        this.okBtn = this.wrapper.querySelector(".msd-ok");
        this.cancelBtn = this.wrapper.querySelector(".msd-cancel");

        this.init();
    }

    init() {
        this.registerSearch();
        this.registerSelectAll();
        this.registerCheckboxEvents();
        this.registerButtons();
        this.updateLabel();
    }

    registerSearch() {
        this.search.addEventListener("keyup", () => {
            const val = this.search.value.toLowerCase();
            this.list.querySelectorAll("li").forEach(li => {
                li.style.display = li.innerText.toLowerCase().includes(val) ? "" : "none";
            });
        });
    }

    registerSelectAll() {
        this.selectAll.addEventListener("change", () => {
            this.list.querySelectorAll(".msd-check").forEach(cb => cb.checked = this.selectAll.checked);
            this.updateLabel();
        });
    }

    registerCheckboxEvents() {
        this.list.querySelectorAll(".msd-check").forEach(cb => {
            cb.addEventListener("change", () => this.updateLabel());
        });
    }

    registerButtons() {
        // OK → close dropdown only
        this.okBtn.addEventListener("click", () => {
            this.btn.click();
        });

        // CANCEL → reset + close
        this.cancelBtn.addEventListener("click", () => {
            this.resetAll();
            this.btn.click();
        });
    }

    updateLabel() {
        const checked = this.list.querySelectorAll(".msd-check:checked");
        this.hiddenInput.value = [...checked].map(c => c.value).join(",");

        if (checked.length === 0) {
            this.btn.innerText = this.buttonLabel;
        } else {
            this.btn.innerText = `${this.buttonLabel} (${checked.length} selected)`;
        }
    }

    resetAll() {
        this.list.querySelectorAll(".msd-check").forEach(cb => cb.checked = false);
        this.selectAll.checked = false;
        this.updateLabel();
    }
}