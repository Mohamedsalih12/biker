@extends('layouts.xpress')
@section('title', 'Vehicle Details')
@section('content')
    <x-session-message />
    <style>
        .vehicle-passport { --passport-ink: #173f33; --passport-line: #d8e4dd; color: var(--passport-ink); }
        .vehicle-passport .passport-nav { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.25rem; }
        .vehicle-passport .passport-nav a { color: var(--passport-ink); text-underline-offset: .25em; }
        .vehicle-passport .passport-nav span { font-size: .875rem; }
        .vehicle-passport .passport-feature { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); background: #ecf4ee; border-radius: 16px; overflow: hidden; }
        .vehicle-passport .passport-photo { margin: 0; background: #fff; min-width: 0; }
        .vehicle-passport .passport-photo a { display: block; height: 100%; }
        .vehicle-passport .passport-photo img { display: block; width: 100%; height: 100%; max-height: 470px; object-fit: contain; }
        .vehicle-passport .passport-no-photo { min-height: 280px; display: grid; place-content: center; padding: 2rem; text-align: center; background: #e2ece5; }
        .vehicle-passport .passport-identity { padding: clamp(1.5rem, 3vw, 3rem); display: flex; flex-direction: column; align-items: flex-start; }
        .vehicle-passport .passport-status { display: inline-flex; align-items: center; gap: .5rem; font-size: .875rem; font-weight: 700; padding: .4rem .8rem; background: #fff; border: 1px solid var(--passport-line); border-radius: 50px; }
        .vehicle-passport .passport-status::before { content: ''; width: .5rem; height: .5rem; border-radius: 50%; background: currentColor; }
        .vehicle-passport .passport-status-available { color: #087044; }
        .vehicle-passport .passport-status-reserved, .vehicle-passport .passport-status-maintenance { color: #805000; }
        .vehicle-passport .passport-status-decommissioned { color: #733d3d; }
        .vehicle-passport h1 { margin: 1.25rem 0 .5rem; font-size: clamp(2rem, 3vw, 3.5rem); line-height: 1.1; letter-spacing: -.03em; overflow-wrap: anywhere; }
        .vehicle-passport .passport-registration { font-size: 1.125rem; margin-bottom: 1.75rem; overflow-wrap: anywhere; }
        .vehicle-passport .passport-key-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.5rem; width: 100%; border-top: 1px solid #bbd0c2; padding-top: 1.25rem; margin-bottom: 1.75rem; }
        .vehicle-passport dt { font-size: .8125rem; font-weight: 500; color: #52685d; margin-bottom: .35rem; }
        .vehicle-passport dd { margin: 0; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
        .vehicle-passport .passport-key-details dd { font-size: 1.5rem; font-weight: 700; }
        .vehicle-passport .passport-key-details dd small { font-size: .875rem; font-weight: 400; }
        .vehicle-passport .passport-edit { margin-top: auto; background: var(--passport-ink); border-color: var(--passport-ink); color: #fff; padding: .75rem 1.5rem; }
        .vehicle-passport .passport-edit:hover { background: #24634b; border-color: #24634b; }
        .vehicle-passport a:focus-visible { outline: 3px solid #087044; outline-offset: 4px; }
        .vehicle-passport .passport-gallery { display: flex; gap: .75rem; overflow-x: auto; padding: 1rem 0 .25rem; }
        .vehicle-passport .passport-gallery figure { flex: 0 0 112px; margin: 0; }
        .vehicle-passport .passport-gallery img { width: 112px; height: 84px; object-fit: contain; background: #fff; border: 1px solid var(--passport-line); border-radius: 8px; }
        .vehicle-passport .passport-gallery figcaption { font-size: .75rem; margin-top: .35rem; color: #52685d; }
        .vehicle-passport .passport-records { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2rem; padding: 2rem 0; }
        .vehicle-passport .passport-section { min-width: 0; }
        .vehicle-passport h2 { font-size: 1.25rem; margin: 0 0 1.25rem; }
        .vehicle-passport .passport-records dl { margin: 0; }
        .vehicle-passport .passport-record { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); gap: 1rem; border-top: 1px solid var(--passport-line); padding: .85rem 0; }
        .vehicle-passport .passport-record dt { margin: 0; }
        .vehicle-passport .passport-condition { border-top: 1px solid var(--passport-line); padding: 1.5rem 0; }
        .vehicle-passport .passport-condition p { white-space: pre-wrap; overflow-wrap: anywhere; margin: 0; }
        @media (max-width: 767px) {
            .vehicle-passport .passport-feature, .vehicle-passport .passport-records { grid-template-columns: minmax(0, 1fr); }
            .vehicle-passport .passport-photo img { max-height: 320px; aspect-ratio: 4 / 3; }
            .vehicle-passport .passport-nav span { display: none; }
            .vehicle-passport .passport-records { gap: 1.5rem; }
        }
    </style>
    <article class="vehicle-passport">
        <nav class="passport-nav" aria-label="Vehicle navigation">
            <a href="{{ route('xpress.vehicles.index') }}">Back to vehicles</a>
            <span>Vehicle Details</span>
        </nav>

        <div class="passport-feature">
            <figure class="passport-photo">
                @if ($vehicle->imageUrl())
                    <a href="{{ $vehicle->imageUrl() }}" target="_blank" rel="noopener" aria-label="Open cover photo of {{ $vehicle->registration_number }} in a new tab">
                        <img src="{{ $vehicle->imageUrl() }}" alt="Picture 1 of {{ $vehicle->registration_number }}" width="640" height="470">
                    </a>
                @else
                    <div class="passport-no-photo">
                        <strong>No pictures yet.</strong>
                        <span>Add a cover photo when editing this vehicle.</span>
                    </div>
                @endif
            </figure>
            <header class="passport-identity">
                <span class="passport-status passport-status-{{ $vehicle->current_status }}">{{ ucfirst($vehicle->current_status) }}</span>
                <h1>{{ $vehicle->brand }} {{ $vehicle->model }}</h1>
                <p class="passport-registration"><span class="visually-hidden">Registration Number: </span>{{ $vehicle->registration_number }}</p>
                <dl class="passport-key-details">
                    <div>
                        <dt>Odometer Reading</dt>
                        <dd>{{ number_format($vehicle->odometer_reading) }} <small>km</small></dd>
                    </div>
                    <div>
                        <dt>Rate Per Unit</dt>
                        <dd>{{ number_format((float) $vehicle->rate_per_unit, 2) }}</dd>
                    </div>
                </dl>
                <a href="{{ route('xpress.vehicles.edit', $vehicle) }}" class="btn passport-edit">Edit Vehicle</a>
            </header>
        </div>

        @if (count($vehicle->imagePaths()) > 1)
            <div class="passport-gallery" role="group" aria-label="More vehicle photos">
                @foreach ($vehicle->imagePaths() as $path)
                    <figure>
                        <a href="{{ asset('storage/'.ltrim($path, '/')) }}" target="_blank" rel="noopener" aria-label="Open picture {{ $loop->iteration }} of {{ $vehicle->registration_number }} in a new tab">
                            <img src="{{ asset('storage/'.ltrim($path, '/')) }}" alt="Picture {{ $loop->iteration }} of {{ $vehicle->registration_number }}" width="112" height="84" loading="lazy">
                        </a>
                        <figcaption>Picture {{ $loop->iteration }}{{ $loop->first ? ' (cover)' : '' }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @endif

        <div class="passport-records">
            <section class="passport-section" aria-labelledby="vehicle-identification">
                <h2 id="vehicle-identification">Vehicle identification</h2>
                <dl>
                    @foreach (['registration_number' => 'Registration Number', 'brand' => 'Brand', 'model' => 'Model', 'chassis_number' => 'Chassis Number', 'battery_serial_number' => 'Battery Serial Number'] as $field => $label)
                        <div class="passport-record">
                            <dt>{{ $label }}</dt>
                            <dd>{{ $vehicle->{$field} ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
            <section class="passport-section" aria-labelledby="vehicle-ownership">
                <h2 id="vehicle-ownership">Ownership &amp; insurance</h2>
                <dl>
                    @foreach (['purchase_date' => 'Purchase Date', 'purchase_cost' => 'Purchase Cost', 'insurance_policy_number' => 'Insurance Policy Number', 'insurance_expires_at' => 'Insurance Expires At'] as $field => $label)
                        <div class="passport-record">
                            <dt>{{ $label }}</dt>
                            <dd>
                                @if (in_array($field, ['purchase_date', 'insurance_expires_at'], true))
                                    {{ $vehicle->{$field}?->format('Y-m-d') ?? '—' }}
                                @elseif ($field === 'purchase_cost')
                                    {{ $vehicle->purchase_cost !== null ? number_format((float) $vehicle->purchase_cost, 2) : '—' }}
                                @else
                                    {{ $vehicle->{$field} ?? '—' }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>
        <section class="passport-condition" aria-labelledby="vehicle-condition">
            <h2 id="vehicle-condition">Damages</h2>
            <p>{{ $vehicle->damages ?? '—' }}</p>
        </section>
    </article>
@endsection
