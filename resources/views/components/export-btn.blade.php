@can('exporting')
    <a href="{{ $routing }}" 
        class="btn btn-secondary btn-sm border-0"
        onclick="this.classList.add('disabled'); this.innerText='Exported...';"
    >
    EXPORT
    </a>
@endcan