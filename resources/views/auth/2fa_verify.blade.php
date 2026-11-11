@extends('layouts.frontend')

@section('content')
    <div class="py-3"
     style="
        background-image: url('{{ asset('storage/uploads/xpress/backgrounds/1.jpg') }}');
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
        min-height: 93vh;
     ">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header"><h2>Two-Factor Authentication</h2></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('2fa') }}">
                                @csrf
                                <div class="form-group">
                                    <p>Please enter the  <strong>OTP</strong> generated on your Authenticator App. <br> Ensure you submit the current one because it refreshes every 30 seconds.</p>
                                    <label for="one_time_password">Enter the 6-digit OTP from your app:</label>
                                    <input type="number" name="one_time_password" class="form-control" required>
                                </div>
                                @if (count($errors) > 0)
                                @foreach ($errors->all() as $error)
                                    <p class="text-danger">{{ $error }}</p>
                                @endforeach
                                @endif
                                <button class="btn btn-primary mt-3" type="submit">Verify</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection