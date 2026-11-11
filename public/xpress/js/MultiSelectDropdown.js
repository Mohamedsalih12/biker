// /assets/js/MultiSelectDropdown.js
export default class MultiSelectDropdown {

    constructor(options) {
        this.wrapper = document.querySelector(options.selector);
        if (!this.wrapper) throw new Error("MultiSelectDropdown: wrapper not found for " + options.selector);

        this.buttonLabel = options.buttonLabel || "Select Items";
        this.inputName = options.inputName || "selected_values";

        // Add a unique instance id (helps for multiple components)
        this.instanceId = options.instanceId || ('msd_' + Math.random().toString(36).slice(2,9));
        this.wrapper.dataset.instance = this.instanceId;

        // element refs (look inside this.wrapper only)
        this.btn = this.wrapper.querySelector(".msd-btn");
        this.search = this.wrapper.querySelector(".msd-search");
        this.selectAll = this.wrapper.querySelector(".msd-select-all");
        this.list = this.wrapper.querySelector(".msd-list");
        this.hiddenInput = this.wrapper.querySelector(".msd-hidden");
        this.okBtn = this.wrapper.querySelector(".msd-ok");
        this.cancelBtn = this.wrapper.querySelector(".msd-cancel");

        if (!this.btn || !this.list || !this.hiddenInput) {
            throw new Error("MultiSelectDropdown: required elements (btn/list/hidden) missing inside " + options.selector);
        }

        this.init();
    }

    init() {
        this.registerSearch();
        this.registerDelegatedCheckboxHandler();
        this.registerSelectAll();
        this.registerButtons();
        this.updateLabel();
        this.syncSelectAllState();
    }

    // --- Search filter (filters li elements)
    registerSearch() {
        if (!this.search) return;
        this.search.addEventListener("input", () => {
            const val = this.search.value.trim().toLowerCase();
            this.list.querySelectorAll("li").forEach(li => {
                const text = li.innerText.toLowerCase();
                li.style.display = text.includes(val) ? "" : "none";
            });
            // When filtering, keep Select All in sync with visible items
            this.syncSelectAllState();
        });
    }

    // --- Delegated handler for checkbox changes (works for dynamically added checkboxes)
    registerDelegatedCheckboxHandler() {
        this.list.addEventListener("change", (e) => {
            const target = e.target;
            if (target && target.classList && target.classList.contains("msd-check")) {
                this.updateLabel();
                this.syncSelectAllState();
            }
        });
    }

    // --- Select All: selects/deselects only visible checkboxes
    registerSelectAll() {
        if (!this.selectAll) return;
        this.selectAll.addEventListener("change", () => {
            const shouldCheck = !!this.selectAll.checked;
            // only affect visible checkboxes (honor filtering)
            this.list.querySelectorAll("li").forEach(li => {
                if (li.style.display === "none") return; // skip hidden
                const cb = li.querySelector(".msd-check");
                if (cb) cb.checked = shouldCheck;
            });
            this.updateLabel();
            this.syncSelectAllState();
        });
    }

    // --- Buttons: OK closes dropdown; Cancel clears visible selections
    registerButtons() {
        if (this.okBtn) {
            this.okBtn.addEventListener("click", () => {
                // close dropdown (simulate toggle)
                this.toggleDropdown();
            });
        }

        if (this.cancelBtn) {
            this.cancelBtn.addEventListener("click", () => {
                // uncheck visible ones and clear selectAll
                this.list.querySelectorAll("li").forEach(li => {
                    const cb = li.querySelector(".msd-check");
                    if (cb) cb.checked = false;
                });
                if (this.selectAll) {
                    this.selectAll.checked = false;
                    this.selectAll.indeterminate = false;
                }
                this.updateLabel();
                this.toggleDropdown();
            });
        }
    }

    // --- Update the main button label and hidden input
    updateLabel() {
        const allChecked = Array.from(this.list.querySelectorAll(".msd-check:checked")).map(cb => cb.value);
        if (allChecked.length === 0) {
            this.btn.innerText = this.buttonLabel;
        } else {
            this.btn.innerText = `${this.buttonLabel} (${allChecked.length} selected)`;
        }
        this.hiddenInput.value = allChecked.join(",");
    }

    // --- Make selectAll reflect the visible checkboxes state
    syncSelectAllState() {
        if (!this.selectAll) return;

        const visibleCheckboxes = Array.from(this.list.querySelectorAll("li"))
            .filter(li => li.style.display !== "none")
            .map(li => li.querySelector(".msd-check"))
            .filter(Boolean);

        if (visibleCheckboxes.length === 0) {
            this.selectAll.checked = false;
            this.selectAll.indeterminate = false;
            return;
        }

        const checkedCount = visibleCheckboxes.filter(cb => cb.checked).length;

        if (checkedCount === 0) {
            this.selectAll.checked = false;
            this.selectAll.indeterminate = false;
        } else if (checkedCount === visibleCheckboxes.length) {
            this.selectAll.checked = true;
            this.selectAll.indeterminate = false;
        } else {
            this.selectAll.checked = false;
            this.selectAll.indeterminate = true; // visual hint "some selected"
        }
    }

    // --- Helpful toggle (closes by clicking the toggle button)
    toggleDropdown() {
        // find the dropdown toggle inside this wrapper and click it
        const toggle = this.wrapper.querySelector("[data-bs-toggle='dropdown']");
        if (toggle) toggle.click();
    }

    // --- reset everything
    resetAll() {
        this.list.querySelectorAll(".msd-check").forEach(cb => cb.checked = false);
        if (this.selectAll) {
            this.selectAll.checked = false;
            this.selectAll.indeterminate = false;
        }
        this.updateLabel();
    }
}