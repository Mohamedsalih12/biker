@extends('layouts.xpress')
@section('title', 'Add Payment')
@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-session-message />
            <div class="card">
                <div class="card-header">
                    <h4>Add Payment
                        <a href="{{ route('xpress.payments.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('xpress.payments.store') }}" method="POST">
                        @csrf
                        @include('admin.payments.form')
                        <button type="submit" class="btn btn-primary">Add Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
