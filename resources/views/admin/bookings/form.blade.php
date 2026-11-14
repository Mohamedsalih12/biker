@include('admin.bookings.table-feedback')
<p class="small text-muted">Required fields are marked (required). Return details can be added later.</p>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="booking_code" class="form-label">Booking Code</label>
        <input type="text" id="booking_code" class="form-control" value="{{ $booking->booking_code ?? 'Auto-generated on save' }}" readonly>
    </div>
    <div class="col-md-6 mb-3">
        <label for="booking_creator" class="form-label">Created By</label>
        <input type="text" id="booking_creator" class="form-control" value="{{ $booking->user?->name ?? auth()->user()->name }}" readonly>
    </div>
    <div class="col-md-6 mb-3">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <label for="customer_id" class="form-label mb-0">Customer (required)</label>
            @can('create', \App\Models\User::class)
                <button type="button" class="btn btn-outline-primary btn-sm" id="quick-add-customer-button" data-bs-toggle="modal" data-bs-target="#quick-add-customer-modal">Quick Add</button>
            @endcan
        </div>
        <select id="customer_id" name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" data-lookup-url="{{ route('xpress.booking-customers.index') }}" required aria-describedby="customer-help customer_id-error">
            <option value=""></option>
            @if ($selectedCustomer)
                <option value="{{ $selectedCustomer->id }}" selected>{{ $selectedCustomer->aadhaar ?: 'Aadhaar unavailable' }} — {{ $selectedCustomer->name }}</option>
            @endif
        </select>
        <div id="customer-help" class="form-text">Search by Aadhaar or name. Use Quick Add if the customer is not listed.</div>
        @error('customer_id') <div id="customer_id-error" class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="vehicle_id" class="form-label">Vehicle Registration Number (required)</label>
        <select id="vehicle_id" name="vehicle_id" class="form-select mySelect2 @error('vehicle_id') is-invalid @enderror" data-placeholder="Select a vehicle" required aria-describedby="vehicle_id-error">
            <option value=""></option>
            @foreach ($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $booking->vehicle_id) === (string) $vehicle->id)>{{ $vehicle->registration_number }}</option>
            @endforeach
        </select>
        @error('vehicle_id') <div id="vehicle_id-error" class="text-danger small">{{ $message }}</div> @enderror
    </div>
    @foreach (['rental_type' => ['Rental Type', \App\Models\Booking::RENTAL_TYPES, 'monthly'], 'status' => ['Status', \App\Models\Booking::STATUSES, 'pending']] as $field => [$label, $options, $default])
        <div class="col-md-6 mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }} (required)</label>
            <select id="{{ $field }}" name="{{ $field }}" class="form-select mySelect2 @error($field) is-invalid @enderror" required aria-describedby="{{ $field }}-error">
                @foreach ($options as $option)
                    <option value="{{ $option }}" @selected(old($field, $booking->{$field} ?? $default) === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
            @error($field) <div id="{{ $field }}-error" class="text-danger small">{{ $message }}</div> @enderror
        </div>
    @endforeach
    <div class="col-md-6 mb-3">
        <label for="rate_per_unit" class="form-label">Rate per Unit (required)</label>
        <input type="number" id="rate_per_unit" name="rate_per_unit" class="form-control @error('rate_per_unit') is-invalid @enderror" value="{{ old('rate_per_unit', $booking->rate_per_unit) }}" min="0.01" max="9999999999.99" step="0.01" required aria-describedby="rate-help rate_per_unit-error">
        <div id="rate-help" class="form-text">Rate per day, week, or month according to the rental type.</div>
        @error('rate_per_unit') <div id="rate_per_unit-error" class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @foreach (['start_date' => 'Start Date', 'end_date' => 'End Date', 'actual_return_date' => 'Actual Return Date'] as $field => $label)
        <div class="col-md-6 mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }} {{ $field === 'actual_return_date' ? '(optional)' : '(required)' }}</label>
            <input type="date" id="{{ $field }}" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror" value="{{ old($field, $booking->{$field}?->format('Y-m-d')) }}" @required($field !== 'actual_return_date') aria-describedby="{{ $field }}-error">
            @error($field) <div id="{{ $field }}-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endforeach
    @foreach (['start_odometer' => 'Start Odometer', 'end_odometer' => 'End Odometer'] as $field => $label)
        <div class="col-md-6 mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }} (km) {{ $field === 'end_odometer' ? '(optional)' : '(required)' }}</label>
            <input type="number" id="{{ $field }}" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror" value="{{ old($field, $booking->{$field}) }}" min="0" max="9223372036854775807" step="1" @required($field === 'start_odometer') aria-describedby="{{ $field }}-error">
            @error($field) <div id="{{ $field }}-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endforeach
    @foreach (['handover_notes' => 'Handover Notes', 'return_notes' => 'Return Notes'] as $field => $label)
        <div class="col-md-6 mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }} (optional)</label>
            <textarea id="{{ $field }}" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror" rows="3" maxlength="10000" aria-describedby="{{ $field }}-error">{{ old($field, $booking->{$field}) }}</textarea>
            @error($field) <div id="{{ $field }}-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endforeach
</div>
<div id="booking-customer-added" class="alert alert-success d-none" role="status"></div>
