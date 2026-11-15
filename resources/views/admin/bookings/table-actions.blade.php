@can('manage-bookings')
    <div class="d-flex gap-1">
        <a href="{{ route('xpress.bookings.show', $booking) }}" class="btn btn-primary btn-sm">V</a>
        <a href="{{ route('xpress.bookings.agreement', $booking) }}" class="btn btn-info btn-sm">A</a>
        <a href="{{ route('xpress.bookings.edit', $booking) }}" class="btn btn-warning btn-sm">E</a>
        <form action="{{ route('xpress.bookings.destroy', $booking) }}" method="POST" onsubmit="return confirm('Delete this booking?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">D</button>
        </form>
    </div>
@endcan
