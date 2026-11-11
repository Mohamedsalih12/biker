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
                    <div class="card-body">
                        <form action="{{ url('change-password') }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="form-control" />
                            </div>
                            <div class="mb-3">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control" />
                            </div>
                            <div class="mb-3">
                                <label>Confirm Password</label>
                                <input type="password" name="new_password_confirmation" class="form-control" />
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