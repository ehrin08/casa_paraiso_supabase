<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PromotionSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AppointmentAddons
{
    /** @return Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> */
    public function selected(array $codes): Collection
    {
        $catalog = $this->catalog()->keyBy('code');
        $selected = collect($codes)
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->unique()
            ->values();

        if ($selected->count() !== count($codes) || $selected->contains(fn (string $code) => ! $catalog->has($code))) {
            throw ValidationException::withMessages([
                'addon_codes' => __('Select valid add-ons without duplicates.'),
            ]);
        }

        return $selected->map(fn (string $code) => $catalog->get($code))->values();
    }

    /** @return Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> */
    public function catalog(): Collection
    {
        return collect(config('casa.addons', []))
            ->map(fn (array $addon) => [
                'code' => (string) $addon['code'],
                'name' => (string) $addon['name'],
                'price' => (float) $addon['price'],
                'duration_minutes' => (int) ($addon['duration_minutes'] ?? 0),
            ])
            ->values();
    }

    /** @param Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> $addons */
    public function durationMinutes(Collection $addons): int
    {
        return (int) $addons->sum('duration_minutes');
    }

    /** @param Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> $addons */
    public function total(Collection $addons): float
    {
        return (float) $addons->sum('price');
    }

    /** @param Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> $addons */
    public function assertDoesNotDuplicateVoucher(Collection $addons, ?PromotionSuggestion $voucher): void
    {
        if ($voucher?->addon_code && $addons->contains('code', $voucher->addon_code)) {
            throw ValidationException::withMessages([
                'addon_codes' => __('A voucher add-on cannot also be selected as a paid add-on.'),
            ]);
        }
    }

    /** @param Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> $addons */
    public function sync(Appointment $appointment, Collection $addons): void
    {
        $appointment->addons()->delete();
        $appointment->addons()->createMany($addons->map(fn (array $addon) => [
            'addon_code' => $addon['code'],
            'addon_name' => $addon['name'],
            'price' => $addon['price'],
            'duration_minutes' => $addon['duration_minutes'],
        ])->all());
    }
}
