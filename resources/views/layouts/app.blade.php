<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Casa Paraiso') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:600,700|manrope:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" data-workspace-role="{{ Auth::user()?->role }}">
        @php
            $usesSidebar = Auth::check();
            $isCustomer = Auth::user()?->isCustomer() ?? false;
        @endphp

        <x-page-loading />

        <div class="casa-page casa-app-page">
            @include('layouts.navigation')
            <x-toast-stack />

            <x-modal-host />

            <div @class([
                'min-h-screen',
                'pb-24 lg:pb-0' => $isCustomer,
                'lg:ps-72' => $usesSidebar,
            ])>
                <!-- Page Heading -->
                @isset($header)
                    <header data-page-header class="border-b border-casa-border/80 bg-casa-paper/90 backdrop-blur-xl">
                        <div class="mx-auto flex max-w-[90rem] flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main data-page-content class="mx-auto max-w-[90rem] px-4 py-5 sm:px-6 lg:px-8 lg:py-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
