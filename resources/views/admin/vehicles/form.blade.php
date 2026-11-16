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
        <label for="rate_per_unit" class="form-label">Rate Per Unit</label>
        <input type="number" class="form-control @error('rate_per_unit') is-invalid @enderror" id="rate_per_unit" name="rate_per_unit" value="{{ old('rate_per_unit', $vehicle->rate_per_unit) }}" min="0" max="9999999999.99" step="1">
        @error('rate_per_unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
        <input type="number" class="form-control @error('purchase_cost') is-invalid @enderror" id="purchase_cost" name="purchase_cost" value="{{ old('purchase_cost', $vehicle->purchase_cost) }}" min="0" max="9999999999.99" step="1">
        @error('purchase_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="insurance_expires_at" class="form-label">Insurance Expires At</label>
        <input type="date" class="form-control @error('insurance_expires_at') is-invalid @enderror" id="insurance_expires_at" name="insurance_expires_at" value="{{ old('insurance_expires_at', $vehicle->insurance_expires_at?->format('Y-m-d')) }}">
        @error('insurance_expires_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 mb-3">
        <fieldset>
            <legend class="fs-6">Vehicle Pictures</legend>
            <label for="images" class="form-label">Add pictures</label>
            <input type="file" class="form-control @error('images') is-invalid @enderror" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp" aria-describedby="images-help">
            <div id="images-help" class="form-text">Select up to 20 JPG, PNG or WebP pictures per upload, up to 2 MB each. New pictures are added to the existing gallery. The first picture is the cover; the fleet shows the first six.</div>
            @foreach (['images', 'images.*', 'replacement_images', 'replacement_images.*', 'remove_images', 'remove_images.*', 'image'] as $errorKey)
                @foreach ($errors->get($errorKey) as $messages)
                    @foreach ((array) $messages as $message)
                        <div class="text-danger small" role="alert">{{ $message }}</div>
                    @endforeach
                @endforeach
            @endforeach
            <div class="row g-3 mt-1">
                @forelse ($vehicle->imagePaths() as $path)
                    @php($photoKey = hash('sha256', $path))
                    <div class="col-12 col-sm-6 col-lg-4">
                        <figure class="mb-2">
                            <a href="{{ asset('storage/'.ltrim($path, '/')) }}" target="_blank" rel="noopener">
                                <img src="{{ asset('storage/'.ltrim($path, '/')) }}" alt="Picture {{ $loop->iteration }} of {{ $vehicle->registration_number }}" class="img-thumbnail" width="160" height="120" style="object-fit: contain" loading="lazy">
                            </a>
                            <figcaption class="small mt-1">Picture {{ $loop->iteration }}{{ $loop->first ? ' (cover)' : '' }}</figcaption>
                        </figure>
                        <label for="replace-{{ $photoKey }}" class="form-label small">Replace picture {{ $loop->iteration }}</label>
                        <input type="file" class="form-control form-control-sm" id="replace-{{ $photoKey }}" name="replacement_images[{{ $photoKey }}]" accept="image/jpeg,image/png,image/webp" aria-describedby="images-help">
                        <div class="form-check mt-2">
                            <input type="checkbox" class="form-check-input" id="remove-{{ $photoKey }}" name="remove_images[]" value="{{ $photoKey }}" @checked(in_array($photoKey, old('remove_images', []), true))>
                            <label for="remove-{{ $photoKey }}" class="form-check-label">Remove picture {{ $loop->iteration }} on save</label>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No pictures yet.</p>
                @endforelse
            </div>
            <p class="form-text mb-0 mt-2">Replacements and removals take effect when you save the vehicle. Leave a picture unchanged to keep it.</p>
        </fieldset>
    </div>
    <div class="col-12 mb-3">
        <label for="damages" class="form-label">Damages</label>
        <textarea class="form-control @error('damages') is-invalid @enderror" id="damages" name="damages" rows="4" maxlength="10000" aria-describedby="damages-help">{{ old('damages', $vehicle->damages) }}</textarea>
        <div id="damages-help" class="form-text">Describe any existing damage. Leave blank if there is nothing to record.</div>
        @error('damages') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
