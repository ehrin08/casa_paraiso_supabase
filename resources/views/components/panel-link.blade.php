@props([
    'href',
])

<a href="{{ $href }}" data-panel-link {{ $attributes }}>
    {{ $slot }}
</a>
