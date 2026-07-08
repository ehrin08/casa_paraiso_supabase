@php
    $user = Auth::user();
    $dashboardRoute = $user->homeRouteName();
    $usesSidebar = $user->isAdmin() || $user->isStaff();

    $roleLabel = match (true) {
        $user->isAdmin() => 'Admin workspace',
        $user->isStaff() => 'Staff workspace',
        default => 'Customer lounge',
    };

    $navItems = match (true) {
        $user->isAdmin() => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
            ['label' => 'Appointments', 'route' => 'admin.appointments.index', 'active' => 'admin.appointments.*'],
            ['label' => 'Customers', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*'],
            ['label' => 'Staff', 'route' => 'admin.staff.index', 'active' => 'admin.staff.*'],
            ['label' => 'Services', 'route' => 'admin.services.index', 'active' => 'admin.services.*'],
            ['label' => 'Transactions', 'route' => 'admin.transactions.index', 'active' => 'admin.transactions.*'],
            ['label' => 'Promotions', 'route' => 'admin.promotions.index', 'active' => 'admin.promotions.*'],
            ['label' => 'Feedback', 'route' => 'admin.feedback.index', 'active' => 'admin.feedback.*'],
            ['label' => 'Reports', 'route' => 'admin.reports.index', 'active' => 'admin.reports.*'],
            ['label' => 'Settings', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*'],
        ],
        $user->isStaff() => [
            ['label' => 'Dashboard', 'route' => 'staff.dashboard', 'active' => 'staff.dashboard'],
            ['label' => 'Appointments', 'route' => 'staff.appointments.index', 'active' => 'staff.appointments.*'],
            ['label' => 'Customers', 'route' => 'staff.customers.index', 'active' => 'staff.customers.*'],
            ['label' => 'Transactions', 'route' => 'staff.transactions.index', 'active' => 'staff.transactions.*'],
            ['label' => 'Feedback', 'route' => 'staff.feedback.index', 'active' => 'staff.feedback.*'],
        ],
        default => [
            ['label' => 'Appointments', 'route' => 'customer.appointments.index', 'active' => 'customer.appointments.*'],
            ['label' => 'Request', 'route' => 'customer.appointments.create', 'active' => 'customer.appointments.create'],
            ['label' => 'Feedback', 'route' => 'customer.feedback.index', 'active' => 'customer.feedback.*'],
            ['label' => 'Profile', 'route' => 'customer.profile.edit', 'active' => ['customer.profile.*', 'profile.*']],
        ],
    };
@endphp

@if ($usesSidebar)
    <nav x-data="{ open: false }">
        <div class="sticky top-0 z-40 border-b border-casa-border bg-casa-bg/95 px-4 py-3 backdrop-blur lg:hidden">
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route($dashboardRoute) }}" class="min-w-0">
                    <x-application-logo class="scale-90 origin-left" />
                </a>

                <button type="button" class="grid size-11 place-items-center rounded-full border border-casa-border bg-white text-casa-primary shadow-sm" aria-label="Open navigation" @click="open = true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <aside class="casa-wood-panel fixed inset-y-0 start-0 z-30 hidden w-72 flex-col overflow-y-auto border-e border-white/10 p-5 lg:flex">
            <a href="{{ route($dashboardRoute) }}" class="rounded-[22px] bg-white/95 p-4 shadow-casa-lift">
                <x-application-logo />
            </a>

            <div class="mt-8">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-casa-gold">{{ $roleLabel }}</p>
                <p class="mt-2 text-sm leading-6 text-casa-bg/75">{{ $user->name }}</p>
            </div>

            <div class="mt-8 space-y-2">
                @foreach ($navItems as $item)
                    @php($isActive = request()->routeIs(...(array) $item['active']))
                    <a href="{{ route($item['route']) }}" @class([
                        'casa-nav-link w-full',
                        'casa-nav-link-active' => $isActive,
                    ])>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="mt-auto rounded-[20px] border border-white/10 bg-white/[0.08] p-4">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-casa-gold">Signed in</p>
                <p class="mt-2 truncate text-sm font-semibold text-white">{{ $user->email }}</p>

                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="casa-button-secondary w-full border-white/15 bg-white/10 text-white hover:bg-white/15 hover:text-white">
                        Log out
                    </button>
                </form>
            </div>
        </aside>

        <div x-show="open" class="fixed inset-0 z-50 lg:hidden" style="display: none;">
            <div class="absolute inset-0 bg-casa-charcoal/70" @click="open = false"></div>
            <aside class="casa-wood-panel absolute inset-y-0 start-0 flex w-[min(22rem,88vw)] flex-col p-5 shadow-casa-lift">
                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route($dashboardRoute) }}" class="rounded-2xl bg-white/95 p-3">
                        <x-application-logo class="scale-90 origin-left" />
                    </a>
                    <button type="button" class="grid size-10 place-items-center rounded-full border border-white/15 text-white" aria-label="Close navigation" @click="open = false">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="mt-8 space-y-2">
                    @foreach ($navItems as $item)
                        @php($isActive = request()->routeIs(...(array) $item['active']))
                        <a href="{{ route($item['route']) }}" @class([
                            'casa-nav-link w-full',
                            'casa-nav-link-active' => $isActive,
                        ]) @click="open = false">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-auto">
                    @csrf
                    <button type="submit" class="casa-button-secondary w-full border-white/15 bg-white/10 text-white hover:bg-white/15 hover:text-white">
                        Log out
                    </button>
                </form>
            </aside>
        </div>
    </nav>
@else
    <nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-casa-border bg-casa-bg/95 backdrop-blur">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-20 items-center justify-between gap-6">
                <a href="{{ route($dashboardRoute) }}" class="min-w-0">
                    <x-application-logo />
                </a>

                <div class="hidden items-center gap-7 md:flex">
                    @foreach ($navItems as $item)
                        @php($isActive = request()->routeIs(...(array) $item['active']))
                        <x-nav-link :href="route($item['route'])" :active="$isActive">
                            {{ $item['label'] }}
                        </x-nav-link>
                    @endforeach
                </div>

                <div class="hidden md:block">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-3 rounded-full border border-casa-border bg-white px-3 py-2 text-sm font-bold text-casa-text shadow-sm transition hover:border-casa-gold">
                                <span class="grid size-8 place-items-center rounded-full bg-casa-green/15 text-xs uppercase text-casa-green">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                <span>{{ $user->name }}</span>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>

                <button type="button" class="grid size-11 place-items-center rounded-full border border-casa-border bg-white text-casa-primary shadow-sm md:hidden" aria-label="Open navigation" @click="open = ! open">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <div x-show="open" class="border-t border-casa-border bg-casa-bg px-4 py-4 md:hidden" style="display: none;">
            <div class="space-y-2">
                @foreach ($navItems as $item)
                    @php($isActive = request()->routeIs(...(array) $item['active']))
                    <x-responsive-nav-link :href="route($item['route'])" :active="$isActive">
                        {{ $item['label'] }}
                    </x-responsive-nav-link>
                @endforeach
            </div>

            <div class="mt-4 border-t border-casa-border pt-4">
                <p class="text-sm font-bold text-casa-text">{{ $user->name }}</p>
                <p class="mt-1 text-sm text-casa-muted">{{ $user->email }}</p>

                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="casa-button-secondary w-full">
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </nav>
@endif
