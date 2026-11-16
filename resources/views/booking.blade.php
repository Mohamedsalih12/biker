@component('layouts.fleet', ['title' => 'Reserve your ride · Biker'])
    <section class="container fleet-reservation" aria-labelledby="reservation-title">
        <a class="fleet-back-link" href="{{ route('frontend.index') }}#fleet">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg>
            Back to the fleet
        </a>
        <header class="fleet-reservation-heading">
            <h1 id="reservation-title">Your next ride starts here<span class="fleet-heading-dot">.</span></h1>
            <p class="fleet-muted fleet-reservation-intro">Your bike. Your dates. A little less waiting.</p>
        </header>
        <div class="fleet-reservation-desk">
            <aside class="fleet-reservation-ride" aria-label="Selected bike">
                <div class="fleet-reservation-summary">
                    <div class="fleet-reservation-photo" x-data="{ photoLoaded: false }"
                        x-init="$nextTick(() => { const image = $el.querySelector('img'); photoLoaded = image && image.complete && image.naturalWidth > 0; })">
                        <div class="fleet-reservation-photo-fallback" x-show="!photoLoaded">
                            <span>{{ $bikeTitle }}</span>
                            <p>Bike photo not available</p>
                        </div>
                        @if ($vehicle->imageUrl())
                            <img src="{{ $vehicle->imageUrl() }}" alt="{{ $bikeTitle }}" width="800" height="600"
                                fetchpriority="high" x-on:load="photoLoaded = true" x-on:error="$el.hidden = true; photoLoaded = false">
                        @endif
                    </div>
                    <div class="fleet-reservation-ride-details">
                        <h2>{{ $bikeTitle }}</h2>
                        <p class="fleet-reservation-odometer">
                            {{ $vehicle->odometer_reading !== null ? \Illuminate\Support\Number::format($vehicle->odometer_reading, locale: 'en_IN').' km on the odometer' : 'Odometer not listed' }}
                        </p>
                        <div class="fleet-reservation-price">
                            @if ($price)
                                <strong>{{ $price }}</strong><span> / month</span>
                            @else
                                <strong>Price on request</strong>
                            @endif
                        </div>
                        <p class="fleet-summary-note mb-0">Monthly rental rate. Payment and handover details will be confirmed by our team.</p>
                    </div>
                </div>
                <div class="fleet-reservation-next">
                    <h2>From reserved to riding.</h2>
                    <ol>
                        <li><strong>Reserve online</strong><span>Choose your dates and submit your details.</span></li>
                        <li><strong>Visit our office within 24 hours</strong><span>Pay at the office to keep your reservation.</span></li>
                    </ol>
                </div>
            </aside>
            <div class="fleet-reservation-entry">
                @if ($canBook)
                    <div class="fleet-reservation-reassurance">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-14 5h4"/></svg>
                        <div><strong>Reserve now. Pay at the office.</strong><span>No payment is collected online.</span></div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert" tabindex="-1" id="reservation-errors">
                        <strong>Please check your reservation.</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($canBook)
                    <form method="POST" action="{{ route('fleet.bookings.store', $vehicle) }}" class="fleet-reservation-form"
                        x-data="{ submitting: false, start: @js(old('start_date', today()->toDateString())) }"
                        @submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }">
                        @csrf
                        <h2>Your details</h2>
                        <div class="mb-4">
                            <label class="form-label" for="customer-name">Full name</label>
                            <input class="form-control @error('name') is-invalid @enderror" id="customer-name" name="name"
                                type="text" value="{{ old('name') }}" maxlength="255" autocomplete="name" required
                                @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                            @error('name') <div id="name-error" class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label" for="customer-phone">Phone number</label>
                                <input class="form-control @error('phone') is-invalid @enderror" id="customer-phone" name="phone"
                                    type="tel" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" placeholder="9876543210" required
                                    @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                                @error('phone') <div id="phone-error" class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="customer-aadhaar">Aadhaar number</label>
                                <input class="form-control @error('aadhaar') is-invalid @enderror" id="customer-aadhaar" name="aadhaar"
                                    type="text" inputmode="numeric" maxlength="18" autocomplete="off" placeholder="1234 5678 9012" required
                                    aria-describedby="aadhaar-help @error('aadhaar') aadhaar-error @enderror"
                                    @error('aadhaar') aria-invalid="true" @enderror>
                                @error('aadhaar') <div id="aadhaar-error" class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <p id="aadhaar-help" class="fleet-form-note">Already registered? Use the same Aadhaar number. We’ll use your existing customer record without changing your saved details.</p>

                        <h2 class="fleet-dates-heading">Choose your dates</h2>
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label" for="start-date">Start date</label>
                                <input class="form-control @error('start_date') is-invalid @enderror" id="start-date" name="start_date"
                                    type="date" value="{{ old('start_date', today()->toDateString()) }}" min="{{ today()->toDateString() }}"
                                    max="{{ today()->addDays(2)->toDateString() }}"
                                    x-model="start" required
                                    @error('start_date') aria-invalid="true" aria-describedby="start-date-error" @enderror>
                                @error('start_date') <div id="start-date-error" class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="end-date">End date</label>
                                <input class="form-control @error('end_date') is-invalid @enderror" id="end-date" name="end_date"
                                    type="date" value="{{ old('end_date') }}" min="{{ old('start_date', today()->toDateString()) }}"
                                    :min="start" required
                                    @error('end_date') aria-invalid="true" aria-describedby="end-date-error" @enderror>
                                @error('end_date') <div id="end-date-error" class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary fleet-book-button w-100" :disabled="submitting">
                            <span x-show="!submitting">Confirm reservation <svg class="ms-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14m-7-7 7 7-7 7"/></svg></span>
                            <span x-show="submitting" x-cloak>Saving your reservation…</span>
                        </button>
                        <p class="fleet-form-note mt-3 mb-0">No payment is collected here. Submitting reserves this bike for your selected dates.</p>
                    </form>
                @else
                    <div class="fleet-reservation-unavailable">
                        <i class="fas fa-clock mb-3" aria-hidden="true"></i>
                        <h2>This bike is no longer available for online booking</h2>
                        <p class="fleet-muted">Please explore another bike or contact our team for assistance.</p>
                        <a class="btn fleet-signin" href="{{ route('frontend.index') }}#fleet">Explore available bikes</a>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endcomponent
