<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer lounge') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Request appointment') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Choose a service, pick an available calendar slot, and send the request for staff confirmation.') }}
            </p>
        </div>
    </x-slot>

    @include('customer.appointments.partials.form')
</x-app-layout>
