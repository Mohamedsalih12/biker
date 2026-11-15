@include('admin.payments.table-feedback')
<p class="small text-muted">All fields are required except notes. Customer and vehicle are filled from the selected booking.</p>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="booking_id" class="form-label">Booking (required)</label>
        <select id="booking_id" name="booking_id" class="form-select @error('booking_id') is-invalid @enderror" data-lookup-url="{{ route('xpress.payments.bookings') }}" required aria-describedby="booking-help booking_id-error">
            <option value=""></option>
            @if ($selectedBooking)
                <option value="{{ $selectedBooking->id }}" selected>{{ $selectedBooking->booking_code }} — {{ $selectedBooking->customer->name }} — {{ $selectedBooking->vehicle->registration_number }}</option>
            @endif
        </select>
        <div id="booking-help" class="form-text">Search by booking code, customer name, or vehicle registration.</div>
        @error('booking_id') <div id="booking_id-error" class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="payment_date" class="form-label">Payment Date (required)</label>
        <input type="date" id="payment_date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', $payment->payment_date?->format('Y-m-d')) }}" required aria-describedby="payment_date-error">
        @error('payment_date') <div id="payment_date-error" class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="amount_received" class="form-label">Amount Received (required)</label>
        <input type="number" id="amount_received" name="amount_received" class="form-control @error('amount_received') is-invalid @enderror" value="{{ old('amount_received', $payment->amount_received) }}" min="0.01" max="9999999999.99" step="0.01" required aria-describedby="amount_received-error">
        @error('amount_received') <div id="amount_received-error" class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="payment_method" class="form-label">Payment Method (required)</label>
        <select id="payment_method" name="payment_method" class="form-select mySelect2 @error('payment_method') is-invalid @enderror" data-placeholder="Select a payment method" required aria-describedby="payment_method-error">
            <option value=""></option>
            @foreach (\App\Models\Payment::METHODS as $method)
                <option value="{{ $method }}" @selected(old('payment_method', $payment->payment_method) === $method)>{{ $method === 'upi' ? 'UPI' : ucfirst($method) }}</option>
            @endforeach
        </select>
        @error('payment_method') <div id="payment_method-error" class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="customer_id" class="form-label">Customer (required)</label>
        <select id="customer_id" name="customer_id" class="form-select mySelect2 @error('customer_id') is-invalid @enderror" data-placeholder="Select a booking first" required aria-describedby="customer_id-error">
            <option value=""></option>
            @if ($selectedBooking)
                <option value="{{ $selectedBooking->customer_id }}" selected>{{ $selectedBooking->customer->name }}</option>
            @endif
        </select>
        @error('customer_id') <div id="customer_id-error" class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="vehicle_id" class="form-label">Vehicle Registration (required)</label>
        <select id="vehicle_id" name="vehicle_id" class="form-select mySelect2 @error('vehicle_id') is-invalid @enderror" data-placeholder="Select a booking first" required aria-describedby="vehicle_id-error">
            <option value=""></option>
            @if ($selectedBooking)
                <option value="{{ $selectedBooking->vehicle_id }}" selected>{{ $selectedBooking->vehicle->registration_number }}</option>
            @endif
        </select>
        @error('vehicle_id') <div id="vehicle_id-error" class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="payment_creator" class="form-label">Created By</label>
        <input type="text" id="payment_creator" class="form-control" value="{{ $payment->user?->name ?? auth()->user()->name }}" readonly>
    </div>
    <div class="col-md-12 mb-3">
        <label for="notes" class="form-label">Notes (optional)</label>
        <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" maxlength="10000" aria-describedby="notes-error">{{ old('notes', $payment->notes) }}</textarea>
        @error('notes') <div id="notes-error" class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const bookingSelect = $('#booking_id');
            bookingSelect.select2({
                placeholder: 'Select a booking', allowClear: true, width: '100%',
                ajax: {
                    url: bookingSelect.data('lookup-url'), dataType: 'json', delay: 250,
                    data: function (params) { return { q: params.term || '', page: params.page || 1 }; },
                    processResults: function (data) { return data; }
                }
            });
            function setLinkedField(selector, id, label) {
                $(selector).empty().append(new Option('', '', false, false));
                if (id) {
                    $(selector).append(new Option(label, id, true, true));
                }
                $(selector).trigger('change');
            }
            bookingSelect.on('select2:select', function (event) {
                const booking = event.params.data;
                setLinkedField('#customer_id', booking.customer_id, booking.customer_name);
                setLinkedField('#vehicle_id', booking.vehicle_id, booking.vehicle_registration);
            });
            bookingSelect.on('select2:clear', function () {
                setLinkedField('#customer_id', null, '');
                setLinkedField('#vehicle_id', null, '');
            });
        });
    </script>
@endpush
