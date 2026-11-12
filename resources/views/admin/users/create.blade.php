@extends('layouts.xpress')
@section('title', 'Add User')
@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-session-message />
            <div class="card">
                <div class="card-header">
                    <h4>Add User
                        <a href="{{ route('xpress.users.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('xpress.users.store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Name (required)</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone (required)</label>
                                <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" required>
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select mySelect2 @error('role') is-invalid @enderror" id="role" name="role" required>
                                    @foreach (['admin' => 'Admin', 'staff' => 'Staff', 'customer' => 'Customer'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('role', 'customer') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('role') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="3" maxlength="10000" autocomplete="street-address">{{ old('address') }}</textarea>
                                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="new-password" aria-describedby="password-help">
                                <div id="password-help" class="form-text">Optional. Email and password are both needed to sign in.</div>
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="aadhaar" class="form-label">Aadhaar Number</label>
                                <input type="text" class="form-control @error('aadhaar') is-invalid @enderror" id="aadhaar" name="aadhaar" maxlength="14" autocomplete="off">
                                @error('aadhaar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">Add User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
