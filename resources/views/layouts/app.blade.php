<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

        <title>{{ $title ?? config('app.name') }}</title>

        <meta name="description" content="Spot every French department plate. Two cars, one shared board.">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#ffffff">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#09090b">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Plate Hunt">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">

        <link rel="manifest" href="/manifest.json">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="bg-white text-zinc-950 dark:bg-zinc-950 dark:text-white">
        <div class="isolate">
            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
