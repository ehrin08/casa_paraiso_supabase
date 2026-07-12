<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Promotion configuration') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Add promotion rule') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Choose the segment and the exact offer administrators will review before use.') }}</p>
        </div>
        <a href="{{ route('admin.promotion-rules.index') }}" class="casa-button-secondary">{{ __('Back to rules') }}</a>
    </x-slot>

    @include('admin.promotion-rules.partials.form', [
        'action' => route('admin.promotion-rules.store'),
        'method' => 'POST',
        'submitLabel' => __('Create rule'),
    ])
</x-app-layout>
