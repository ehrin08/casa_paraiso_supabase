@props(['tone' => 'neutral'])

@php
    $classes = match ($tone) {
        'success' => 'border-casa-green/30 bg-casa-green/15 text-casa-green',
        'warning' => 'border-casa-gold/40 bg-casa-gold/15 text-casa-primary',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
        'dark' => 'border-casa-charcoal/20 bg-casa-charcoal/10 text-casa-charcoal',
        default => 'border-casa-border bg-casa-bg text-casa-muted',
    };
@endphp

<span {{ $attributes->merge(['class' => 'casa-badge '.$classes]) }}>
    {{ $slot }}
</span>
