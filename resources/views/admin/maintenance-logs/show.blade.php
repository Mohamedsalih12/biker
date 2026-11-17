@extends('layouts.xpress')
@section('title', 'Maintenance Log Details')
@section('content')
    <x-session-message />
    <div class="card">
        <div class="card-header">
            <h4>Maintenance Log Details
                <a href="{{ route('xpress.maintenance-logs.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
            </h4>
        </div>
        <div class="card-body">
            <dl class="row">
                @foreach (['ID' => $maintenanceLog->id, 'Vehicle Registration' => $maintenanceLog->vehicle->registration_number, 'Service Description' => $maintenanceLog->service_description, 'Delivered Date' => $maintenanceLog->delivered_date->format('Y-m-d'), 'Expected Completion Date' => $maintenanceLog->expected_completion_date->format('Y-m-d'), 'Cost' => $maintenanceLog->cost ?? '—', 'Odometer Reading' => $maintenanceLog->odometer_reading ?? '—', 'Vendor' => ucwords(str_replace('_', ' ', $maintenanceLog->vendor_name)), 'Status' => ucwords(str_replace('_', ' ', $maintenanceLog->status)), 'Created By' => $maintenanceLog->user->name] as $label => $value)
                    <dt class="col-sm-4">{{ $label }}</dt><dd class="col-sm-8 text-break">{{ $value }}</dd>
                @endforeach
            </dl>
            <a href="{{ route('xpress.maintenance-logs.edit', $maintenanceLog) }}" class="btn btn-warning">Edit Maintenance Log</a>
        </div>
    </div>
@endsection
