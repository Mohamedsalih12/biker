@can('manage-maintenance-logs')
    <div class="d-flex gap-1">
        <a href="{{ route('xpress.maintenance-logs.show', $maintenanceLog) }}" class="btn btn-primary btn-sm">View</a>
        <a href="{{ route('xpress.maintenance-logs.edit', $maintenanceLog) }}" class="btn btn-warning btn-sm">Edit</a>
        <form action="{{ route('xpress.maintenance-logs.destroy', $maintenanceLog) }}" method="POST" onsubmit="return confirm('Delete this maintenance log?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
    </div>
@endcan
