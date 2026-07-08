<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff appointment') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}
            </p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            @if (session('status'))
                <div class="rounded-[18px] border border-casa-green/30 bg-casa-green/10 px-5 py-4 text-sm font-semibold text-casa-green">
                    {{ __('Appointment updated.') }}
                </div>
            @endif
            <x-input-error :messages="$errors->all()" />

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Details') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Service schedule') }}</h2>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Requested') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->requested_start_at?->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Scheduled') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->scheduled_start_at?->format('M d, Y g:i A') ?: __('Not confirmed') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt>
                        <dd class="mt-2"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Customer phone') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->customerProfile?->user?->phone ?: __('Not set') }}</dd>
                    </div>
                </dl>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Customer notes') }}</h2>
                </div>
                <p class="mt-5 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->customer_notes ?: __('No customer notes.') }}</p>
            </x-app-card>
        </section>

        <aside class="space-y-4">
            @if ($appointment->status === \App\Models\Appointment::STATUS_PENDING)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Confirm') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Accept request') }}</h2>
                    <form method="POST" action="{{ route('staff.appointments.update', $appointment) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="service_id" value="{{ $appointment->service_id }}">
                        <input type="hidden" name="requested_start_at" value="{{ $appointment->requested_start_at?->format('Y-m-d H:i:s') }}">
                        <input type="hidden" name="status" value="{{ \App\Models\Appointment::STATUS_CONFIRMED }}">
                        <div>
                            <x-input-label for="scheduled_start_at" :value="__('Scheduled time')" />
                            <x-text-input id="scheduled_start_at" name="scheduled_start_at" type="datetime-local" class="mt-2" :value="old('scheduled_start_at', $appointment->requested_start_at?->format('Y-m-d\\TH:i'))" required />
                        </div>
                        <textarea name="internal_notes" rows="4" class="casa-input" placeholder="{{ __('Internal notes') }}">{{ old('internal_notes', $appointment->internal_notes) }}</textarea>
                        <button type="submit" class="casa-button-primary w-full">{{ __('Confirm appointment') }}</button>
                    </form>
                </x-app-card>
            @elseif ($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Service actions') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Update outcome') }}</h2>
                    <div class="mt-5 space-y-3">
                        @foreach ([\App\Models\Appointment::STATUS_COMPLETED => __('Mark completed'), \App\Models\Appointment::STATUS_NO_SHOW => __('Mark no-show'), \App\Models\Appointment::STATUS_CANCELLED => __('Cancel')] as $targetStatus => $label)
                            <form method="POST" action="{{ route('staff.appointments.update', $appointment) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="service_id" value="{{ $appointment->service_id }}">
                                <input type="hidden" name="requested_start_at" value="{{ $appointment->requested_start_at?->format('Y-m-d H:i:s') }}">
                                <input type="hidden" name="status" value="{{ $targetStatus }}">
                                <button type="submit" class="{{ $targetStatus === \App\Models\Appointment::STATUS_COMPLETED ? 'casa-button-primary' : 'casa-button-secondary' }} w-full">{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                </x-app-card>

                <a href="{{ route('staff.transactions.create', ['appointment_id' => $appointment->id]) }}" class="casa-button-secondary w-full">{{ __('Record payment') }}</a>
            @endif

            <x-app-card>
                <p class="casa-section-label">{{ __('Customer') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ $appointment->customerProfile?->user?->name }}</h2>
                @if ($appointment->customerProfile)
                    <a href="{{ route('staff.customers.show', $appointment->customerProfile) }}" class="mt-5 casa-button-secondary w-full">{{ __('Open customer') }}</a>
                @endif
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
