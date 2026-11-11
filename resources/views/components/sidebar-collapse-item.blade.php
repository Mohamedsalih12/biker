@props([
    'id',
    'title',
    'icon',
    'patterns' => [],
])

@php
    $isOpen = collect($patterns)->contains(fn ($pattern) => Request::is($pattern));
@endphp

<li class="nav-item">
    <a
        class="nav-link d-flex align-items-center justify-content-between {{ $isOpen ? 'active' : '' }}"
        data-bs-toggle="collapse"
        href="#{{ $id }}"
        data-bs-parent="#sidebar"
        aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
    >
        <!-- LEFT SIDE -->
        <div class="d-flex align-items-center">
            <i class="{{ $icon }}"></i>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <span class="menu-title">{{ $title }}</span>
        </div>

        <!-- RIGHT SIDE (ALWAYS RIGHT ALIGNED) -->
        <i class="fa-solid toggle-icon {{ $isOpen ? 'fa-chevron-up' : 'fa-chevron-down' }}"></i>
    </a>

    <div class="collapse {{ $isOpen ? 'show' : '' }}" id="{{ $id }}">
        <ul class="nav flex-column sub-menu">
            {{ $slot }}
        </ul>
    </div>
</li>