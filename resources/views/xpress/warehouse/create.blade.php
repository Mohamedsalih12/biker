@extends('layouts.xpress')
@section('title', 'Add Warehouse')
@section('content')

    <div class="container-fluid">
        <form method="POST" action="{{ url('xpress/warehouse') }}">
            @csrf
            <div class="card shadow-sm">
                <div class="card-header bg-primary">
                    <h4 class="text-white">Add Warehouse
                        <a href="{{ url('xpress/warehouse') }}" class="btn btn-primary btn-sm text-white float-end">
                            Back
                        </a>
                    </h4>
                </div>
                <div class="card-body">
                    <x-session-message />

                    <x-searchable3-dropdown column="name" label="Username" fk="user_id"              :items="$userNames" />
                    <x-text-box name="warehouse_code"           label="WH Code" />
                    <x-text-box name="warehouse_name"           label="Warehouse Name" />  
                    <x-boolean name="active_status"             label="Active Status" option1="Active" option2="Inactive"/>
                </div>
            </div>
            <button type="submit" class="btn btn-success w-100 py-2">ADD</button>
        </form>
    </div>
@endsection
