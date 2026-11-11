<div class="dropdown" wire:ignore.self x-data="{ search: '' }">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start"
            type="button" data-bs-toggle="dropdown">Select
    </button>

        <div class="dropdown-menu p-3" style="width:260px; max-height:350px; overflow-y:auto" @click.stop>

        <!-- ACTION BUTTONS -->
        <div class="d-flex gap-2 mb-2">
            <button type="button" class="btn btn-sm btn-outline-primary" @click="selectAll()">Select All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearAll()">Clear</button>
        </div>

        <input type="search" class="form-control form-control-sm mb-2"
               placeholder="Search..." aria-label="Search options" x-model="search" autocomplete="off">

        <hr class="my-2">

        <!-- CHECKBOX LIST -->
        <div style="max-height:500px; overflow-y:auto">
            
            <template x-for="(item,index) in items.filter(item => String(item).toLowerCase().includes(search.toLowerCase()))" :key="item + index + items.length">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" :value="item" x-model="selected">
                    <label class="form-check-label" x-text="item"></label>
                </div>
            </template>

            <div class="small text-muted py-2" x-show="items.filter(item => String(item).toLowerCase().includes(search.toLowerCase())).length === 0">
                No matches found.
            </div>
        </div>
        <hr>
        <!-- ACTIONS -->
        <div class="d-flex justify-content-between">
            <button class="btn btn-primary btn-sm" wire:click="filterData" 
                @click="bootstrap.Dropdown.getInstance($root.querySelector('.dropdown-toggle')).hide();">
                Apply Filters
            </button>
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="dropdown"
                @click="bootstrap.Dropdown.getInstance($root.querySelector('.dropdown-toggle')).hide();">
                Cancel
            </button>
        </div>
    </div>
</div>