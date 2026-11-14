<div class="row">
    @foreach ([
        'registration_number' => 'Registration Number',
        'brand' => 'Brand',
        'model' => 'Model',
        'chassis_number' => 'Chassis Number',
        'battery_serial_number' => 'Battery Serial Number',
        'insurance_policy_number' => 'Insurance Policy Number',
    ] as $field => $label)
        <div class="col-md-6 mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }}{{ $field === 'registration_number' ? ' (required)' : '' }}</label>
            <input type="text" class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $vehicle->{$field}) }}" maxlength="255" @required($field === 'registration_number') aria-describedby="{{ $field }}-error">
            @error($field) <div id="{{ $field }}-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endforeach
    <div class="col-md-6 mb-3">
        <label for="odometer_reading" class="form-label">Odometer Reading (km)</label>
        <input type="number" class="form-control @error('odometer_reading') is-invalid @enderror" id="odometer_reading" name="odometer_reading" value="{{ old('odometer_reading', $vehicle->odometer_reading) }}" min="0" max="9223372036854775807" step="1">
        @error('odometer_reading') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="status" class="form-label">Status</label>
        <select class="form-select mySelect2 @error('status') is-invalid @enderror" id="status" name="status" required>
            @foreach (\App\Models\Vehicle::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $vehicle->status ?? 'available') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('status') <div class="text-danger small">{{ $message }}</div> @enderror
        <div class="form-text">The latest booking determines the displayed status. Maintenance and decommissioned override bookings; this value is used when there are no bookings.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="purchase_date" class="form-label">Purchase Date</label>
        <input type="date" class="form-control @error('purchase_date') is-invalid @enderror" id="purchase_date" name="purchase_date" value="{{ old('purchase_date', $vehicle->purchase_date?->format('Y-m-d')) }}">
        @error('purchase_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="purchase_cost" class="form-label">Purchase Cost</label>
        <input type="number" class="form-control @error('purchase_cost') is-invalid @enderror" id="purchase_cost" name="purchase_cost" value="{{ old('purchase_cost', $vehicle->purchase_cost) }}" min="0" max="9999999999.99" step="0.01">
        @error('purchase_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="insurance_expires_at" class="form-label">Insurance Expires At</label>
        <input type="date" class="form-control @error('insurance_expires_at') is-invalid @enderror" id="insurance_expires_at" name="insurance_expires_at" value="{{ old('insurance_expires_at', $vehicle->insurance_expires_at?->format('Y-m-d')) }}">
        @error('insurance_expires_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="image" class="form-label">Vehicle Photo</label>
        <input type="file" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/jpeg,image/png,image/webp" aria-describedby="image-help">
        <div id="image-help" class="form-text">Optional JPG, PNG or WebP, up to 2 MB. Choose a new photo to replace the current one.</div>
        @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @if ($vehicle->image)
            <img src="{{ $vehicle->imageUrl() }}" alt="Current photo of {{ $vehicle->registration_number }}" class="img-thumbnail mt-2" width="160" loading="lazy">
        @endif
    </div>
    <div class="col-12 mb-3">
        <label for="damages" class="form-label">Damages</label>
        <textarea class="form-control @error('damages') is-invalid @enderror" id="damages" name="damages" rows="4" maxlength="10000" aria-describedby="damages-help">{{ old('damages', $vehicle->damages) }}</textarea>
        <div id="damages-help" class="form-text">Describe any existing damage. Leave blank if there is nothing to record.</div>
        @error('damages') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
