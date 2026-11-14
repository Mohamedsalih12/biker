@extends('layouts.xpress')
@section('title', 'Edit Booking')
@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-session-message />
            <div class="card">
                <div class="card-header">
                    <h4>Edit Booking
                        <a href="{{ route('xpress.bookings.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('xpress.bookings.update', $booking) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('admin.bookings.form')
                        <button type="submit" class="btn btn-primary">Update Booking</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @include('admin.bookings.quick-add-customer')
@endsection
