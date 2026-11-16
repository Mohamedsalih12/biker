@if ($vehicle->imagePaths() !== [])
    <div class="d-flex flex-wrap gap-2" style="min-width: 8rem; max-width: 22rem;">
    @foreach (array_slice($vehicle->imagePaths(), 0, 1) as $path)
        @php($photoKey = hash('sha256', $path))
        <div class="d-flex flex-column align-items-start gap-2" wire:key="vehicle-photo-{{ $vehicle->id }}-{{ $photoKey }}">
            <a href="{{ asset('storage/'.ltrim($path, '/')) }}" target="_blank" rel="noopener">
                <img src="{{ asset('storage/'.ltrim($path, '/')) }}" alt="Picture {{ $loop->iteration }} of {{ $vehicle->registration_number }}" class="img-thumbnail" width="96" height="72" style="object-fit: cover" loading="lazy">
            </a>
            @can('update', $vehicle)
                <button type="button" class="btn btn-outline-danger btn-sm"
                    wire:click="removePhoto({{ $vehicle->id }}, '{{ $photoKey }}')"
                    wire:confirm="Remove this vehicle photo? This cannot be undone."
                    wire:loading.attr="disabled" wire:target="removePhoto"
                    aria-label="Remove photo {{ $loop->iteration }} of {{ $vehicle->registration_number }}">
                    Remove photo
                </button>
            @endcan
        </div>
    @endforeach
    </div>
@else
    <span class="text-muted">No photo</span>
@endif
