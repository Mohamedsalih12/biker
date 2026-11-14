@extends('layouts.xpress')
@section('title', 'Booking Details')
@section('content')
    <x-session-message />
    <div class="card">
        <div class="card-header">
            <h4>Booking Details
                <a href="{{ route('xpress.bookings.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
            </h4>
        </div>
        <div class="card-body">
            <dl class="row">
                @foreach (['Booking Code' => $booking->booking_code, 'Customer' => $booking->customer->name, 'Vehicle Registration' => $booking->vehicle->registration_number, 'Created By' => $booking->user->name] as $label => $value)
                    <dt class="col-sm-4">{{ $label }}</dt><dd class="col-sm-8 text-break">{{ $value }}</dd>
                @endforeach
                @foreach (['rental_type' => 'Rental Type', 'rate_per_unit' => 'Rate per Unit', 'start_date' => 'Start Date', 'end_date' => 'End Date', 'actual_return_date' => 'Actual Return Date', 'start_odometer' => 'Start Odometer (km)', 'end_odometer' => 'End Odometer (km)', 'booking_status' => 'Booking Status', 'payment_status' => 'Payment Status', 'handover_notes' => 'Handover Notes', 'return_notes' => 'Return Notes'] as $field => $label)
                    <dt class="col-sm-4">{{ $label }}</dt>
                    <dd class="col-sm-8 text-break">
                        @if (in_array($field, ['start_date', 'end_date', 'actual_return_date'], true))
                            {{ $booking->{$field}?->format('Y-m-d') ?? '—' }}
                        @elseif (in_array($field, ['booking_status', 'payment_status', 'rental_type'], true))
                            {{ ucfirst($booking->{$field}) }}
                        @else
                            {{ $booking->{$field} ?? '—' }}
                        @endif
                    </dd>
                @endforeach
            </dl>
            <a href="{{ route('xpress.bookings.edit', $booking) }}" class="btn btn-warning">Edit Booking</a>
        </div>
    </div>
@endsection
