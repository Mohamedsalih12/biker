@extends('layouts.xpress')
@section('title', 'Users Edit')
@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-session-message />
            <div class="card">
                <div class="card-header">
                    <h4>Edit User
                        <a href="{{ url('admin/users') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="row">
                    <div class="card-body">
                        <form action="{{ url('admin/users/' . $user->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="{{ $user->name }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" readonly class="form-control" id="email" name="email" readonly value="{{ $user->email }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="active_status" class="form-label">Active Status</label><br>
                                    <input type="hidden" name="active_status" value="0">
                                    <input type="checkbox" class="form-check-input" id="active_status" name="active_status" value="1" {{ $user->active_status ? 'checked' : '' }}>
                                </div>

                                <button type="submit" class="btn btn-warning">Update User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
