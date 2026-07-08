@php
    $user = Auth::user();
    $dashboardRoute = $user->homeRouteName();

    $roleLabel = match (true) {
        $user->isAdmin() => 'Admin workspace',
        $user->isStaff() => 'Staff workspace',
        default => 'Customer lounge',
    };

    $navGroups = match (true) {
        $user->isAdmin() => [
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
                ],
            ],
            [
                'label' => 'Operations',
                'items' => [
                    ['label' => 'Appointments', 'route' => 'admin.appointments.index', 'active' => 'admin.appointments.*'],
                    ['label' => 'Transactions', 'route' => 'admin.transactions.index', 'active' => 'admin.transactions.*'],
                ],
            ],
            [
                'label' => 'People',
                'items' => [
                    ['label' => 'Customers', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*'],
                    ['label' => 'Staff', 'route' => 'admin.staff.index', 'active' => 'admin.staff.*'],
                ],
            ],
            [
                'label' => 'Catalog',
                'items' => [
                    ['label' => 'Services', 'route' => 'admin.services.index', 'active' => 'admin.services.*'],
                ],
            ],
            [
                'label' => 'Insights',
                'items' => [
                    ['label' => 'Promotions', 'route' => 'admin.promotions.index', 'active' => 'admin.promotions.*'],
                    ['label' => 'Feedback', 'route' => 'admin.feedback.index', 'active' => 'admin.feedback.*'],
                    ['label' => 'Reports', 'route' => 'admin.reports.index', 'active' => 'admin.reports.*'],
                ],
            ],
            [
                'label' => 'Account',
                'items' => [
                    ['label' => 'Settings', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*'],
                    ['label' => 'Profile', 'route' => 'profile.edit', 'active' => 'profile.*'],
                ],
            ],
        ],
        $user->isStaff() => [
            [
                'label' => 'Today',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'staff.dashboard', 'active' => 'staff.dashboard'],
                    ['label' => 'Appointments', 'route' => 'staff.appointments.index', 'active' => 'staff.appointments.*'],
                ],
            ],
            [
                'label' => 'Records',
                'items' => [
                    ['label' => 'Customers', 'route' => 'staff.customers.index', 'active' => 'staff.customers.*'],
                    ['label' => 'Transactions', 'route' => 'staff.transactions.index', 'active' => 'staff.transactions.*'],
                    ['label' => 'Feedback', 'route' => 'staff.feedback.index', 'active' => 'staff.feedback.*'],
                ],
            ],
            [
                'label' => 'Account',
                'items' => [
                    ['label' => 'Profile', 'route' => 'profile.edit', 'active' => 'profile.*'],
                ],
            ],
        ],
        default => [
            [
                'label' => 'Appointments',
                'items' => [
                    ['label' => 'Appointments', 'route' => 'customer.appointments.index', 'active' => ['customer.appointments.index', 'customer.appointments.show']],
                    ['label' => 'Request', 'route' => 'customer.appointments.create', 'active' => 'customer.appointments.create'],
                ],
            ],
            [
                'label' => 'Care',
                'items' => [
                    ['label' => 'Feedback', 'route' => 'customer.feedback.index', 'active' => 'customer.feedback.*'],
                ],
            ],
            [
                'label' => 'Account',
                'items' => [
                    ['label' => 'Profile', 'route' => 'profile.edit', 'active' => 'profile.*'],
                ],
            ],
        ],
    };
@endphp

<nav x-data="{ open: false }">
    <div class="sticky top-0 z-40 border-b border-casa-border bg-white/95 px-4 py-2.5 backdrop-blur lg:hidden">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route($dashboardRoute) }}" class="min-w-0" data-prefetch>
                <x-application-logo class="scale-75 origin-left" />
            </a>

            <button type="button" class="casa-icon-button" aria-label="Open navigation" @click="open = true">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </div>

    <aside class="casa-wood-panel fixed inset-y-0 start-0 z-30 hidden w-64 flex-col overflow-y-auto border-e border-white/10 p-4 lg:flex">
        <a href="{{ route($dashboardRoute) }}" class="rounded-lg bg-white/95 p-3 shadow-casa-card" data-prefetch>
            <x-application-logo class="scale-90 origin-left" />
        </a>

        <div class="mt-5 rounded-lg border border-white/10 bg-white/[0.07] p-3">
            <p class="text-[0.68rem] font-black uppercase tracking-[0.18em] text-casa-gold">{{ $roleLabel }}</p>
            <p class="mt-1 truncate text-sm font-semibold text-white">{{ $user->name }}</p>
            <p class="mt-0.5 truncate text-xs text-casa-bg/65">{{ $user->email }}</p>
        </div>

        <div class="mt-5 space-y-5">
            @foreach ($navGroups as $group)
                <div>
                    <p class="px-3 text-[0.66rem] font-black uppercase tracking-[0.16em] text-casa-bg/48">{{ $group['label'] }}</p>
                    <div class="mt-2 space-y-1">
                        @foreach ($group['items'] as $item)
                            @php($isActive = request()->routeIs(...(array) $item['active']))
                            <a href="{{ route($item['route']) }}" data-prefetch @class([
                                'casa-nav-link w-full',
                                'casa-nav-link-active' => $isActive,
                            ])>
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-auto pt-6">
            @csrf
            <button type="submit" class="casa-button-secondary w-full border-white/15 bg-white/10 text-white hover:bg-white/15 hover:text-white">
                Log out
            </button>
        </form>
    </aside>

    <div x-show="open" class="fixed inset-0 z-50 lg:hidden" style="display: none;">
        <div class="absolute inset-0 bg-casa-charcoal/70" @click="open = false"></div>
        <aside class="casa-wood-panel absolute inset-y-0 start-0 flex w-[min(20rem,88vw)] flex-col overflow-y-auto p-4 shadow-casa-lift">
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route($dashboardRoute) }}" class="rounded-lg bg-white/95 p-3" data-prefetch>
                    <x-application-logo class="scale-75 origin-left" />
                </a>
                <button type="button" class="casa-icon-button border-white/15 bg-white/10 text-white" aria-label="Close navigation" @click="open = false">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="mt-5 rounded-lg border border-white/10 bg-white/[0.07] p-3">
                <p class="text-[0.68rem] font-black uppercase tracking-[0.18em] text-casa-gold">{{ $roleLabel }}</p>
                <p class="mt-1 truncate text-sm font-semibold text-white">{{ $user->name }}</p>
            </div>

            <div class="mt-5 space-y-5">
                @foreach ($navGroups as $group)
                    <div>
                        <p class="px-3 text-[0.66rem] font-black uppercase tracking-[0.16em] text-casa-bg/48">{{ $group['label'] }}</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($group['items'] as $item)
                                @php($isActive = request()->routeIs(...(array) $item['active']))
                                <a href="{{ route($item['route']) }}" data-prefetch @class([
                                    'casa-nav-link w-full',
                                    'casa-nav-link-active' => $isActive,
                                ]) @click="open = false">
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-auto pt-6">
                @csrf
                <button type="submit" class="casa-button-secondary w-full border-white/15 bg-white/10 text-white hover:bg-white/15 hover:text-white">
                    Log out
                </button>
            </form>
        </aside>
    </div>
</nav>
