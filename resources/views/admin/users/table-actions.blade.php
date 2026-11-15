@can('manage-users')
    @if ($type === 'edit-delete')
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('xpress.users.edit', $user) }}" class="btn btn-warning btn-sm">E</a>
            <form action="{{ route('xpress.users.destroy', $user) }}" method="POST"
                onsubmit="return confirm('Delete this user?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">D</button>
            </form>
        </div>
    @elseif ($type === 'reset-password' && auth()->id() !== $user->id)
        <form action="{{ route('users.reset-password.store', $user) }}" method="POST"
            onsubmit="return confirm('Reset the password for this user?')">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm">Reset</button>
        </form>
    @elseif ($type === 'reset-2fa')
        <form action="{{ route('users.reset-2fa.store', $user) }}" method="POST"
            onsubmit="return confirm('Reset 2FA for this user?')">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm">Reset 2FA</button>
        </form>
    @endif
@endcan
