@extends('layouts.xpress')
@section('title', 'Edit Maintenance Log')
@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-session-message />
            <div class="card">
                <div class="card-header">
                    <h4>Edit Maintenance Log
                        <a href="{{ route('xpress.maintenance-logs.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('xpress.maintenance-logs.update', $maintenanceLog) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('admin.maintenance-logs.form')
                        <button type="submit" class="btn btn-primary">Update Maintenance Log</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
