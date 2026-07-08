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
    <body class="font-sans antialiased">
        @php
            $usesSidebar = Auth::check();
        @endphp

        <x-page-loading />

        <div class="casa-page">
            @include('layouts.navigation')
            <x-toast-stack />

            <x-modal-host />

            <div @class(['min-h-screen', 'lg:ps-64' => $usesSidebar])>
                <!-- Page Heading -->
                @isset($header)
                    <header data-page-header class="border-b border-casa-border/70 bg-white/92 backdrop-blur">
                        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main data-page-content class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
