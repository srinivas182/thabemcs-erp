<section class="reveal bg-ink text-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-5 py-14">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">{{ $data['heading'] ?? '' }}</h2>
            @if (! empty($data['body']))<p class="mt-2 max-w-xl text-white/75">{{ $data['body'] }}</p>@endif
        </div>
        @if (! empty($data['button_label']))
            <a href="{{ $data['button_link'] ?? '#' }}" class="rounded-full bg-hivis px-7 py-3 font-semibold text-ink transition hover:brightness-95">
                {{ $data['button_label'] }}
            </a>
        @endif
    </div>
</section>
