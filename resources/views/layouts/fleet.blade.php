<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Explore our electric bike rental fleet. Compare monthly prices, browse bike photos, and check availability.">
    <title>{{ $title ?? 'Find your next ride · Biker' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('admin/images/GMBSmall.png') }}">
    <link rel="stylesheet" href="{{ asset('xpress/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('xpress/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('xpress/css/fleet-dashboard.css') }}">
    @livewireStyles
</head>
<body class="fleet-page">
    <a href="#main-content" class="fleet-skip-link">Skip to content</a>
    <header class="fleet-header">
        <nav class="container d-flex justify-content-between align-items-center gap-3" aria-label="Main navigation">
            <a class="fleet-brand" href="{{ route('frontend.index') }}" aria-label="Biker home">
                <i class="fas fa-bolt" aria-hidden="true"></i> biker<span class="fleet-brand-period">.</span>
            </a>
            <div class="d-flex align-items-center gap-4">
                <a class="fleet-nav-link d-none d-sm-inline" href="{{ route('frontend.index') }}#fleet">Explore the fleet</a>
                <a class="btn fleet-signin" href="{{ route('login') }}">Sign in <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></a>
            </div>
        </nav>
    </header>
    <main id="main-content">
        {{ $slot }}
    </main>
    <footer class="fleet-footer">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
            <span class="fleet-brand fleet-footer-brand"><i class="fas fa-bolt" aria-hidden="true"></i> biker.</span>
            <span>Less waiting. More riding.</span>
            <span class="small">&copy; {{ now()->year }} Biker</span>
        </div>
    </footer>
    @livewireScripts
</body>
</html>
