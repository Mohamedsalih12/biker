@extends('layouts.xpress')
@section('title', 'Vehicle Details')
@section('content')
    <x-session-message />
    <div class="card">
        <div class="card-header">
            <h4>Vehicle Details
                <a href="{{ route('xpress.vehicles.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
            </h4>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 mb-3">
                @forelse ($vehicle->imagePaths() as $path)
                    <figure class="mb-0">
                        <a href="{{ asset('storage/'.ltrim($path, '/')) }}" target="_blank" rel="noopener">
                            <img src="{{ asset('storage/'.ltrim($path, '/')) }}" alt="Picture {{ $loop->iteration }} of {{ $vehicle->registration_number }}" class="img-thumbnail" width="240" height="180" style="object-fit: contain" loading="lazy">
                        </a>
                        <figcaption class="small mt-1">Picture {{ $loop->iteration }}{{ $loop->first ? ' (cover)' : '' }}</figcaption>
                    </figure>
                @empty
                    <span class="text-muted">No pictures yet.</span>
                @endforelse
            </div>
            <dl class="row">
                @foreach ([
                    'registration_number' => 'Registration Number', 'brand' => 'Brand', 'model' => 'Model',
                    'chassis_number' => 'Chassis Number', 'battery_serial_number' => 'Battery Serial Number',
                    'odometer_reading' => 'Odometer Reading (km)', 'rate_per_unit' => 'Rate Per Unit', 'status' => 'Status',
                    'purchase_date' => 'Purchase Date', 'purchase_cost' => 'Purchase Cost',
                    'insurance_policy_number' => 'Insurance Policy Number', 'insurance_expires_at' => 'Insurance Expires At',
                    'damages' => 'Damages',
                ] as $field => $label)
                    <dt class="col-sm-4">{{ $label }}</dt>
                    <dd class="col-sm-8 text-break">
                        @if (in_array($field, ['purchase_date', 'insurance_expires_at'], true))
                            {{ $vehicle->{$field}?->format('Y-m-d') ?? '—' }}
                        @elseif ($field === 'status')
                            {{ ucfirst($vehicle->current_status) }}
                        @elseif ($field === 'damages')
                            <div class="text-break" style="white-space: pre-wrap">{{ $vehicle->damages ?? '—' }}</div>
                        @else
                            {{ $vehicle->{$field} ?? '—' }}
                        @endif
                    </dd>
                @endforeach
            </dl>
            <a href="{{ route('xpress.vehicles.edit', $vehicle) }}" class="btn btn-warning">Edit Vehicle</a>
        </div>
    </div>
@endsection
