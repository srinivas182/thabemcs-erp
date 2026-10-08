@php
    $show = $data['show'] ?? 'all';
    $list = collect($developments ?? [])
        ->when($show === 'selling', fn ($c) => $c->where('available', '>', 0))
        ->when($show === 'complete', fn ($c) => $c->where('status', 'completed'))
        ->when($show === 'under_construction', fn ($c) => $c->where('status', 'active'))
        ->take((int) ($data['limit'] ?? 6));
@endphp
<section class="reveal mx-auto max-w-6xl px-5 py-16 sm:py-20">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $data['heading'] ?? 'Our developments' }}</h2>
        <a href="/developments" class="font-medium text-line hover:underline">See them all</a>
    </div>
    <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($list as $development)
            <li class="group overflow-hidden rounded-2xl border border-concrete bg-white transition hover:-translate-y-1 hover:shadow-lg">
                <a href="/developments/{{ $development['code'] }}" class="block">
                    @if (! empty($development['image']))
                        <img src="{{ $development['image'] }}" alt="{{ $development['name'] }}" loading="lazy"
                             class="aspect-[16/10] w-full object-cover">
                    @endif
                    <span class="block p-6">
                    <p class="text-xs uppercase tracking-wide text-ink-soft">{{ $development['town'] }}</p>
                    <h3 class="mt-1 text-xl font-bold group-hover:text-line">{{ $development['name'] }}</h3>
                    <p class="mt-2 line-clamp-3 text-sm text-ink-soft">{{ $development['description'] }}</p>
                    <p class="mt-4 text-sm font-medium">
                        @if (($data['show_availability'] ?? true) && $development['available'] > 0)
                            <span class="text-line-deep">{{ $development['available'] }} of {{ $development['units'] }} still available</span>
                        @elseif ($development['units'] > 0)
                            <span class="text-ink-soft">All {{ $development['units'] }} units sold</span>
                        @else
                            <span class="text-ink-soft">{{ $development['stage'] }}</span>
                        @endif
                    </p>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
