<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Admin\AddonRequest;
use App\Models\Addon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MobileAdminAddonController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $search = trim((string) ($data['q'] ?? ''));
        $addons = Addon::query()->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))->when(($data['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))->when(($data['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))->orderByDesc('is_active')->orderBy('name')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();
        return response()->json(['data' => $addons->getCollection()->map($this->serialize(...))->values(), 'summary' => ['active' => Addon::where('is_active', true)->count(), 'inactive' => Addon::where('is_active', false)->count()], 'meta' => ['current_page' => $addons->currentPage(), 'last_page' => $addons->lastPage(), 'per_page' => $addons->perPage(), 'total' => $addons->total(), 'from' => $addons->firstItem(), 'to' => $addons->lastItem()]])->header('Cache-Control', 'no-store');
    }

    public function store(AddonRequest $request): JsonResponse
    {
        $data = $request->validated(); $data['code'] = $this->uniqueCode($data['name']); $data['is_active'] = $request->boolean('is_active');
        return response()->json(['data' => $this->serialize(Addon::create($data)), 'message' => 'Add-on created.'], 201);
    }

    public function update(AddonRequest $request, Addon $addon): JsonResponse
    {
        $data = $request->validated(); $data['is_active'] = $request->boolean('is_active'); $addon->update($data);
        return response()->json(['data' => $this->serialize($addon->refresh()), 'message' => 'Add-on updated.']);
    }

    public function toggle(Addon $addon): JsonResponse
    {
        $addon->update(['is_active' => ! $addon->is_active]);
        return response()->json(['data' => $this->serialize($addon->refresh()), 'message' => $addon->is_active ? 'Add-on activated.' : 'Add-on deactivated.']);
    }

    private function serialize(Addon $addon): array { return ['id' => $addon->id, 'code' => $addon->code, 'name' => $addon->name, 'description' => $addon->description, 'duration_minutes' => $addon->duration_minutes, 'price' => number_format((float) $addon->price, 2, '.', ''), 'is_active' => (bool) $addon->is_active]; }
    private function uniqueCode(string $name): string { $base = Str::slug($name) ?: 'addon'; $code = $base; for ($suffix = 2; Addon::where('code', $code)->exists(); $suffix++) $code = "{$base}-{$suffix}"; return $code; }
}
