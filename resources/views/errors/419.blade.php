@extends('layouts.frontend')

@section('content')

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body text-center p-5">
                    <i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
                    <h2 class="mt-4">Session Token Expired (419)</h2>
                    <div class="mt-4">
                        <a href="{{ route('login') }}" class="btn btn-secondary">Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection