@extends('layouts.frontend')

@section('content')

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body text-center p-5">
                    <i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
                    <h2 class="mt-4">Page Not Found</h2>
                    <p class="text-muted mt-3">
                        This page in under payment wall
                        <br>
                    </p>
                    <div class="mt-4">
                        <a href="{{ route('home') }}" class="btn btn-success">Available Pages</a>
                        <button type="button" class="btn btn-primary" onclick="history.back();">Back</button>
                        <a href="{{ route('logout') }}" class="btn btn-danger">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection