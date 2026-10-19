<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#F4EFE6">
        @isset($publicHead)
            {{-- Link previews for WhatsApp / Instagram / Facebook crawlers, which run no JavaScript (F-002 AC-29–AC-31, ADR-0024). --}}
            <title>{{ $publicHead['title'] }}</title>
            @if ($publicHead['description'] !== null)
                <meta name="description" content="{{ $publicHead['description'] }}">
            @endif
            <link rel="canonical" href="{{ $publicHead['url'] }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="Rakyt">
            <meta property="og:locale" content="id_ID">
            <meta property="og:title" content="{{ $publicHead['og_title'] }}">
            @if ($publicHead['description'] !== null)
                <meta property="og:description" content="{{ $publicHead['description'] }}">
            @endif
            @if ($publicHead['image'] !== null)
                <meta property="og:image" content="{{ $publicHead['image'] }}">
            @endif
            <meta property="og:url" content="{{ $publicHead['url'] }}">
        @endisset
        @isset($plainHead)
            {{-- Customer booking pages: a title only, no Open Graph and no customer data (F-005 AC-36). --}}
            <title>{{ $plainHead['title'] }}</title>
            @if ($plainHead['noindex'])
                <meta name="robots" content="noindex">
            @endif
        @endisset
        {{-- Poppins @font-face + preloads from the Vite fonts helper; @vite does not emit them. --}}
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-inertia::head />
    </head>
    <body class="bg-gading font-sans text-ink antialiased">
        <x-inertia::app />
    </body>
</html>
