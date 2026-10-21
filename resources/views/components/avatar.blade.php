@props(['name', 'size' => 32])

{{-- Initials on a colour picked from the name, so the same person always gets the same colour. --}}
@php
    $palette = ['#4f46e5', '#0891b2', '#16a34a', '#d97706', '#db2777', '#7c3aed'];
    $colour = $palette[crc32($name) % count($palette)];
    $initials = collect(preg_split('/\s+/', trim($name)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp

<span {{ $attributes->merge(['class' => 'avatar']) }} title="{{ $name }}"
      style="--size: {{ $size }}px; background: {{ $colour }}">{{ mb_strtoupper($initials) }}</span>
