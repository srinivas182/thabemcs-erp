<section class="reveal mx-auto max-w-6xl px-5 py-16 sm:py-20">
    @if (! empty($data['heading']))<h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $data['heading'] }}</h2>@endif
    <ul class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (($data['people'] ?? []) as $person)
            <li>
                @if (! empty($person['photo']))
                    <img src="{{ $person['photo'] }}" alt="{{ $person['name'] ?? '' }}" class="aspect-[4/5] w-full rounded-2xl object-cover" loading="lazy">
                @endif
                <h3 class="mt-4 text-lg font-bold">{{ $person['name'] ?? '' }}</h3>
                <p class="text-sm text-line-deep">{{ $person['role'] ?? '' }}</p>
                <p class="mt-2 text-sm text-ink-soft">{{ $person['bio'] ?? '' }}</p>
            </li>
        @endforeach
    </ul>
</section>
