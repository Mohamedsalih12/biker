@extends('layouts.xpress')
@section('title', 'Edit User')
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
                <div class="card-body">
                    <form action="{{ route('xpress.users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('admin.users.form', ['user' => $user])
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
