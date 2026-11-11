    // --- REUSABLE FUNCTION TO INITIALIZE FILTERS ---
    // The core logic remains, but the naming and strings are updated.
    function initializeFilter(filterName, checkSelector, dropdownBtnId, searchInputId, selectAllId, cancelBtnId) {
        const dropdownBtn = document.getElementById(dropdownBtnId);
        const searchInput = document.getElementById(searchInputId);
        const selectAllCheck = document.getElementById(selectAllId);
        const cancelBtn = document.getElementById(cancelBtnId);
        const allChecks = document.querySelectorAll(checkSelector);

        // Defensive: if required DOM is missing, bail out silently
        if (!dropdownBtn || !searchInput || !selectAllCheck || !cancelBtn) {
            return;
        }

        // 1. DYNAMIC LABEL UPDATE (Simplified string output)
        function updateLabel() {
            const checks = document.querySelectorAll(checkSelector + ":checked");
            // Dynamically set the text based on the filterName parameter (e.g., "Dropdown 1" or "Dropdown 2")
            const labelText = `Dropdown ${filterName.slice(-1)} Codes`;
            
            if (checks.length === 0) {
                dropdownBtn.innerText = `Select ${labelText}`;
            } else {
                dropdownBtn.innerText = `Select ${labelText} (${checks.length} selected)`;
            }
        }

        // 2. SEARCH FILTER (Uses generic list item selector)
        if (searchInput) {
            searchInput.addEventListener("keyup", function () {
            const searchValue = this.value.toLowerCase();
            // Uses filterName to build the list item class name (e.g., 'dropdown1-item')
            const listItemSelector = filterName + '-item'; 
            const items = document.querySelectorAll(`.${listItemSelector}`);

            items.forEach(item => {
                const label = item.innerText.toLowerCase();
                item.style.display = label.includes(searchValue) ? "" : "none";
            });
            });
        }

        // 3. SELECT ALL/NONE LOGIC
        if (selectAllCheck) {
            selectAllCheck.addEventListener("change", function () {
                allChecks.forEach(ch => ch.checked = this.checked);
                updateLabel();
            });
        }

        // 4. INDIVIDUAL CHECKBOX CHANGE
        if (allChecks && allChecks.length) {
            allChecks.forEach(ch => {
                ch.addEventListener("change", updateLabel);
            });
        }

        // 5. CANCEL BUTTON
        if (cancelBtn) {
            cancelBtn.addEventListener("click", function () {
                allChecks.forEach(ch => ch.checked = false);
                if (selectAllCheck) selectAllCheck.checked = false;
                updateLabel();
                try {
                    const toggle = this.closest && this.closest('.dropdown') && this.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]');
                    if (toggle && typeof toggle.click === 'function') toggle.click();
                } catch (e) {
                    // ignore
                }

                // Specific action for Dropdown 1 (clearing session)
                if (filterName === 'dropdown1') {
                    try { fetch("../api/filters/unset_pos_code.php", { method: "POST" }); } catch (e) {}
                }
            });
        }

        // Initial load call
        updateLabel();
    }