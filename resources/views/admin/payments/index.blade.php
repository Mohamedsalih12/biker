@extends('layouts.xpress')
@section('title', 'Payments List')
@section('content')
    <x-session-message />
    <div class="container-fluid">
        <div class="topbar">
            <div>
                <h3 class="mb-0">Payments</h3>
                <div class="small-muted">Data loads from Database table <b>Payments</b></div>
            </div>
            <div class="btn-group-top">
                <x-add-btn :routing="route('xpress.payments.create')" />
                <x-bulk-delete table-name="payment-table" :model="\App\Models\Payment::class" button-id="payments-bulk-delete" />
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <livewire:admin.payment-table />
            </div>
        </div>
    </div>
@endsection
