@extends('layouts.xpress')
@section('title', 'Users List')
@section('content')
    <x-session-message />
    @if(session('reset_password_once'))
        @php
            $reset = session('reset_password_once');
        @endphp

        <div class="alert alert-warning border-warning">
            <h5 class="mb-2">Temporary Password Generated</h5>
            <div><strong>User:</strong> {{ $reset['user_name'] }}</div>
            <div><strong>Email:</strong> {{ $reset['user_email'] }}</div>
            <div class="mt-2">
                <strong>Temporary Password:</strong>
                <code id="tempPassword">{{ $reset['temporary_password'] }}</code>
            </div>
            <div class="small text-danger mt-2">
                Copy this now. It is shown only once and is not stored in plain text.
            </div>

            <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="copyTempPassword()">
                Copy Password
            </button>
        </div>

        <script>
            function copyTempPassword() {
                const text = document.getElementById('tempPassword').innerText;
                navigator.clipboard.writeText(text).then(() => {
                    alert('Temporary password copied');
                });
            }
        </script>
    @endif
    
    <div class="container-fluid">
        <div class="topbar">
            <div>
                <h3 class="mb-0">Users</h3>
                <div class="small-muted">Data loads from Database table <b>Users</b></div>
            </div>

            <!-- Buttons -->
            <div class="btn-group-top">
                <x-add-btn :routing="url('admin/users/create')" />
                
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Active Status</th>
                            <th>Actions</th>
                            <th>Reset Password</th>
                            <th>Reset 2FA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td class="text-center" >{{ $user->id ?? null }}</td>
                                <td><a href="{{ route('xpress.users.edit', $user) }}"  @style(['color: #000', 'text-decoration: none']) >
                                        {{ $user->name }}
                                    </a>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    {{ $user->active_status ? 'Active' : 'Inactive' }}
                                </td>
                                <td class="text-center" >
                                    <x-edit-btn :routing="url('admin/users/' . $user->id . '/edit')" />
                                    <form action="{{ url('admin/users/' . $user->id . '/delete') }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')                                        
                                        <x-destroy-btn />
                                    </form>
                                </td>
                                <td>
                                    <form action="{{ route('users.reset-password.store', $user) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Reset password for {{ $user->name }}?')">
                                        @csrf
                                        @if(auth()->id() !== $user->id)
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            Reset Password
                                        </button>
                                        @endif
                                    </form>
                                </td>
                                <td>
                                    <form action="{{ route('users.reset-2fa.store', $user) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Reset 2FA for {{ $user->name }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">Reset 2FA</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No Users Available</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-3">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
