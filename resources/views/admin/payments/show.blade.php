@extends('layouts.xpress')
@section('title', 'Payment Details')
@section('content')
    <x-session-message />
    <div class="card">
        <div class="card-header">
            <h4>Payment Details
                <a href="{{ route('xpress.payments.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
            </h4>
        </div>
        <div class="card-body">
            <dl class="row">
                @foreach (['ID' => $payment->id, 'Booking Code' => $payment->booking->booking_code, 'Payment Date' => $payment->payment_date->format('Y-m-d'), 'Amount Received' => $payment->amount_received, 'Payment Method' => strtoupper($payment->payment_method), 'Customer' => $payment->customer->name, 'Vehicle Registration' => $payment->vehicle->registration_number, 'Created By' => $payment->user->name, 'Notes' => $payment->notes ?? '—'] as $label => $value)
                    <dt class="col-sm-4">{{ $label }}</dt><dd class="col-sm-8 text-break">{{ $value }}</dd>
                @endforeach
            </dl>
            <a href="{{ route('xpress.payments.edit', $payment) }}" class="btn btn-warning">Edit Payment</a>
        </div>
    </div>
@endsection
