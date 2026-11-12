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
                <x-add-btn :routing="route('xpress.users.create')" />
                <x-bulk-delete
                    table-name="user-table"
                    :model="\App\Models\User::class"
                    button-id="users-bulk-delete"
                />
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <livewire:admin.user-table />
            </div>
        </div>
    </div>
@endsection
