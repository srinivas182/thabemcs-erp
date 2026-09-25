<section class="relative isolate flex min-h-[70vh] items-end overflow-hidden bg-ink text-white">
    @if (! empty($data['image']))
        <img src="{{ $data['image'] }}" alt="" class="absolute inset-0 -z-10 size-full object-cover" fetchpriority="high">
        @if ($data['overlay'] ?? true)
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-ink via-ink/70 to-ink/20"></div>
        @endif
    @endif
    <div class="mx-auto w-full max-w-6xl px-5 py-16 sm:py-24">
        <h1 class="max-w-3xl text-4xl font-bold leading-[1.05] tracking-tight sm:text-6xl">{{ $data['heading'] ?? '' }}</h1>
        @if (! empty($data['subheading']))
            <p class="mt-5 max-w-2xl text-lg text-white/80">{{ $data['subheading'] }}</p>
        @endif
        @if (! empty($data['button_label']))
            <a href="{{ $data['button_link'] ?? '#' }}" class="mt-8 inline-block rounded-full bg-line px-7 py-3 font-semibold transition hover:bg-line-deep">
                {{ $data['button_label'] }}
            </a>
        @endif
    </div>
</section>
