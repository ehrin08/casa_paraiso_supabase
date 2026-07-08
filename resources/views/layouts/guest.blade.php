<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Casa Paraiso') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=montserrat:600,700,800,900|poppins:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-casa-text antialiased">
        <x-page-loading />
        <x-toast-stack />

        <div class="grid min-h-screen bg-casa-bg lg:grid-cols-[minmax(0,1fr)_minmax(420px,520px)]">
            <section class="casa-dark-panel relative hidden overflow-hidden px-10 py-12 lg:flex lg:flex-col lg:justify-between">
                <div class="relative z-10">
                    <a href="/" class="inline-flex rounded-2xl bg-white/95 p-3 shadow-casa-lift" data-prefetch>
                        <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" class="h-20 w-auto rounded-xl object-contain">
                    </a>
                </div>

                <div class="relative z-10 max-w-xl">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-casa-gold">Tropical wellness sanctuary</p>
                    <h1 class="mt-4 font-display text-4xl font-black leading-tight text-white">
                        Calm booking and spa operations in one warm workspace.
                    </h1>
                    <p class="mt-5 max-w-md text-sm leading-7 text-casa-bg/80">
                        Designed for reservations, customer care, staff schedules, and thoughtful wellness service management.
                    </p>
                </div>

                <div class="relative z-10 grid grid-cols-3 gap-3 text-xs font-bold uppercase tracking-[0.12em] text-casa-bg/80">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.08] p-4">Bookings</div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.08] p-4">Services</div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.08] p-4">Care</div>
                </div>
            </section>

            <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
                <div class="w-full max-w-md">
                    <div class="mb-8 flex justify-center lg:hidden">
                        <a href="/" class="inline-flex rounded-2xl bg-white p-3 shadow-casa-card" data-prefetch>
                            <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" class="h-16 w-auto rounded-xl object-contain">
                        </a>
                    </div>

                    <div class="casa-card p-6 sm:p-8">
                        <div class="mb-6">
                            <p class="casa-section-label">{{ $eyebrow ?? __('Casa Paraiso') }}</p>
                            <h1 class="mt-2 font-display text-2xl font-black text-casa-text">
                                {{ $title ?? __('Welcome') }}
                            </h1>
                            @isset($subtitle)
                                <p class="mt-2 text-sm leading-6 text-casa-muted">{{ $subtitle }}</p>
                            @endisset
                        </div>

                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
