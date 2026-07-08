<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer feedback') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Submit feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Rate a completed appointment and leave an optional service comment.') }}</p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('customer.feedback.store') }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        <x-app-card>
            <div class="border-b border-casa-border pb-5">
                <p class="casa-section-label">{{ __('Review') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Service feedback') }}</h2>
            </div>

            <div class="mt-5 grid gap-5">
                <div>
                    <x-input-label for="appointment_id" :value="__('Completed appointment')" />
                    <select id="appointment_id" name="appointment_id" class="casa-input mt-2" required>
                        <option value="">{{ __('Select appointment') }}</option>
                        @foreach ($appointments as $appointment)
                            <option value="{{ $appointment->id }}" @selected((int) old('appointment_id', $selectedAppointmentId) === $appointment->id)>
                                {{ $appointment->appointment_number }} · {{ $appointment->service?->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('appointment_id')" />
                </div>

                <div>
                    <x-input-label for="rating" :value="__('Rating')" />
                    <select id="rating" name="rating" class="casa-input mt-2" required>
                        @for ($rating = 5; $rating >= 1; $rating--)
                            <option value="{{ $rating }}" @selected((int) old('rating', 5) === $rating)>{{ $rating }} / 5</option>
                        @endfor
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('rating')" />
                </div>

                <div>
                    <x-input-label for="comment" :value="__('Comment')" />
                    <textarea id="comment" name="comment" rows="6" class="casa-input mt-2">{{ old('comment') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('comment')" />
                </div>
            </div>
        </x-app-card>

        <aside class="space-y-4">
            <x-app-card>
                <p class="casa-section-label">{{ __('Sentiment') }}</p>
                <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('The system assigns a simple positive, neutral, or negative label from the rating and comment keywords.') }}</p>
            </x-app-card>
            <x-app-card>
                <div class="flex flex-col gap-3">
                    <button type="submit" class="casa-button-primary w-full">{{ __('Submit feedback') }}</button>
                    <a href="{{ route('customer.feedback.index') }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>
                </div>
            </x-app-card>
        </aside>
    </form>
</x-app-layout>
