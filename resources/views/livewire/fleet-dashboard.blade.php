<div x-data="{
    search: '',
    filter: 'all',
    bikes: @js($bikes),
    matches(bike) {
        return (this.filter === 'all' || (this.filter === 'available' ? bike.available : !bike.available))
            && bike.search.includes(this.search.toLowerCase().trim());
    },
    get visibleCount() { return this.bikes.filter(bike => this.matches(bike)).length; }
}">
    <section class="fleet-hero" aria-labelledby="featured-ride-title">
        <div class="container">
            @if ($featuredBike)
                <div class="fleet-spotlight">
                    <div class="fleet-spotlight-photo">
                        <div class="fleet-spotlight-placeholder" role="img" aria-label="Photo not available for {{ $featuredBike['title'] }}">
                            <i class="fas fa-motorcycle" aria-hidden="true"></i>
                            <span>Photos coming soon</span>
                        </div>
                        @if ($featuredBike['images'] !== [])
                            <img src="{{ $featuredBike['images'][0] }}" alt="{{ $featuredBike['title'] }}"
                                width="800" height="600" fetchpriority="high" loading="eager"
                                x-on:load="$el.previousElementSibling.hidden = true"
                                x-on:error="$el.hidden = true; $el.previousElementSibling.hidden = false">
                        @endif
                        <span class="fleet-spotlight-status">
                            <i class="fas {{ $featuredBike['available'] ? 'fa-check-circle' : 'fa-clock' }}" aria-hidden="true"></i>
                            {{ $featuredBike['available'] ? 'Available to book' : 'Currently on a ride or reserved' }}
                        </span>
                    </div>
                    <div class="fleet-spotlight-details">
                        <h1 id="featured-ride-title">{{ $featuredBike['available'] ? 'Ride this today' : 'Your next ride' }}<span>.</span></h1>
                        <p class="fleet-hero-copy">{{ $featuredBike['available'] ? 'A real bike. A monthly rate. Your next move.' : 'Good rides are worth planning for. Here’s what’s coming back.' }}</p>
                        <h2 class="fleet-spotlight-model">{{ $featuredBike['title'] }}</h2>
                        <p class="fleet-spotlight-spec">{{ $featuredBike['odometer'] }}</p>
                        @if (! $featuredBike['available'])
                            <p class="fleet-spotlight-return">
                                <i class="far fa-calendar-alt me-2" aria-hidden="true"></i>
                                {{ $featuredBike['next_date'] ? 'Expected back '.$featuredBike['next_date'] : 'Return date to be confirmed' }}
                            </p>
                        @endif
                        <div class="fleet-spotlight-price">
                            @if ($featuredBike['price'] !== null)
                                <strong>{{ $featuredBike['price'] }}</strong><span> / month</span>
                            @else
                                <strong class="fleet-spotlight-price-request">Price on request</strong>
                            @endif
                        </div>
                        @if ($featuredBike['available'])
                            <a class="btn fleet-spotlight-book" href="{{ route('fleet.bookings.create', $featuredBike['id']) }}">
                                Book this bike <i class="fas fa-arrow-right ms-3" aria-hidden="true"></i>
                            </a>
                            <p class="fleet-spotlight-note">Reserve online. Pay at our office within 24 hours.</p>
                        @else
                            <p class="fleet-spotlight-note">Not available to book online yet. Visit us to discuss pre-booking.</p>
                        @endif
                        <a href="#fleet" class="fleet-hero-link">Explore all {{ count($bikes) }} {{ count($bikes) === 1 ? 'bike' : 'bikes' }} <i class="fas fa-arrow-down ms-2" aria-hidden="true"></i></a>
                    </div>
                </div>
            @else
                <div class="fleet-hero-empty">
                    <h1 id="featured-ride-title">Your next ride<br>is on its way<span>.</span></h1>
                    <p class="fleet-hero-copy mb-0">Our electric fleet will be back here soon. Check back for available bikes and monthly rates.</p>
                </div>
            @endif
        </div>
    </section>

    <section id="fleet" class="fleet-catalog container" aria-labelledby="fleet-title">
        @if (session('fleet_booking_code'))
            <div class="alert alert-success fleet-booking-confirmation" role="status">
                <i class="fas fa-check-circle me-2" aria-hidden="true"></i>
                <strong>Reservation received.</strong> Your booking reference is
                <strong>{{ session('fleet_booking_code') }}</strong>. Visit our office to pay within 24 hours, otherwise the booking will be cancelled.
            </div>
        @endif
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
            <div>
                <h2 id="fleet-title">Meet your next ride<span class="fleet-heading-dot">.</span></h2>
                <p class="fleet-muted mb-0">Real bikes. Clear prices. Your choice.</p>
            </div>
            <span class="fleet-monthly-label"><i class="far fa-calendar-alt me-2" aria-hidden="true"></i> Monthly rentals</span>
        </div>

        @if (count($bikes) > 0)
            <div class="fleet-toolbar d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="fleet-filters d-flex flex-wrap gap-1" role="group" aria-label="Filter bikes by availability">
                    <button type="button" class="btn fleet-filter" :class="{ 'is-selected': filter === 'all' }" :aria-pressed="filter === 'all'" @click="filter = 'all'">All bikes <span>{{ count($bikes) }}</span></button>
                    <button type="button" class="btn fleet-filter" :class="{ 'is-selected': filter === 'available' }" :aria-pressed="filter === 'available'" @click="filter = 'available'">Available <span>{{ $availableCount }}</span></button>
                    <button type="button" class="btn fleet-filter" :class="{ 'is-selected': filter === 'unavailable' }" :aria-pressed="filter === 'unavailable'" @click="filter = 'unavailable'">Not available <span>{{ count($bikes) - $availableCount }}</span></button>
                </div>
                <div class="fleet-search">
                    <label for="fleet-search" class="visually-hidden">Search brand or model</label>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input id="fleet-search" type="search" class="form-control" placeholder="Search brand or model" x-model="search">
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between gap-2 fleet-results-note">
                <span role="status"><span x-text="visibleCount">{{ count($bikes) }}</span> bikes to explore</span>
                <span><i class="fas fa-circle fleet-legend-dot me-1" aria-hidden="true"></i> Green means ready to ride</span>
            </div>
        @endif

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            @foreach ($bikes as $bike)
                <div class="col" wire:key="fleet-bike-{{ $bike['id'] }}" x-show="matches(bikes[{{ $loop->index }}])">
                    <article class="card fleet-card h-100 {{ $bike['available'] ? '' : 'fleet-card-unavailable' }}" aria-labelledby="bike-title-{{ $bike['id'] }}">
                        <div class="fleet-gallery" x-data="{ angle: 0, total: {{ count($bike['images']) }} }" role="region" aria-label="{{ $bike['title'] }} photo gallery" aria-roledescription="carousel">
                            @foreach ($bike['images'] as $image)
                                <img src="{{ $image }}" alt="{{ $bike['title'] }}, angle {{ $loop->iteration }}"
                                    class="fleet-bike-image" width="640" height="400"
                                    loading="{{ $loop->parent->first && $loop->first ? 'eager' : 'lazy' }}"
                                    x-show="angle === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif
                                    x-transition.opacity.duration.200ms
                                    x-on:error="$el.hidden = true">
                            @endforeach
                            @if ($bike['images'] === [])
                                <div class="fleet-photo-missing">
                                    <i class="fas fa-motorcycle" aria-hidden="true"></i>
                                    <span>Photos coming soon</span>
                                </div>
                            @else
                                <div class="fleet-photo-error" aria-hidden="true"><i class="fas fa-motorcycle"></i><span>Bike photo</span></div>
                            @endif
                            <span class="badge fleet-status {{ $bike['available'] ? 'bg-success' : 'bg-danger' }}">
                                <i class="fas {{ $bike['available'] ? 'fa-check-circle' : 'fa-clock' }} me-1" aria-hidden="true"></i>
                                {{ $bike['available'] ? 'Available' : 'Not Available' }}
                            </span>
                            @if (count($bike['images']) > 1)
                                <div class="fleet-angle-count" aria-live="polite" aria-atomic="true"><span x-text="angle + 1">1</span> / {{ count($bike['images']) }} <span class="visually-hidden">angles</span></div>
                                <button type="button" class="fleet-gallery-arrow fleet-gallery-prev" @click="angle = (angle - 1 + total) % total" aria-label="Previous angle of {{ $bike['title'] }}"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                                <button type="button" class="fleet-gallery-arrow fleet-gallery-next" @click="angle = (angle + 1) % total" aria-label="Next angle of {{ $bike['title'] }}"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                                <div class="fleet-gallery-dots" role="group" aria-label="Choose bike angle">
                                    @foreach ($bike['images'] as $image)
                                        <button type="button" :class="{ 'is-active': angle === {{ $loop->index }} }" :aria-pressed="angle === {{ $loop->index }}" @click="angle = {{ $loop->index }}" aria-label="Show angle {{ $loop->iteration }}"></button>
                                    @endforeach
                                </div>
                            @endif
                            @if (! $bike['available'])
                                <div class="fleet-booked-strip"><i class="fas fa-clock me-2" aria-hidden="true"></i> Currently reserved or on a ride</div>
                            @endif
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h3 id="bike-title-{{ $bike['id'] }}">{{ $bike['title'] }}</h3>
                            <p class="fleet-spec"><i class="fas fa-tachometer-alt me-2" aria-hidden="true"></i> {{ $bike['odometer'] }}</p>
                            <p class="fleet-return {{ $bike['available'] ? 'fleet-return-ready' : '' }}">
                                <i class="{{ $bike['available'] ? 'fas fa-check-circle' : 'far fa-calendar-alt' }} me-2" aria-hidden="true"></i>
                                @if ($bike['available'])
                                    Ready for your next ride
                                @elseif ($bike['next_date'])
                                    Next Available: <strong>{{ $bike['next_date'] }}</strong>
                                @else
                                    Return date to be confirmed
                                @endif
                            </p>
                            <div class="fleet-price-row mt-auto">
                                @if ($bike['price'] !== null)
                                    <span class="fleet-price">{{ $bike['price'] }}</span><span class="fleet-price-unit"> / month</span>
                                @else
                                    <span class="fw-semibold">Price on request</span>
                                @endif
                            </div>
                            @if ($bike['available'])
                                <a class="btn btn-primary fleet-book-button w-100" href="{{ route('fleet.bookings.create', $bike['id']) }}">
                                    Book Now <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                                </a>
                            @else
                                <button type="button" class="btn fleet-prebook-button w-100" disabled>
                                    {{ $bike['prebook_date'] ? 'Visit us to pre-book from '.$bike['prebook_date'] : 'Awaiting return date' }}
                                </button>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        @if (count($bikes) === 0)
            <div class="fleet-empty">
                <i class="fas fa-motorcycle" aria-hidden="true"></i>
                <h3>Our next ride is on its way</h3>
                <p class="fleet-muted mb-0">There are no bikes listed right now. Please check back soon.</p>
            </div>
        @else
            <div class="fleet-empty" x-show="visibleCount === 0" x-cloak>
                <i class="fas fa-search" aria-hidden="true"></i>
                <h3>No bikes match just yet</h3>
                <p class="fleet-muted">Try another brand, model, or availability filter.</p>
                <button type="button" class="btn fleet-signin" @click="search = ''; filter = 'all'">Show all bikes</button>
            </div>
        @endif
    </section>
</div>
