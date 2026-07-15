<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MobileAdminServiceController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $search = trim((string) ($data['q'] ?? ''));
        $services = Service::query()->withCount(['staffProfiles', 'appointments', 'transactions'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->when(($data['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($data['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json([
            'data' => $services->getCollection()->map($this->serialize(...))->values(),
            'summary' => ['active' => Service::query()->where('is_active', true)->count(), 'inactive' => Service::query()->where('is_active', false)->count()],
            'meta' => $this->pagination($services),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Service $service): JsonResponse
    {
        $service->loadCount(['staffProfiles', 'appointments', 'transactions']);

        return response()->json(['data' => $this->serialize($service)])->header('Cache-Control', 'no-store');
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $service = Service::query()->create($data)->loadCount(['staffProfiles', 'appointments', 'transactions']);

        return response()->json(['data' => $this->serialize($service), 'message' => 'Service created.'], 201)->header('Cache-Control', 'no-store');
    }

    public function update(ServiceRequest $request, Service $service): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        if ($service->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $service);
        }
        $service->update($data);
        $service->loadCount(['staffProfiles', 'appointments', 'transactions']);

        return response()->json(['data' => $this->serialize($service), 'message' => 'Service updated.'])->header('Cache-Control', 'no-store');
    }

    public function toggle(Service $service): JsonResponse
    {
        $service->update(['is_active' => ! $service->is_active]);
        $service->loadCount(['staffProfiles', 'appointments', 'transactions']);

        return response()->json(['data' => $this->serialize($service), 'message' => $service->is_active ? 'Service activated.' : 'Service deactivated.'])->header('Cache-Control', 'no-store');
    }

    private function serialize(Service $service): array
    {
        return ['id' => $service->id, 'name' => $service->name, 'slug' => $service->slug, 'description' => $service->description, 'duration_minutes' => $service->duration_minutes, 'price' => number_format((float) $service->price, 2, '.', ''), 'is_active' => (bool) $service->is_active, 'staff_count' => (int) ($service->staff_profiles_count ?? 0), 'appointments_count' => (int) ($service->appointments_count ?? 0), 'transactions_count' => (int) ($service->transactions_count ?? 0)];
    }

    private function uniqueSlug(string $name, ?Service $service = null): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        for ($suffix = 2; Service::withTrashed()->where('slug', $slug)->when($service, fn ($query) => $query->whereKeyNot($service->id))->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }
}
