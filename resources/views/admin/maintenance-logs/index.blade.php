@extends('layouts.xpress')
@section('title', 'Maintenance Logs List')
@section('content')
    <x-session-message />
    <div class="container-fluid">
        <div class="topbar">
            <div>
                <h3 class="mb-0">Maintenance Logs</h3>
                <div class="small-muted">Data loads from Database table <b>Maintenance Logs</b></div>
            </div>
            <div class="btn-group-top">
                <x-add-btn :routing="route('xpress.maintenance-logs.create')" />
                <x-bulk-delete table-name="maintenance-log-table" :model="\App\Models\MaintenanceLog::class" button-id="maintenance-logs-bulk-delete" />
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <livewire:admin.maintenance-log-table />
            </div>
        </div>
    </div>
@endsection
