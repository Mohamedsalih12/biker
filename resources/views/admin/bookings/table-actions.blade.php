@can('manage-bookings')
    <div class="d-flex gap-1">
        <a href="{{ route('xpress.bookings.show', $booking) }}" class="btn btn-primary btn-sm">View</a>
        <a href="{{ route('xpress.bookings.edit', $booking) }}" class="btn btn-warning btn-sm">Edit</a>
        <form action="{{ route('xpress.bookings.destroy', $booking) }}" method="POST" onsubmit="return confirm('Delete this booking?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
    </div>
@endcan
