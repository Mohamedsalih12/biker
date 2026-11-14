@if ($vehicle->image)
    <div class="d-flex flex-column align-items-start gap-2">
        <a href="{{ $vehicle->imageUrl() }}" target="_blank" rel="noopener">
            <img src="{{ $vehicle->imageUrl() }}" alt="Photo of {{ $vehicle->registration_number }}" class="img-thumbnail" width="96" height="72" style="object-fit: cover" loading="lazy">
        </a>
        @can('update', $vehicle)
            <button type="button" class="btn btn-outline-danger btn-sm"
                wire:click="removePhoto({{ $vehicle->id }})"
                wire:confirm="Remove this vehicle photo? This cannot be undone."
                wire:loading.attr="disabled" wire:target="removePhoto({{ $vehicle->id }})">
                Remove photo
            </button>
        @endcan
    </div>
@else
    <span class="text-muted">No photo</span>
@endif
