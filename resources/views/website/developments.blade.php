@extends('website.layout')

@section('content')
    <section class="mx-auto max-w-6xl px-5 pb-8 pt-16">
        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">Our developments</h1>
        <p class="mt-4 max-w-2xl text-lg text-ink-soft">
            What we are building now, what is still available, and what we have delivered. Availability comes straight
            from our own records, so what you see here is what is actually on the market today.
        </p>
    </section>

    <section class="mx-auto max-w-6xl px-5 pb-20">
        <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($developments as $development)
                <li class="group overflow-hidden rounded-2xl border border-concrete bg-white transition hover:-translate-y-1 hover:shadow-lg">
                    <a href="/developments/{{ $development['code'] }}" class="block p-6">
                        <p class="text-xs uppercase tracking-wide text-ink-soft">{{ $development['town'] }} &middot; {{ $development['stage'] }}</p>
                        <h2 class="mt-1 text-xl font-bold group-hover:text-line">{{ $development['name'] }}</h2>
                        <p class="mt-2 line-clamp-3 text-sm text-ink-soft">{{ $development['description'] }}</p>
                        <p class="mt-4 text-sm font-medium">
                            @if ($development['available'] > 0)
                                <span class="text-line-deep">{{ $development['available'] }} available</span>
                                @if ($development['fromPrice'])
                                    <span class="text-ink-soft"> &middot; from R{{ number_format($development['fromPrice'], 0, '.', ' ') }}</span>
                                @endif
                            @elseif ($development['units'] > 0)
                                <span class="text-ink-soft">Sold out</span>
                            @endif
                        </p>
                    </a>
                </li>
            @empty
                <li class="text-ink-soft">Our next development will be announced shortly.</li>
            @endforelse
        </ul>
    </section>
@endsection
