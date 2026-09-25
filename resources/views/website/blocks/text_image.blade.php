<section class="reveal mx-auto grid max-w-6xl gap-10 px-5 py-16 sm:py-20 lg:grid-cols-2 lg:items-center">
    <div class="{{ ($data['image_side'] ?? 'right') === 'left' ? 'lg:order-2' : '' }}">
        @if (! empty($data['heading']))<h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $data['heading'] }}</h2>@endif
        <div class="mt-5 space-y-4 text-lg leading-relaxed text-ink-soft">
            @foreach (preg_split('/\n\s*\n/', (string) ($data['body'] ?? '')) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    </div>
    @if (! empty($data['image']))
        <img src="{{ $data['image'] }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-2xl object-cover">
    @endif
</section>
