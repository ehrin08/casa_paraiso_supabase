@props([
    'label',
    'value',
    'meta' => null,
    'tone' => 'brown',
])

@php
    $accent = match ($tone) {
        'green' => 'bg-casa-green',
        'gold' => 'bg-casa-gold',
        'charcoal' => 'bg-casa-charcoal',
        default => 'bg-casa-primary',
    };
@endphp

<div {{ $attributes->merge(['class' => 'casa-card-compact p-5']) }}>
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-casa-muted">{{ $label }}</p>
            <p class="mt-3 font-display text-3xl font-black text-casa-text">{{ $value }}</p>
        </div>
        <span class="mt-1 h-10 w-1.5 rounded-full {{ $accent }}"></span>
    </div>

    @if ($meta)
        <p class="mt-4 text-sm text-casa-muted">{{ $meta }}</p>
    @endif
</div>
