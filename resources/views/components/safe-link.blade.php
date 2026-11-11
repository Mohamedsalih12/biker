@props(['href' => null])

@php
    $isSafe = \App\Support\SafeUrl::isSafe($href);
@endphp
<a @if($isSafe) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'text-decoration: none;']) }}>{{ $slot }}</a>
