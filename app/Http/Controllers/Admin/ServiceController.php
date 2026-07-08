<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        $services = Service::query()
            ->withCount(['staffProfiles', 'appointments'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10);

        return view('admin.services.index', [
            'services' => $services,
            'activeCount' => Service::query()->where('is_active', true)->count(),
            'inactiveCount' => Service::query()->where('is_active', false)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.services.create', [
            'service' => new Service(['is_active' => true]),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        $service = Service::query()->create($data);

        return redirect()
            ->route('admin.services.show', $service)
            ->with('status', 'service-created');
    }

    public function show(Service $service): View
    {
        $service->loadCount(['staffProfiles', 'appointments', 'transactions']);

        return view('admin.services.show', [
            'service' => $service,
        ]);
    }

    public function edit(Service $service): View
    {
        return view('admin.services.edit', [
            'service' => $service,
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        if ($service->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $service);
        }

        $service->update($data);

        return redirect()
            ->route('admin.services.show', $service)
            ->with('status', 'service-updated');
    }

    public function toggle(Service $service): RedirectResponse
    {
        $service->update([
            'is_active' => ! $service->is_active,
        ]);

        return redirect()
            ->route('admin.services.index')
            ->with('status', $service->is_active ? 'service-activated' : 'service-deactivated');
    }

    private function uniqueSlug(string $name, ?Service $service = null): string
    {
        $baseSlug = Str::slug($name) ?: 'service';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            Service::withTrashed()
                ->where('slug', $slug)
                ->when($service, fn ($query) => $query->whereKeyNot($service->getKey()))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
