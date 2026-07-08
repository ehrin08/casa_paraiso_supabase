@php
    $modalName = $modalName ?? null;
    $initialRequestedAt = old('requested_start_at');
    $initialMonth = $initialRequestedAt
        ? \Illuminate\Support\Carbon::parse($initialRequestedAt)->format('Y-m')
        : now()->addDay()->format('Y-m');
@endphp

<form
    method="POST"
    action="{{ route('customer.appointments.store') }}"
    @class([
        'grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]',
        'casa-modal-form' => $modalName,
    ])
    x-data="customerCalendarBooking({
        availabilityUrl: '{{ route('customer.appointments.availability') }}',
        initialMonth: '{{ $initialMonth }}',
        initialServiceId: '{{ old('service_id') }}',
        initialStaffId: '{{ old('preferred_staff_profile_id') }}',
        initialSlot: '{{ $initialRequestedAt }}',
        slotPreviewLimit: 2
    })"
    x-init="init()"
>
    @csrf
    @if ($modalName)
        <input type="hidden" name="_modal" value="{{ $modalName }}">
    @endif
    <input type="hidden" name="requested_start_at" x-bind:value="selectedSlot">

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Request details') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Preferred visit') }}</h2>
        </div>

        <div class="mt-5 grid gap-6">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="service_id" :value="__('Service')" />
                    <select id="service_id" name="service_id" class="casa-input mt-2" required x-model="serviceId" x-on:change="serviceChanged()">
                        <option value="">{{ __('Select service') }}</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((int) old('service_id') === $service->id)>
                                {{ $service->name }} - {{ $service->duration_minutes }} min - PHP {{ number_format((float) $service->price, 2) }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
                </div>

                <div>
                    <x-input-label for="preferred_staff_profile_id" :value="__('Preferred staff (optional)')" />
                    <select id="preferred_staff_profile_id" name="preferred_staff_profile_id" class="casa-input mt-2" x-model="staffId" x-on:change="staffChanged()">
                        <option value="">{{ __('No preference') }}</option>
                        @foreach ($staffProfiles as $staffProfile)
                            <option value="{{ $staffProfile->id }}" @selected((int) old('preferred_staff_profile_id') === $staffProfile->id)>
                                {{ $staffProfile->user->name }} - {{ $staffProfile->specialization ?: __('Spa staff') }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('preferred_staff_profile_id')" />
                </div>
            </div>

            <section class="rounded-[22px] border border-casa-border bg-casa-bg p-4 sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Calendar') }}</p>
                        <h3 class="mt-2 font-display text-xl font-black text-casa-text" x-text="monthLabel"></h3>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="casa-button-secondary min-h-10 px-4 py-2" x-on:click="previousMonth()">{{ __('Prev') }}</button>
                        <button type="button" class="casa-button-secondary min-h-10 px-4 py-2" x-on:click="nextMonth()">{{ __('Next') }}</button>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-7 gap-2 text-center text-xs font-black uppercase tracking-[0.08em] text-casa-muted">
                    <template x-for="dayName in weekDays" x-bind:key="dayName">
                        <span x-text="dayName"></span>
                    </template>
                </div>

                <div class="mt-3 grid grid-cols-7 gap-2">
                    <template x-for="day in calendarDays" x-bind:key="day.key">
                        <button
                            type="button"
                            class="min-h-20 rounded-2xl border px-2 py-3 text-left text-sm font-bold transition sm:min-h-24"
                            x-bind:class="day.date === selectedDate
                                ? 'border-casa-primary bg-casa-primary text-white'
                                : day.available
                                    ? 'border-casa-gold bg-white text-casa-text hover:border-casa-primary'
                                    : 'border-casa-border bg-white/45 text-casa-muted/50'"
                            x-bind:disabled="!day.available"
                            x-on:click="selectDate(day.date)"
                        >
                            <span class="block" x-text="day.label"></span>
                            <span class="mt-2 flex flex-col gap-1" x-show="day.available">
                                <template x-for="slot in day.previewSlots" x-bind:key="slot.starts_at">
                                    <span
                                        class="block w-full truncate rounded-full px-1.5 py-1 text-center text-[0.62rem] font-black uppercase leading-none"
                                        x-bind:class="day.date === selectedDate
                                            ? 'bg-white/18 text-white'
                                            : 'bg-casa-gold/15 text-casa-primary'"
                                    >
                                        <span class="sm:hidden" x-text="slot.time"></span>
                                        <span class="hidden sm:inline" x-text="slot.label"></span>
                                    </span>
                                </template>
                                <span
                                    class="block truncate text-center text-[0.62rem] font-black uppercase leading-none"
                                    x-bind:class="day.date === selectedDate ? 'text-white/80' : 'text-casa-muted'"
                                    x-show="day.moreSlots"
                                    x-text="moreSlotsLabel(day)"
                                ></span>
                            </span>
                        </button>
                    </template>
                </div>

                <div class="mt-5 rounded-2xl border border-casa-border bg-white p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Available times') }}</p>
                            <p class="mt-1 text-sm font-semibold text-casa-text" x-text="selectedDateLabel"></p>
                        </div>
                        <x-status-badge x-show="loading">{{ __('Loading') }}</x-status-badge>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-casa-muted" x-show="!serviceId">
                        {{ __('Choose a service to load available calendar slots.') }}
                    </p>

                    <p class="mt-4 text-sm leading-6 text-red-700" x-show="error" x-text="error"></p>

                    <div class="mt-4 grid gap-2 sm:grid-cols-3" x-show="selectedDate && selectedDateSlots.length">
                        <template x-for="slot in selectedDateSlots" x-bind:key="slot.starts_at">
                            <button
                                type="button"
                                class="rounded-full border px-4 py-3 text-sm font-black uppercase tracking-[0.06em] transition"
                                x-bind:class="selectedSlot === slot.starts_at
                                    ? 'border-casa-primary bg-casa-primary text-white'
                                    : 'border-casa-border bg-casa-bg text-casa-primary hover:border-casa-gold'"
                                x-on:click="chooseSlot(slot)"
                            >
                                <span x-text="slot.label"></span>
                            </button>
                        </template>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-casa-muted" x-show="serviceId && selectedDate && !selectedDateSlots.length && !loading">
                        {{ __('No available times for this date. Choose another highlighted date.') }}
                    </p>
                </div>

                <x-input-error class="mt-3" :messages="$errors->get('requested_start_at')" />
            </section>

            <noscript>
                <div>
                    <x-input-label for="requested_start_at_fallback" :value="__('Preferred date and time')" />
                    <x-text-input id="requested_start_at_fallback" name="requested_start_at" type="datetime-local" class="mt-2" :value="old('requested_start_at', now()->addDay()->setTime(10, 0)->format('Y-m-d\\TH:i'))" required />
                </div>
            </noscript>

            <div>
                <x-input-label for="customer_notes" :value="__('Notes')" />
                <textarea id="customer_notes" name="customer_notes" rows="5" class="casa-input mt-2" placeholder="{{ __('Tell us anything staff should know before confirming.') }}">{{ old('customer_notes') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('customer_notes')" />
            </div>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card>
            <p class="casa-section-label">{{ __('How confirmation works') }}</p>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Highlighted dates have at least one available time based on staff schedules. Your request still starts as pending until staff confirms it.') }}
            </p>
        </x-app-card>
        <x-app-card>
            <p class="casa-section-label">{{ __('Selected slot') }}</p>
            <p class="mt-3 text-lg font-bold text-casa-text" x-text="selectedSlotLabel || '{{ __('No time selected') }}'"></p>
        </x-app-card>
        <x-app-card data-modal-actions>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full" x-bind:disabled="!selectedSlot" x-bind:class="!selectedSlot ? 'opacity-60' : ''">{{ __('Submit request') }}</button>
                @if ($modalName)
                    <button type="button" class="casa-button-secondary w-full" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Cancel') }}</button>
                @else
                    <a href="{{ route('customer.appointments.index') }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>
                @endif
            </div>
        </x-app-card>
    </aside>
</form>
