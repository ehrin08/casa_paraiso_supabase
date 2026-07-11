@props([
    'eyebrow' => null,
    'title' => null,
    'count' => null,
    'resetUrl' => null,
])

<div {{ $attributes->merge(['class' => 'casa-list-toolbar']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="casa-section-label">{{ $eyebrow }}</p>
        @endif

        @if ($title)
            <h2 class="mt-2 text-xl font-extrabold text-casa-text">{{ $title }}</h2>
        @endif

        @if ($count !== null || $resetUrl)
            <div class="mt-3 flex flex-wrap items-center gap-2">
                @if ($count !== null)
                    <span class="casa-filter-chip">{{ trans_choice(':count record|:count records', (int) $count) }}</span>
                @endif

                @if ($resetUrl)
                    <a href="{{ $resetUrl }}" class="casa-filter-chip hover:border-casa-gold hover:text-casa-primary">
                        {{ __('Reset') }}
                    </a>
                @endif
            </div>
        @endif
    </div>

    <div class="w-full lg:w-auto">
        {{ $slot }}
    </div>
</div>
