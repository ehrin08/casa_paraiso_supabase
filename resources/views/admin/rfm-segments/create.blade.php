<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Promotion configuration') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Add RFM segment') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Create a clear customer group using measurable visit and spending thresholds.') }}</p>
        </div>
        <a href="{{ route('admin.rfm-segments.index') }}" class="casa-button-secondary">{{ __('Back to segments') }}</a>
    </x-slot>

    @include('admin.rfm-segments.partials.form', [
        'action' => route('admin.rfm-segments.store'),
        'method' => 'POST',
        'submitLabel' => __('Create segment'),
    ])
</x-app-layout>
