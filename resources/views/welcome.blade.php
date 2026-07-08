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
        <div class="casa-page min-h-screen">
            <header class="border-b border-casa-border/80 bg-casa-bg/95 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="/" class="inline-flex items-center gap-3">
                        <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" class="h-14 w-auto rounded-xl bg-white object-contain shadow-sm">
                    </a>

                    @if (Route::has('login'))
                        <nav class="flex items-center gap-3">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="casa-button-secondary">
                                    {{ __('Dashboard') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="hidden text-sm font-bold text-casa-muted transition hover:text-casa-primary sm:inline-flex">
                                    {{ __('Log in') }}
                                </a>

                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="casa-button-primary">
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
                        <p class="casa-section-label">{{ __('Casa Paraiso Body and Wellness Spa') }}</p>
                        <h1 class="mt-5 font-display text-4xl font-black leading-[1.05] text-casa-text sm:text-5xl lg:text-6xl">
                            {{ __('A warm tropical sanctuary for spa bookings and care.') }}
                        </h1>
                        <p class="mt-6 max-w-2xl text-base leading-8 text-casa-muted">
                            {{ __('Request appointments, coordinate staff schedules, record transactions, and keep wellness service details in one calm, premium workspace.') }}
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="casa-button-primary">{{ __('Open dashboard') }}</a>
                            @else
                                <a href="{{ route('register') }}" class="casa-button-primary">{{ __('Request appointment') }}</a>
                                <a href="{{ route('login') }}" class="casa-button-secondary">{{ __('Staff log in') }}</a>
                            @endauth
                        </div>
                    </div>

                    <div class="casa-dark-panel rounded-[28px] p-5 shadow-casa-lift">
                        <div class="rounded-[24px] bg-white p-4">
                            <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso brand mark" class="mx-auto h-40 w-auto object-contain sm:h-52">
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-white/10 bg-white/[0.08] p-4">
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-casa-gold">{{ __('Book') }}</p>
                                <p class="mt-2 text-sm leading-6 text-casa-bg/80">{{ __('Customer appointment requests') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/[0.08] p-4">
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-casa-gold">{{ __('Manage') }}</p>
                                <p class="mt-2 text-sm leading-6 text-casa-bg/80">{{ __('Staff and service operations') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/[0.08] p-4">
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-casa-gold">{{ __('Review') }}</p>
                                <p class="mt-2 text-sm leading-6 text-casa-bg/80">{{ __('Feedback and promotions') }}</p>
                            </div>
                        </div>
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
