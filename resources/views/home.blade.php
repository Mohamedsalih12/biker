@extends('layouts.xpress')

@section('title', 'Admin Dashboard')

@section('content')
<style>
    /* Dashboard styles — dash- prefixed so they never clash with the global theme CSS */
    .dash-stat-card {
        border: 1px solid #dce5df; border-radius: 16px; background: #fff; height: 100%;
        padding: 1.1rem 1.2rem; overflow: hidden;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 10px 30px -18px rgba(16, 24, 40, .18);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .dash-stat-card:hover { transform: translateY(-4px); box-shadow: 0 16px 34px -18px rgba(16, 24, 40, .28); }
    .dash-stat-card .dash-icon {
        width: 2.6rem; height: 2.6rem; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px; font-size: .95rem; background: rgba(8, 123, 81, .12); color: #087b51;
    }
    .dash-stat-card .dash-icon.dash-warn { background: #fff8e6; color: #795000; }
    .dash-stat-card .dash-icon.dash-danger { background: #fff0ee; color: #9d2017; }
    .dash-stat-card .dash-value { font-size: 1.65rem; font-weight: 700; letter-spacing: -.02em; font-variant-numeric: tabular-nums; line-height: 1.2; }
    .dash-stat-card .dash-label { color: #5b6c65; font-size: .82rem; font-weight: 600; }
    .dash-stat-card .dash-hint { color: #5b6c65; font-size: .78rem; }
    .dash-chip { display: inline-block; padding: .3rem .6rem; border-radius: 6px; font-size: .75rem; font-weight: 600; line-height: 1.3; white-space: nowrap; }
    .dash-chip-neutral { background: #eef1ef; color: #415b4e; }
    .dash-chip-blue { background: #e3edf6; color: #1c5a8a; }
    .dash-chip-green { background: #e4f2e5; color: #135936; }
    .dash-chip-red { background: #ffe2de; color: #9d2017; }
    .dash-chip-amber { background: #fff8e6; color: #795000; }
    .dash-list-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem 0; border-top: 1px solid #dce5df; }
    .dash-list-item:first-child { border-top: 0; }
    .dash-list-item .dash-item-title { font-weight: 600; font-size: .875rem; }
    .dash-list-item .dash-item-sub { color: #5b6c65; font-size: .76rem; }
    .dash-chart-wrap svg { width: 100%; height: auto; display: block; }
    .dash-chart-note { color: #5b6c65; font-size: .78rem; margin-top: .4rem; }
    .dash-cell-code { font-weight: 600; font-variant-numeric: tabular-nums; }
</style>

@php
    $bookingChipClasses = [
        'reserved' => 'dash-chip-neutral',
        'taken' => 'dash-chip-blue',
        'returned' => 'dash-chip-green',
        'cancelled' => 'dash-chip-red',
    ];
    $paymentChipClasses = [
        'paid' => 'dash-chip-green',
        'pending' => 'dash-chip-amber',
        'overdue' => 'dash-chip-red',
    ];
    $maintenanceChipClasses = [
        'scheduled' => 'dash-chip-neutral',
        'in_progress' => 'dash-chip-amber',
        'completed' => 'dash-chip-green',
        'cancelled' => 'dash-chip-red',
    ];

    $chartMax = max(array_column($chart, 'count') ?: [1]);
@endphp

<div class="container-fluid px-4 py-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-0">Admin Dashboard</h3>
            <div class="small text-muted">A live look at the fleet</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="dash-icon"><i class="fas fa-motorcycle"></i></span>
                </div>
                <div class="dash-value">{{ $takenCount }} / {{ $vehicleCount }}</div>
                <div class="dash-label">Bikes out on rent</div>
                <div class="dash-hint">Active rentals across the fleet</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="dash-icon"><i class="fas fa-circle-check"></i></span>
                </div>
                <div class="dash-value">{{ $availableCount }}</div>
                <div class="dash-label">Bikes available</div>
                <div class="dash-hint">Ready to book right now</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="dash-icon"><i class="fas fa-indian-rupee-sign"></i></span>
                </div>
                <div class="dash-value">{{ number_format($revenue) }}</div>
                <div class="dash-label">Revenue collected</div>
                <div class="dash-hint">Across {{ number_format($paymentCount) }} recorded payment{{ ($paymentCount === 1) ? '' : 's' }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="dash-stat-card">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="dash-icon dash-danger"><i class="fas fa-triangle-exclamation"></i></span>
                </div>
                <div class="dash-value">{{ $overdueCount }}</div>
                <div class="dash-label">Overdue payments</div>
                <div class="dash-hint">Bookings past their due date</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100 shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span>Recent bookings</span>
                    <a href="{{ route('xpress.bookings.index') }}" class="small text-decoration-none">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Customer</th>
                                    <th>Bike</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentBookings as $booking)
                                    <tr>
                                        <td class="dash-cell-code">
                                            <a href="{{ route('xpress.bookings.show', $booking) }}">{{ $booking->booking_code }}</a>
                                        </td>
                                        <td>{{ $booking->customer->name ?? '—' }}</td>
                                        <td>{{ $booking->vehicle->registration_number ?? '—' }}</td>
                                        <td>
                                            <span class="dash-chip {{ $bookingChipClasses[$booking->booking_status] }}">{{ ucfirst($booking->booking_status) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No bookings recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100 shadow-sm">
                <div class="card-header">Fleet pulse</div>
                <div class="card-body pt-2">
                    @forelse ($activeRentals as $booking)
                        <div class="dash-list-item">
                            <div>
                                <div class="dash-item-title">
                                    {{ $booking->vehicle->registration_number ?? '—' }} · {{ $booking->customer->name ?? '—' }}
                                </div>
                                <div class="dash-item-sub">Due back {{ $booking->end_date?->format('d M Y') ?? '—' }}</div>
                            </div>
                            <span class="dash-chip {{ $paymentChipClasses[$booking->payment_status] }}">{{ ucfirst($booking->payment_status) }}</span>
                        </div>
                    @empty
                        <div class="text-muted py-2 small">No bikes are out on rent right now.</div>
                    @endforelse

                    @foreach ($workshopLogs as $log)
                        <div class="dash-list-item">
                            <div>
                                <div class="dash-item-title">
                                    <a href="{{ route('xpress.maintenance-logs.show', $log) }}" class="text-decoration-none">
                                        {{ $log->vehicle->registration_number ?? '—' }} · Workshop
                                    </a>
                                </div>
                                <div class="dash-item-sub">{{ $log->service_description }}</div>
                            </div>
                            <span class="dash-chip {{ $maintenanceChipClasses[$log->status] }}">{{ str_replace('_', ' ', ucfirst($log->status)) }}</span>
                        </div>
                    @endforeach

                    <div class="dash-chart-wrap mt-3">
                        <svg viewBox="0 0 420 180" role="img" aria-label="Bookings started per month, last 6 months">
                            <line x1="0" y1="150" x2="420" y2="150" stroke="#dce5df"></line>
                            @foreach ($chart as $index => $point)
                                @php
                                    $barWidth = 34;
                                    $gap = (420 - count($chart) * $barWidth) / (count($chart) + 1);
                                    $x = (int) round($gap + $index * ($barWidth + $gap));
                                    $barHeight = (int) round(($point['count'] / max($chartMax, 1)) * 130);
                                    $y = 150 - $barHeight;
                                    $isCurrent = $index === count($chart) - 1;
                                @endphp
                                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barWidth }}" height="{{ $barHeight }}" rx="6" fill="#087b51" opacity="{{ $isCurrent ? 1 : .45 }}"></rect>
                                <text x="{{ $x + (int) ($barWidth / 2) }}" y="168" text-anchor="middle" font-size="11" fill="#5b6c65">{{ $point['label'] }}</text>
                                <text x="{{ $x + (int) ($barWidth / 2) }}" y="{{ $y - 8 }}" text-anchor="middle" font-size="11" font-weight="600" fill="#18372e">{{ $point['count'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                    <div class="dash-chart-note">Bookings started per month, last 6 months</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
