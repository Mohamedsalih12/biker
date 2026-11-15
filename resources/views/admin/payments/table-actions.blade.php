@can('manage-payments')
    <div class="d-flex gap-1">
        <a href="{{ route('xpress.payments.show', $payment) }}" class="btn btn-primary btn-sm">View</a>
        <a href="{{ route('xpress.payments.edit', $payment) }}" class="btn btn-warning btn-sm">Edit</a>
        <form action="{{ route('xpress.payments.destroy', $payment) }}" method="POST" onsubmit="return confirm('Delete this payment?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
    </div>
@endcan
