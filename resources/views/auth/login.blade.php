@extends('layouts.frontend')

@section('title', 'Sign in — Biker')

@section('content')
@php
    $t360BrandPanel = [
        ['fas fa-boxes', 'Fleet availability', 'Check which bikes are available, booked or ready for their next ride.'],
        ['fas fa-arrow-circle-up', 'Bike bookings', 'Manage customer bookings, rental dates and bike assignments.'],
        ['fas fa-file-invoice', 'Rental payments', 'Record rental payments and review payment details for each booking.'],
        ['fas fa-file-contract', 'Rental agreements', 'Prepare rental agreements with customer, bike and rental details.'],
    ];
@endphp
<style>
    .t360-auth {
        display: flex;
        min-height: 100vh;
        font-family: "Instrument Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* ---------- Brand panel ---------- */
    .t360-auth-brand {
        flex: 0 0 44%;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 3.4rem 3rem;
        color: #fff;
        background:
            radial-gradient(720px 480px at 18% 108%, rgba(8, 123, 81, .32), transparent 62%),
            radial-gradient(560px 420px at 86% -12%, rgba(8, 123, 81, .12), transparent 60%),
            linear-gradient(165deg, #163f31 0%, #18372e 70%, #153d2e 100%);
        overflow: hidden;
    }

    .t360-auth-brand::after {
        content: "";
        position: absolute;
        inset: 0;
        background-image: radial-gradient(rgba(255, 255, 255, .075) 1.1px, transparent 1.1px);
        background-size: 22px 22px;
        mask-image: radial-gradient(ellipse at 30% 40%, rgba(0,0,0,.85), transparent 78%);
        -webkit-mask-image: radial-gradient(ellipse at 30% 40%, rgba(0,0,0,.85), transparent 78%);
        pointer-events: none;
    }

    .t360-auth-brand > * { position: relative; z-index: 1; }

    .t360-auth-logo {
        display: inline-flex;
        align-items: center;
        gap: .85rem;
    }

    .t360-auth-logo .logo-chip {
        background: #fff;
        border-radius: 14px;
        padding: .55rem .8rem;
        box-shadow: 0 10px 30px -10px rgba(0,0,0,.55);
    }

    .t360-auth-logo img { height: 34px; display: block; }
    .t360-auth-logo .brand-word { font-size: 1.02rem; font-weight: 600; letter-spacing: .02em; }
    .t360-auth-logo .brand-word b { color: #c3ed91; font-weight: 700; }

    .t360-auth-headline {
        font-size: clamp(1.9rem, 3.2vw, 2.7rem);
        font-weight: 700;
        line-height: 1.16;
        letter-spacing: -0.02em;
        margin: 2rem 0 1rem;
    }

    .t360-auth-headline em {
        font-style: normal;
        color: #c3ed91;
        white-space: nowrap;
    }

    .t360-auth-sub {
        color: #d0e1d6;
        font-size: 1.02rem;
        max-width: 34ch;
        margin-bottom: 0;
    }

    .t360-features { list-style: none; padding: 0; margin: 2.2rem 0 0; }

    .t360-features li {
        display: flex;
        align-items: flex-start;
        gap: .9rem;
        padding: .8rem 0;
        border-top: 1px solid rgba(255, 255, 255, .07);
    }

    .t360-features i {
        flex: 0 0 2.4rem;
        height: 2.4rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
        color: #c3ed91;
        background: rgba(8, 123, 81, .16);
        border: 1px solid rgba(195, 237, 145, .3);
        border-radius: 10px;
    }

    .t360-features .f-title { font-weight: 600; font-size: .95rem; }
    .t360-features .f-text { color: #d0e1d6; font-size: .82rem; }
    .t360-auth-meta { color: #b7cbc0; font-size: .8rem; }

    /* ---------- Form panel ---------- */
    .t360-auth-form-panel {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem;
        background:
            radial-gradient(600px 380px at 100% 0%, rgba(8, 123, 81, .05), transparent 65%),
            #f4f7f4;
    }

    .t360-auth-form { width: min(400px, 100%); }

    .t360-auth-form h1 {
        font-size: 1.65rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: #18372e;
        margin-bottom: .35rem;
    }

    .t360-auth-form .lead-sm { color: #5b6c65; font-size: .95rem; margin-bottom: 2rem; }

    .t360-field { margin-bottom: 1.15rem; }

    .t360-field label {
        font-size: .8rem;
        font-weight: 600;
        color: #415b4e;
        letter-spacing: .02em;
        margin-bottom: .45rem;
        display: block;
    }

    .t360-field .t360-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
        gap: .7rem;
    }

    .t360-field .t360-input-wrap i.lead-icon {
        flex: 0 0 1.4rem;
        margin-left: .35rem;
        color: #65786a;
        font-size: .95rem;
        text-align: center;
        pointer-events: none;
    }

    .t360-field input.form-control, .t360-field .form-control {
        flex: 1 1 auto;
        min-width: 0;
        height: 47px;
        padding: 0 1rem;
        border-radius: 12px;
        border: 1.5px solid #b7cbc0;
        background: #fff;
        font-size: .96rem;
        transition: border-color .15s ease, box-shadow .15s ease;
        width: 100%;
    }

    .t360-field input.form-control.is-invalid, .t360-field .form-control.is-invalid {
        border-color: #d31145;
    }

    .t360-field.has-eye input.form-control { padding-right: 2.6rem; }

    .t360-field input.form-control::placeholder { color: #65786a; }

    .t360-field input.form-control:focus, .t360-field .form-control:focus {
        border-color: #087b51;
        box-shadow: 0 0 0 .28rem rgba(8, 123, 81, .1);
        outline: 0;
    }

    #togglePassword {
        position: absolute;
        right: .5rem;
        top: 0;
        bottom: 0;
        margin: auto 0;
        height: 37px;
        width: 37px;
        border: 0;
        background: transparent;
        color: #65786a;
        border-radius: 9px;
        z-index: 2;
    }

    #togglePassword:hover { background: #edf5ed; color: #415b4e; }

    .t360-auth-error {
        border: 1px solid rgba(211, 17, 69, .3);
        background: rgba(211, 17, 69, .06);
        color: #a30d37;
        border-radius: 12px;
        padding: .6rem .9rem;
        font-size: .85rem;
    }

    .t360-auth-submit {
        width: 100%;
        height: 47px;
        border: 0;
        border-radius: 12px;
        background: linear-gradient(140deg, #087b51, #065b3c);
        color: #fff;
        font-weight: 600;
        font-size: 1rem;
        box-shadow: 0 14px 26px -12px rgba(8, 123, 81, .65);
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        cursor: pointer;
    }

    .t360-auth-submit:hover {
        transform: translateY(-1px);
        filter: brightness(1.05);
        box-shadow: 0 18px 30px -12px rgba(8, 123, 81, .7);
    }

    .t360-auth-submit:active { transform: translateY(0); }

    .invalid-feedback { color: #a30d37; }

    .t360-auth-card-note {
        margin-top: 1.6rem;
        text-align: center;
        color: #65786a;
        font-size: .82rem;
    }

    .t360-auth-card-note b { color: #415b4e; }

    /* ---------- Responsive ---------- */
    @media (max-width: 991.98px) {
        .t360-auth-brand { flex: 0 0 40%; padding: 2.4rem 2rem; }
        .t360-features li:nth-child(n+3) { display: none; }
    }

    @media (max-width: 767.98px) {
        .t360-auth { flex-direction: column; min-height: 100dvh; }
        .t360-auth-brand {
            flex: none;
            padding: 2rem 1.6rem 2.2rem;
        }
        .t360-features, .t360-auth-meta { display: none; }
        .t360-auth-headline { font-size: 1.55rem; margin-top: 1.4rem; }
        .t360-auth-form-panel { padding: 2rem 1.25rem 3rem; }
    }
</style>

<div class="t360-auth">
    {{-- Brand panel --}}
    <aside class="t360-auth-brand">
        <div class="t360-auth-logo">
            <span class="logo-chip">
                <img src="{{ asset('admin/images/GMBLogo.png') }}" alt="GULF MB logo">
            </span>
            <span class="brand-word">Biker<b>.</b></span>
        </div>

        <div>
            <h1 class="t360-auth-headline">
                Your bike rental business — <em>ready to ride.</em>
            </h1>
            <p class="t360-auth-sub">One sign-in for your fleet, customer bookings, rental payments and agreements.</p>

            <ul class="t360-features">
                @foreach ($t360BrandPanel as [$icon, $title, $text])
                    <li>
                        <i class="{{ $icon }}"></i>
                        <span>
                            <span class="f-title d-block">{{ $title }}</span>
                            <span class="f-text d-block">{{ $text }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="t360-auth-meta">Biker · Bike Rental Management</div>
    </aside>

    {{-- Sign-in panel --}}
    <main class="t360-auth-form-panel">
        <div class="t360-auth-form">
            <h1>Sign in</h1>
            <p class="lead-sm">Use your Biker account. Contact your administrator if you are locked out.</p>

            @if ($errors->any())
                <div class="t360-auth-error mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="t360-field">
                    <label for="email">Email address</label>
                    <div class="t360-input-wrap">
                        <i class="fas fa-envelope lead-icon"></i>
                        <input id="email" type="text"
                               class="form-control @error('email') is-invalid @enderror"
                               name="email" value="{{ old('email') }}"
                               required autocomplete="email" autofocus
                               placeholder="you@example.com">
                        @error('email')
                            <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>

                <div class="t360-field has-eye">
                    <label for="password">Password</label>
                    <div class="t360-input-wrap">
                        <i class="fas fa-shield-alt lead-icon" id="t360PasswordLock"></i>
                        <input id="password" type="password"
                               class="form-control @error('password') is-invalid @enderror"
                               name="password" required autocomplete="current-password"
                               placeholder="••••••••">
                        <button class="btn" type="button" id="togglePassword" aria-label="Show or hide password">
                            <i class="bi bi-eye"></i>
                        </button>

                        @error('password')
                            <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="t360-auth-submit">
                    Sign in <i class="fas fa-arrow-right ms-2" id="t360SubmitArrow"></i>
                </button>
            </form>

            <div class="t360-auth-card-note">
                <i class="fas fa-lock me-1"></i> Secured with two-factor authentication · <b>Biker</b>
            </div>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
  const togglePassword = document.querySelector('#togglePassword');
  const password = document.querySelector('#password');

  togglePassword.addEventListener('click', function () {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);

    // Swap the leading lock icon to unlocked while the password is visible
    const lead = document.querySelector('#t360PasswordLock');
    if (lead) {
      lead.classList.toggle('fa-unlock');
      lead.classList.toggle('fa-shield-alt');
    }

    // toggle eye / eye-slash icon
    const icon = this.querySelector('i');
    icon.classList.toggle('bi-eye');
    icon.classList.toggle('bi-eye-slash');
  });
</script>
@endpush
