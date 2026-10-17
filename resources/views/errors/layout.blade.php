{{-- Bahasa error pages (F-009 A17). Self-contained CSS so they render even when assets fail. Ink-outline only: no gold CTA. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · Rakyt</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: #F4EFE6; color: #211710; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; }
        main { max-width: 28rem; min-height: 100vh; margin: 0 auto; padding: 3rem 1.25rem; display: flex; flex-direction: column; justify-content: center; }
        .code { font-family: 'Poppins', Arial, sans-serif; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #6F5B4B; margin: 0 0 0.5rem; }
        h1 { font-family: 'Poppins', Arial, sans-serif; font-size: 1.5rem; line-height: 1.3; margin: 0 0 0.75rem; overflow-wrap: anywhere; }
        p { font-size: 1rem; line-height: 1.5; color: #6F5B4B; margin: 0 0 1.5rem; overflow-wrap: anywhere; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 1.25rem; border: 2px solid #211710; border-radius: 0.75rem; background: transparent; color: #211710; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .button:focus-visible { outline: 3px solid #6E4527; outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @section('actions')
                <a class="button" href="{{ url('/') }}">Ke beranda</a>
            @show
        </div>
    </main>
</body>
</html>
