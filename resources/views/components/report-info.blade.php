
<div class="alert alert-info p-1">
    <small class="text-muted">
        @if(!empty($selection))
            <strong>
                {{ $label }} : ({{ count($selection) }}):
            </strong>

            {{ collect($selection)->take(3)->implode(', ') }}

            @if(count($selection) > 3)
                +{{ count($selection) - 3 }} more
            @endif
        @else No {{ $label }} selected
        @endif
    </small>    
</div>