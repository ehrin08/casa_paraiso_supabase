<form method="POST" action="{{ $action }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Rule details') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Segment offer') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label for="rfm_segment_id" :value="__('RFM segment')" />
                <select id="rfm_segment_id" name="rfm_segment_id" class="casa-input mt-2" required>
                    <option value="">{{ __('Select segment') }}</option>
                    @foreach ($segments as $segment)
                        <option value="{{ $segment->id }}" @selected((int) old('rfm_segment_id', $promotionRule->rfm_segment_id) === $segment->id)>{{ $segment->name }}{{ $segment->is_active ? '' : ' (inactive)' }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('rfm_segment_id')" />
            </div>
            <div>
                <x-input-label for="name" :value="__('Rule name')" />
                <x-text-input id="name" name="name" type="text" class="mt-2" :value="old('name', $promotionRule->name)" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>
            <div>
                <x-input-label for="suggested_offer" :value="__('Suggested offer')" />
                <x-text-input id="suggested_offer" name="suggested_offer" type="text" class="mt-2" :value="old('suggested_offer', $promotionRule->suggested_offer)" required />
                <p class="mt-2 text-xs leading-5 text-casa-muted">{{ __('Example: Free aromatherapy add-on on the next completed visit.') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('suggested_offer')" />
            </div>
            <div>
                <x-input-label for="description" :value="__('Internal description')" />
                <textarea id="description" name="description" rows="4" class="casa-input mt-2">{{ old('description', $promotionRule->description) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('description')" />
            </div>
            <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promotionRule->is_active)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                <span>
                    <span class="block text-sm font-bold text-casa-text">{{ __('Active for generation') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Only active rules on active segments can produce new review items.') }}</span>
                </span>
            </label>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card>
            <p class="casa-section-label">{{ __('Review-first workflow') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('No automatic discount') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('This rule creates a suggestion for an administrator to review. It does not change prices or contact customers automatically.') }}</p>
        </x-app-card>
        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
                <a href="{{ route('admin.promotion-rules.index') }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>
            </div>
        </x-app-card>
    </aside>
</form>
