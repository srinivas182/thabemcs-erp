<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] ?? $title ?? $brand['name'] }} | {{ $brand['name'] }}</title>
    <meta name="description" content="{{ $seo['description'] ?? $brand['tagline'] }}">
    @if (($seo['noindex'] ?? false))
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- How the page looks when someone shares it on WhatsApp or LinkedIn --}}
    <meta property="og:title" content="{{ $seo['title'] ?? $title ?? $brand['name'] }}">
    <meta property="og:description" content="{{ $seo['description'] ?? $brand['tagline'] }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @if (! empty($seo['image']))<meta property="og:image" content="{{ $seo['image'] }}">@endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite('resources/css/website.css')
</head>
<body class="bg-plaster text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-ink focus:px-4 focus:py-2 focus:text-white">Skip to the main content</a>

    <header class="sticky top-0 z-40 border-b border-concrete bg-plaster/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-5 py-4">
            <a href="/" class="text-lg font-bold tracking-tight">{{ $brand['name'] }}</a>

            <nav aria-label="Main" class="hidden items-center gap-6 md:flex">
                @foreach ($primaryMenu as $item)
                    <a href="{{ $item['link'] }}" class="text-sm font-medium text-ink-soft transition hover:text-ink">{{ $item['label'] }}</a>
                @endforeach
                <a href="/login" class="rounded-full border border-concrete px-4 py-1.5 text-sm font-medium transition hover:border-line hover:text-line">Staff sign in</a>
            </nav>

            <details class="md:hidden">
                <summary class="cursor-pointer list-none rounded border border-concrete px-3 py-2 text-sm">Menu</summary>
                <nav aria-label="Main" class="absolute left-0 right-0 mt-3 grid gap-1 border-y border-concrete bg-plaster px-5 py-4">
                    @foreach ($primaryMenu as $item)
                        <a href="{{ $item['link'] }}" class="py-2 font-medium">{{ $item['label'] }}</a>
                    @endforeach
                    <a href="/login" class="py-2 text-ink-soft">Staff sign in</a>
                </nav>
            </details>
        </div>
    </header>

    <main id="main">
        @if (session('sent'))
            <p role="status" class="mx-auto mt-6 max-w-3xl rounded bg-line-wash px-5 py-4 text-line-deep">{{ session('sent') }}</p>
        @endif
        @if (session('error'))
            <p role="alert" class="mx-auto mt-6 max-w-3xl rounded bg-brick-wash px-5 py-4 text-brick">{{ session('error') }}</p>
        @endif

        @yield('content')
    </main>

    <footer class="mt-24 border-t border-concrete bg-ink text-plaster">
        <div class="mx-auto grid max-w-6xl gap-8 px-5 py-14 sm:grid-cols-3">
            <div>
                <p class="text-lg font-bold">{{ $brand['name'] }}</p>
                <p class="mt-2 text-sm text-plaster/70">{{ $brand['tagline'] }}</p>
            </div>
            <nav aria-label="Footer" class="grid content-start gap-2 text-sm">
                @foreach ($footerMenu as $item)
                    <a href="{{ $item['link'] }}" class="text-plaster/80 transition hover:text-white">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="grid content-start gap-2 text-sm text-plaster/80">
                @if ($brand['email'])<a href="mailto:{{ $brand['email'] }}" class="hover:text-white">{{ $brand['email'] }}</a>@endif
                @if ($brand['phone'])<a href="tel:{{ $brand['phone'] }}" class="hover:text-white">{{ $brand['phone'] }}</a>@endif
                <a href="/news" class="hover:text-white">News and insight</a>
            </div>
        </div>
        <div class="border-t border-white/10">
            <p class="mx-auto max-w-6xl px-5 py-5 text-xs text-plaster/60">
                &copy; {{ date('Y') }} {{ $brand['owner'] ?: $brand['name'] }}. All rights reserved.
                We handle personal information in line with POPIA.
            </p>
        </div>
    </footer>
</body>
</html>
