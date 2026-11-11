@props([
    'tableName',
    'eventName' => 'bulkDelete',
    'ability' => 'delete',
    'model',
    'buttonId' => null,
    'label' => 'Bulk delete',
    'confirmation' => 'Delete the selected items?',
])

@php($buttonId ??= $tableName . '-bulk-delete')

@can($ability, $model)
    <button
        {{ $attributes->merge(['id' => $buttonId, 'class' => 'btn btn-danger btn-sm']) }}
        type="button"
        x-data="{
            tableName: @js($tableName),
            eventName: @js($eventName),
            label: @js($label),
            confirmation: @js($confirmation),
            get selectedCount() {
                return $store.pgBulkActions.count(this.tableName);
            },
            deleteSelected() {
                if (this.selectedCount > 0 && window.confirm(this.confirmation)) {
                    Livewire.dispatch(this.eventName);
                }
            }
        }"
        disabled
        x-bind:disabled="selectedCount === 0"
        x-on:click="deleteSelected()"
    >
        <i class="fa fa-trash" aria-hidden="true"></i>
        <span x-text="label"></span>
        (<span x-text="selectedCount"></span>)
    </button>
@endcan
