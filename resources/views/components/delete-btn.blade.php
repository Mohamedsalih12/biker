@can('deleting')
<button type="submit"
        class="no-border"
        onclick="return confirm('Are you sure you want to delete selected items?')">
    <i class="fa-solid fa-xmark" aria-hidden="true" style="color: #dc3545;"></i>
    DEL
</button>
@endcan