@extends('layouts.frontend')
@section('title', 'Change Password')

@section('content')

<div class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <x-session-message />
                <div class="card shadow">
                    <div class="card-header bg-primary">
                        <h4 class="mb-0 text-white">Change Password</h4>
                    </div>
                    <div class="alert alert-warning">
                        Your password was reset by an administrator. You must change it before continuing.
                    </div>
                    <div class="card-body">
                        <form action="{{ url('force-change-password') }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label">Current Temporary Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                                @error('current_password')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password" class="form-control" required>
                                @error('password')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>
                            <div class="mb-3 text-end">
                                <hr>
                                <button type="submit" class="btn btn-warning">Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection