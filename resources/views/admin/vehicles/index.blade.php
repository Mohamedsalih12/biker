@extends('layouts.xpress')
@section('title', 'Vehicles List')
@section('content')
    <x-session-message />
    <div class="container-fluid">
        <div class="topbar">
            <div>
                <h3 class="mb-0">Vehicles</h3>
                <div class="small-muted">Data loads from Database table <b>Vehicles</b></div>
            </div>
            <div class="btn-group-top">
                <x-add-btn :routing="route('xpress.vehicles.create')" />
                <x-bulk-delete table-name="vehicle-table" :model="\App\Models\Vehicle::class" button-id="vehicles-bulk-delete" />
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <livewire:admin.vehicle-table />
            </div>
        </div>
    </div>
@endsection
