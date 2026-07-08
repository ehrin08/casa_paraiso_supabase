<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Casa Paraiso') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=montserrat:600,700,800,900|poppins:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @php
            $servicePackages = config('casa.service_packages', []);
            $addons = config('casa.addons', []);
            $businessHours = config('casa.business_hours', []);
            $homeUrl = auth()->check() ? route(auth()->user()->homeRouteName()) : null;
        @endphp

        <x-page-loading />

        <div class="casa-page min-h-screen">
            <header class="border-b border-casa-border/80 bg-casa-bg/95 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="/" class="inline-flex items-center gap-3" data-prefetch>
                        <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" class="h-14 w-auto rounded-xl bg-white object-contain shadow-sm">
                    </a>

                    @if (Route::has('login'))
                        <nav class="flex items-center gap-3">
                            @auth
                                <a href="{{ $homeUrl }}" class="casa-button-secondary" data-prefetch>
                                    {{ __('Dashboard') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="hidden text-sm font-bold text-casa-muted transition hover:text-casa-primary sm:inline-flex" data-prefetch>
                                    {{ __('Log in') }}
                                </a>

                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="casa-button-primary" data-prefetch>
                                        {{ __('Register') }}
                                    </a>
                                @endif
                            @endauth
                        </nav>
                    @endif
                </div>
            </header>

            <main>
                <section class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(360px,0.95fr)] lg:px-8 lg:py-16">
                    <div class="flex flex-col justify-center">
                        <p class="casa-section-label">{{ config('casa.business_name') }}</p>
                        <h1 class="mt-5 font-display text-4xl font-black leading-[1.05] text-casa-text sm:text-5xl lg:text-6xl">
                            {{ __('Casa Paraiso Body and Wellness Spa') }}
                        </h1>
                        <p class="mt-6 max-w-2xl text-base leading-8 text-casa-muted">
                            {{ __('Signature full-body massage packages with clear durations, add-on options, and appointment-first service care.') }}
                        </p>
                        <p class="mt-5 max-w-xl rounded-2xl border border-casa-gold/40 bg-white/70 px-5 py-4 font-display text-xl font-black text-casa-primary shadow-casa-card">
                            {{ config('casa.marketing_line') }}
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @auth
                                <a href="{{ $homeUrl }}" class="casa-button-primary" data-prefetch>{{ __('Open dashboard') }}</a>
                            @else
                                <a href="{{ route('register') }}" class="casa-button-primary" data-prefetch>{{ __('Request appointment') }}</a>
                                <a href="{{ route('login') }}" class="casa-button-secondary" data-prefetch>{{ __('Staff log in') }}</a>
                            @endauth
                        </div>

                        <div class="mt-8 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-casa-border bg-white/75 p-4">
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-casa-primary">{{ __('Business hours') }}</p>
                                <p class="mt-2 font-display text-xl font-black text-casa-text">{{ $businessHours['summary'] ?? __('Open every day') }}</p>
                                <p class="mt-1 text-sm font-semibold text-casa-muted">{{ $businessHours['window'] ?? __('1:00 PM to 12:00 MN') }}</p>
                            </div>
                            <div class="rounded-2xl border border-casa-border bg-white/75 p-4">
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-casa-primary">{{ __('Starting rate') }}</p>
                                <p class="mt-2 font-display text-xl font-black text-casa-text">
                                    PHP {{ number_format((float) collect($servicePackages)->min('price'), 2) }}
                                </p>
                                <p class="mt-1 text-sm font-semibold text-casa-muted">{{ __('Full-body package menu') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="casa-dark-panel rounded-[28px] p-5 shadow-casa-lift">
                        <div class="rounded-[24px] bg-white p-4">
                            <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso brand mark" class="mx-auto h-40 w-auto object-contain sm:h-52">
                        </div>

                        <div class="mt-5 space-y-3">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.14em] text-casa-gold">{{ __('Massage menu') }}</p>
                                    <h2 class="mt-2 font-display text-2xl font-black text-white">{{ __('Four signature packages') }}</h2>
                                </div>
                                <p class="text-right text-xs font-bold uppercase tracking-[0.12em] text-casa-bg/70">{{ __('1 PM - 12 MN') }}</p>
                            </div>

                            @foreach ($servicePackages as $package)
                                <div class="rounded-2xl border border-white/10 bg-white/[0.08] p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 class="font-display text-lg font-black text-white">{{ $package['name'] }}</h3>
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.1em] text-casa-bg/70">{{ $package['duration_label'] }}</p>
                                        </div>
                                        <p class="font-display text-xl font-black text-casa-gold">PHP {{ number_format((float) $package['price'], 2) }}</p>
                                    </div>
                                    <p class="mt-3 text-sm leading-6 text-casa-bg/80">{{ $package['description'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="mx-auto max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">
                    <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                        <div>
                            <p class="casa-section-label">{{ __('Services') }}</p>
                            <h2 class="mt-3 font-display text-3xl font-black text-casa-text">{{ __('Package details') }}</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Each package keeps price, duration, and included treatments visible before customers request an appointment.') }}</p>
                        </div>
                        @guest
                            <a href="{{ route('register') }}" class="casa-button-primary" data-prefetch>{{ __('Reserve your spot') }}</a>
                        @endguest
                    </div>

                    <div class="grid gap-4 lg:grid-cols-4">
                        @foreach ($servicePackages as $package)
                            <article class="casa-card flex flex-col p-6">
                                <p class="casa-section-label">{{ $package['duration_label'] }}</p>
                                <h3 class="mt-3 font-display text-2xl font-black text-casa-text">{{ $package['name'] }}</h3>
                                <p class="mt-2 font-display text-3xl font-black text-casa-primary">PHP {{ number_format((float) $package['price'], 2) }}</p>
                                <p class="mt-4 text-sm leading-6 text-casa-muted">{{ $package['description'] }}</p>
                                <div class="mt-5 flex flex-wrap gap-2">
                                    @foreach ($package['includes'] as $include)
                                        <span class="rounded-full border border-casa-border bg-casa-bg px-3 py-1 text-xs font-bold text-casa-muted">{{ $include }}</span>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="mx-auto grid max-w-7xl gap-4 px-4 pb-16 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.42fr)] lg:px-8">
                    <div class="casa-card p-6">
                        <p class="casa-section-label">{{ __('Add-ons') }}</p>
                        <h2 class="mt-3 font-display text-2xl font-black text-casa-text">{{ __('Optional treatment extras') }}</h2>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($addons as $addon)
                                <div class="rounded-2xl border border-casa-border bg-casa-bg p-4">
                                    <p class="font-bold text-casa-text">{{ $addon['name'] }}</p>
                                    <p class="mt-1 text-sm font-semibold text-casa-primary">PHP {{ number_format((float) $addon['price'], 2) }}</p>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-5 text-sm leading-6 text-casa-muted">{{ __('Add-ons are listed for customer reference and can be coordinated with staff during appointment confirmation.') }}</p>
                    </div>

                    <div class="casa-card bg-casa-primary p-6 text-white">
                        <p class="text-xs font-black uppercase tracking-[0.14em] text-casa-gold">{{ __('Visit hours') }}</p>
                        <h2 class="mt-3 font-display text-3xl font-black">{{ $businessHours['summary'] ?? __('Open every day') }}</h2>
                        <p class="mt-3 text-lg font-bold text-white/90">{{ $businessHours['window'] ?? __('1:00 PM to 12:00 MN') }}</p>
                        <div class="my-6 h-px bg-white/20"></div>
                        <p class="font-display text-2xl font-black text-casa-gold">{{ config('casa.marketing_line') }}</p>
                        <p class="mt-4 text-sm leading-6 text-white/80">{{ __('Customers can create an account to request appointments, while staff confirm the final schedule.') }}</p>
                    </div>
                </section>

                <section class="mx-auto max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="casa-card p-6">
                            <p class="casa-section-label">{{ __('Appointments') }}</p>
                            <h2 class="mt-3 font-display text-xl font-black text-casa-text">{{ __('Request-first booking') }}</h2>
                            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('Customers request visits while staff keep control of final confirmation.') }}</p>
                        </div>
                        <div class="casa-card p-6">
                            <p class="casa-section-label">{{ __('Operations') }}</p>
                            <h2 class="mt-3 font-display text-xl font-black text-casa-text">{{ __('Role-specific workspaces') }}</h2>
                            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('Admin, staff, and customer screens stay focused on their daily decisions.') }}</p>
                        </div>
                        <div class="casa-card p-6">
                            <p class="casa-section-label">{{ __('Insights') }}</p>
                            <h2 class="mt-3 font-display text-xl font-black text-casa-text">{{ __('Simple service intelligence') }}</h2>
                            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('Feedback sentiment and RFM suggestions are planned without external services.') }}</p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
