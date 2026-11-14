@extends('layouts.xpress')
@section('title', 'All Bookings List')
@section('content')
    <x-session-message />
    <div class="container-fluid">
        <div class="topbar">
            <div>
                <h3 class="mb-0">All Bookings</h3>
                <div class="small-muted">Data loads from Database table <b>All Bookings</b></div>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <livewire:admin.all-booking-table />
            </div>
        </div>
    </div>
@endsection
