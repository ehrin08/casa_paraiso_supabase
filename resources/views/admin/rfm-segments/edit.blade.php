<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Promotion configuration') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Edit RFM segment') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Changes affect future generation only; stored suggestion snapshots remain unchanged.') }}</p>
        </div>
        <a href="{{ route('admin.rfm-segments.index') }}" class="casa-button-secondary">{{ __('Back to segments') }}</a>
    </x-slot>

    @include('admin.rfm-segments.partials.form', [
        'action' => route('admin.rfm-segments.update', $rfmSegment),
        'method' => 'PATCH',
        'submitLabel' => __('Save changes'),
    ])
</x-app-layout>
