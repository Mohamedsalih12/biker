@extends('layouts.xpress')
@section('title', 'Edit Payment')
@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-session-message />
            <div class="card">
                <div class="card-header">
                    <h4>Edit Payment
                        <a href="{{ route('xpress.payments.index') }}" class="btn btn-primary btn-sm text-white float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('xpress.payments.update', $payment) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('admin.payments.form')
                        <button type="submit" class="btn btn-primary">Update Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
