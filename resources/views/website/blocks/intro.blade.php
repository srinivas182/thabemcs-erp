<section class="reveal mx-auto max-w-3xl px-5 py-16 sm:py-20">
    @if (! empty($data['heading']))<h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $data['heading'] }}</h2>@endif
    <div class="mt-5 space-y-4 text-lg leading-relaxed text-ink-soft">
        @foreach (preg_split('/\n\s*\n/', (string) ($data['body'] ?? '')) as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    </div>
</section>
