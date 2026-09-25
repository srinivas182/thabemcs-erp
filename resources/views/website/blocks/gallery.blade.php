<section class="reveal mx-auto max-w-6xl px-5 py-16 sm:py-20">
    @if (! empty($data['heading']))<h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $data['heading'] }}</h2>@endif
    <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (($data['images'] ?? []) as $image)
            <li>
                <img src="{{ $image['image'] ?? '' }}" alt="{{ $image['caption'] ?? '' }}" loading="lazy"
                     class="aspect-[4/3] w-full rounded-xl object-cover">
                @if (! empty($image['caption']))<p class="mt-2 text-sm text-ink-soft">{{ $image['caption'] }}</p>@endif
            </li>
        @endforeach
    </ul>
</section>
