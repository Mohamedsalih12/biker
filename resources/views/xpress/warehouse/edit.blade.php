
@extends('layouts.xpress')
@section('title', 'Edit Warehouse')
@section('content')

    <div class="container-fluid">
        <form method="POST" action="{{ url('xpress/warehouse/' . $warehouse->id) }}">
            @csrf
            @method('PUT')
            <div class="card shadow-sm">
                <div class="card-header bg-primary">
                    <h4 class="text-white">Edit Warehouse
                        <a href="{{ url('xpress/warehouse') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">
                    <x-session-message />

                    <x-searchable3-dropdown column="name" label="Username" fk="user_id"       :items="$userNames"  :arrayval="$warehouse" />
                    <x-text-box name="warehouse_code"           label="WH Code"  :arrayval="$warehouse" />
                    <x-text-box name="warehouse_name"           label="Warehouse Name"  :arrayval="$warehouse" />  
                    <x-boolean name="active_status"             label="Active Status" option1="Active" option2="Inactive" :arrayval="$warehouse" />
                </div>
            </div>
            <button type="submit" class="btn btn-warning w-100 py-2">UPDATE</button>
        </form>
    </div>
@endsection
