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
    <section class="fleet-hero">
        <div class="container">
            <div class="row align-items-end g-4">
                <div class="col-lg-8">
                    <h1>Your city.<br>Your pace. <span>Your ride.</span></h1>
                    <p class="fleet-hero-copy">A better everyday starts on two wheels. Explore our electric fleet and find a ride that fits your routine.</p>
                    <a href="#fleet" class="fleet-hero-link">Find your next ride <i class="fas fa-arrow-down ms-2" aria-hidden="true"></i></a>
                </div>
                <div class="col-lg-4">
                    <div class="fleet-availability-summary">
                        <span class="fleet-summary-icon"><i class="fas fa-motorcycle" aria-hidden="true"></i></span>
                        <div><strong>{{ $availableCount }} of {{ count($bikes) }} bikes</strong><span>available to ride</span></div>
                    </div>
                    <p class="fleet-hero-note mb-0">Availability at a glance.<br>See what’s ready and what’s returning.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="fleet" class="fleet-catalog container" aria-labelledby="fleet-title">
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
                                <button type="button" class="btn btn-primary fleet-book-button w-100" @click="$dispatch('fleet-booking-placeholder')" aria-describedby="booking-placeholder-note">
                                    Book Now <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                                </button>
                            @else
                                <button type="button" class="btn fleet-prebook-button w-100" disabled>
                                    {{ $bike['prebook_date'] ? 'Pre-Book from '.$bike['prebook_date'] : 'Awaiting return date' }}
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
            <p id="booking-placeholder-note" class="fleet-booking-note"><i class="fas fa-info-circle me-2" aria-hidden="true"></i> Online booking is coming soon. For now, explore the fleet.</p>
        @endif
        <div class="fleet-placeholder-notice" role="status"
            x-data="{ visible: false, timer: null }"
            @fleet-booking-placeholder.window="visible = true; clearTimeout(timer); timer = setTimeout(() => visible = false, 4500)"
            x-show="visible" x-cloak x-transition.opacity>
            <i class="fas fa-info-circle me-2" aria-hidden="true"></i> Online booking is coming soon.
            <button type="button" @click="visible = false" aria-label="Dismiss booking notice"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
    </section>
</div>
