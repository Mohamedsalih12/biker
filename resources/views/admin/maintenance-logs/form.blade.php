@include('admin.maintenance-logs.table-feedback')
<p class="small text-muted">Cost is optional. The odometer is fetched from the selected vehicle and saved as a historical reading.</p>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="vehicle_id" class="form-label">Vehicle Registration (required)</label>
        <select id="vehicle_id" name="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror" data-lookup-url="{{ route('xpress.maintenance-logs.vehicles') }}" required aria-describedby="vehicle-help vehicle_id-error">
            <option value=""></option>
            @if ($selectedVehicle)
                <option value="{{ $selectedVehicle->id }}" selected>{{ $selectedVehicle->registration_number }}</option>
            @endif
        </select>
        <div id="vehicle-help" class="form-text">Select a vehicle by searching its registration number.</div>
        @error('vehicle_id') <div id="vehicle_id-error" class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="odometer_reading" class="form-label">Odometer Reading</label>
        <input type="text" id="odometer_reading" class="form-control" value="{{ $maintenanceLog->exists && $selectedVehicle?->id === $maintenanceLog->vehicle_id ? $maintenanceLog->odometer_reading : $selectedVehicle?->odometer_reading }}" readonly aria-describedby="odometer-help">
        <div id="odometer-help" class="form-text">Read-only snapshot; ordinary edits preserve the original reading. Blank means unknown.</div>
    </div>
    @foreach (['delivered_date' => 'Delivered Date', 'expected_completion_date' => 'Expected Completion Date'] as $field => $label)
        <div class="col-md-6 mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }} (required)</label>
            <input type="date" id="{{ $field }}" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror" value="{{ old($field, $maintenanceLog->{$field}?->format('Y-m-d')) }}" required aria-describedby="{{ $field }}-error">
            @error($field) <div id="{{ $field }}-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endforeach
    <div class="col-md-6 mb-3">
        <label for="cost" class="form-label">Cost (optional)</label>
        <input type="number" id="cost" name="cost" class="form-control @error('cost') is-invalid @enderror" value="{{ old('cost', $maintenanceLog->cost) }}" min="0" max="9999999999.99" step="0.01" aria-describedby="cost-error">
        @error('cost') <div id="cost-error" class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @foreach (['vendor_name' => ['Vendor', \App\Models\MaintenanceLog::VENDORS], 'status' => ['Status', \App\Models\MaintenanceLog::STATUSES]] as $field => [$label, $options])
        <div class="col-md-6 mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }} (required)</label>
            <select id="{{ $field }}" name="{{ $field }}" class="form-select mySelect2 @error($field) is-invalid @enderror" data-placeholder="Select {{ strtolower($label) }}" required aria-describedby="{{ $field }}-error">
                <option value=""></option>
                @foreach ($options as $option)
                    <option value="{{ $option }}" @selected(old($field, $maintenanceLog->{$field}) === $option)>{{ ucwords(str_replace('_', ' ', $option)) }}</option>
                @endforeach
            </select>
            @error($field) <div id="{{ $field }}-error" class="text-danger small">{{ $message }}</div> @enderror
        </div>
    @endforeach
    <div class="col-md-6 mb-3">
        <label for="maintenance_creator" class="form-label">Created By</label>
        <input type="text" id="maintenance_creator" class="form-control" value="{{ $maintenanceLog->user?->name ?? auth()->user()->name }}" readonly>
    </div>
    <div class="col-md-12 mb-3">
        <label for="service_description" class="form-label">Service Description (required)</label>
        <textarea id="service_description" name="service_description" class="form-control @error('service_description') is-invalid @enderror" rows="4" maxlength="10000" required aria-describedby="service_description-error">{{ old('service_description', $maintenanceLog->service_description) }}</textarea>
        @error('service_description') <div id="service_description-error" class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const vehicleSelect = $('#vehicle_id');
            const originalVehicleId = {{ Illuminate\Support\Js::from($maintenanceLog->vehicle_id) }};
            const originalOdometer = {{ Illuminate\Support\Js::from($maintenanceLog->odometer_reading) }};
            vehicleSelect.select2({
                placeholder: 'Select a vehicle', allowClear: true, width: '100%',
                ajax: {
                    url: vehicleSelect.data('lookup-url'), dataType: 'json', delay: 250,
                    data: function (params) { return { q: params.term || '', page: params.page || 1 }; },
                    processResults: function (data) { return data; }
                }
            });
            vehicleSelect.on('select2:select', function (event) {
                const vehicle = event.params.data;
                const reading = String(vehicle.id) === String(originalVehicleId) ? originalOdometer : vehicle.odometer_reading;
                $('#odometer_reading').val(reading ?? '');
            });
            vehicleSelect.on('select2:clear', function () { $('#odometer_reading').val(''); });
        });
    </script>
@endpush
