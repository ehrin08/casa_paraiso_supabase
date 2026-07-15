<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CustomerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MobileCustomerProfileController
{
    public function show(Request $request): JsonResponse
    {
        if (! $request->user()->customerProfile) {
            return $this->missingCustomerProfile();
        }

        return response()->json(['data' => $this->profile($request)])
            ->header('Cache-Control', 'no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_preference' => ['nullable', Rule::in(array_keys(CustomerProfile::CONTACT_PREFERENCES))],
        ]);
        $user = $request->user();

        DB::transaction(function () use ($data, $user): void {
            $user->update([
                'name' => trim((string) $data['name']),
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            ]);

            CustomerProfile::provisionFor($user)->update([
                'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
                'contact_preference' => $data['contact_preference'] ?? null,
            ]);
        });

        $request->setUserResolver(fn () => $user->fresh());

        return response()->json([
            'data' => $this->profile($request),
            'message' => 'Profile updated.',
        ])->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function profile(Request $request): array
    {
        $user = $request->user();
        $profile = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $profile->address,
            'contact_preference' => $profile->contact_preference,
            'customer_code' => $profile->customer_code,
            'has_password' => filled($user->password),
            'google_linked' => filled($user->google_id),
            'contact_preferences' => collect(CustomerProfile::CONTACT_PREFERENCES)
                ->map(fn (string $label, string $value): array => compact('value', 'label'))
                ->values(),
        ];
    }

    private function missingCustomerProfile(): JsonResponse
    {
        return response()->json(['error' => [
            'code' => 'CUSTOMER_PROFILE_REQUIRED',
            'message' => 'This account does not have a customer profile.',
        ]], 403)->header('Cache-Control', 'no-store');
    }
}
