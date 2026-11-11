@extends('layouts.xpress')
@section('title', 'Warehouse')
@section('subTitle', '')
@section('searchBox', 'Search by Warehouse Name')

@section('content')
<div class="container-fluid">
    <div class="topbar">
        <div>
            <h3 class="mb-0">Warehouse</h3>
            <div class="small-muted">Data loads from Database table <b>Warehouse</b></div>
        </div>
        <div class="btn-group-top">
            <x-add-btn :routing="url('xpress/warehouse/create')" />
            <x-bulk-delete
                table-name="warehouse-table"
                :model="\App\Models\Xpress\Warehouse::class"
                button-id="warehouse-bulk-delete"
            />
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <x-session-message />
            <livewire:xpress.warehouse-table />
        </div>
    </div>
</div>

@endsection
