@extends('layouts.xpress')
@section('title', 'Add Vehicle')
@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-session-message />
            <div class="card">
                <div class="card-header">
                    <h4>Add Vehicle
                        <a href="{{ route('xpress.vehicles.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('xpress.vehicles.store') }}" method="POST">
                        @csrf
                        @include('admin.vehicles.form', ['vehicle' => $vehicle])
                        <button type="submit" class="btn btn-primary">Add Vehicle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
