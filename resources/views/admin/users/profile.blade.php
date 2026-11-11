@extends('layouts.xpress')

@section('title', 'User Profile')

@section('content')
    @php($profileUser = $user ?? Auth::user())

    <div class="py-5">
        <div class="container">                   
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <h4>User Profile
                        <a href="{{ route('xpress.change-password-index') }}" class="btn btn-warning btn-sm float-end px-1 py-1">
                            Change Password
                        </a>
                        <a href="{{ url()->previous() }}" class="btn btn-primary btn-sm float-end px-1 py-1 me-2">
                            Back
                        </a>
                    </h4>
                    <div class="underline mb-4"></div>
                </div>
                <div class="col-md-10">
                    <x-session-message />
                    
                    <div class="card-shadow">
                        <div class="card-header bg-primary text-white mb-3 py-1 px-1">
                            {{ $profileUser->name }} - User Profile
                        </div>
                        <div class="card-body">
                            <form action="{{ isset($user) ? route('xpress.adminprofile.update', $user) : route('xpress.profile.update') }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Name</label>
                                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $profileUser->name) }}" required maxlength="255">
                                            @error('name')
                                                <div class="text-danger">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" readonly class="form-control" id="email" name="email" value="{{ $profileUser->email }}">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-warning">Update Profile</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
