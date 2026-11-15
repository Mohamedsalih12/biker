@can('manage-vehicles')
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('xpress.vehicles.show', $vehicle) }}" class="btn btn-primary btn-sm">V</a>
        <a href="{{ route('xpress.vehicles.edit', $vehicle) }}" class="btn btn-warning btn-sm">E</a>
        <form action="{{ route('xpress.vehicles.destroy', $vehicle) }}" method="POST" onsubmit="return confirm('Delete this vehicle?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">D</button>
        </form>
    </div>
@endcan
