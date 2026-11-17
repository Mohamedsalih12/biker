@extends('layouts.xpress')
@section('title', 'Add Maintenance Log')
@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-session-message />
            <div class="card">
                <div class="card-header">
                    <h4>Add Maintenance Log
                        <a href="{{ route('xpress.maintenance-logs.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('xpress.maintenance-logs.store') }}" method="POST">
                        @csrf
                        @include('admin.maintenance-logs.form')
                        <button type="submit" class="btn btn-primary">Add Maintenance Log</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
