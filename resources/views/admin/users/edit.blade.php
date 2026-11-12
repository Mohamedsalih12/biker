@extends('layouts.xpress')
@section('title', 'Users Edit')
@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-session-message />
            <div class="card">
                <div class="card-header">
                    <h4>Edit User
                        <a href="{{ route('xpress.users.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="row">
                    <div class="card-body">
                        <form action="{{ route('xpress.users.update', $user) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Name (required)</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone (required)</label>
                                    <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" autocomplete="tel" required>
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-select mySelect2 @error('role') is-invalid @enderror" id="role" name="role" required>
                                        @foreach (['admin' => 'Admin', 'staff' => 'Staff', 'customer' => 'Customer'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('role') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="3" maxlength="10000" autocomplete="street-address">{{ old('address', $user->address) }}</textarea>
                                    @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="new-password" aria-describedby="password-help">
                                    <div id="password-help" class="form-text">Leave blank to keep the existing password.</div>
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="aadhaar" class="form-label">Aadhaar Number</label>
                                    <input type="text" class="form-control @error('aadhaar') is-invalid @enderror" id="aadhaar" name="aadhaar" maxlength="14" autocomplete="off">
                                    @error('aadhaar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="active_status" class="form-label">Active Status</label><br>
                                    <input type="hidden" name="active_status" value="0">
                                    <input type="checkbox" class="form-check-input" id="active_status" name="active_status" value="1" @checked(old('active_status', $user->active_status))>
                                    @error('active_status') <div class="text-danger small">{{ $message }}</div> @enderror
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
