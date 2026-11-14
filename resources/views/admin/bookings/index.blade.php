@extends('layouts.xpress')
@section('title', 'Bookings List')
@section('content')
    <x-session-message />
    <div class="container-fluid">
        <div class="topbar">
            <div>
                <h3 class="mb-0">Bookings</h3>
                <div class="small-muted">Data loads from Database table <b>Bookings</b></div>
            </div>
            <div class="btn-group-top">
                <x-add-btn :routing="route('xpress.bookings.create')" />
                <x-bulk-delete table-name="booking-table" :model="\App\Models\Booking::class" button-id="bookings-bulk-delete" />
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <livewire:admin.booking-table />
            </div>
        </div>
    </div>
@endsection
