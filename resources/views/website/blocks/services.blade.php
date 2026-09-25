<section class="reveal mx-auto max-w-6xl px-5 py-16 sm:py-20">
    @if (! empty($data['heading']))<h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $data['heading'] }}</h2>@endif
    <ul class="mt-10 grid gap-px overflow-hidden rounded-2xl border border-concrete bg-concrete sm:grid-cols-2 lg:grid-cols-3">
        @foreach (($data['items'] ?? []) as $item)
            <li class="bg-plaster p-7">
                <span class="flex size-11 items-center justify-center rounded-full bg-line-wash text-line-deep font-bold">{{ $loop->iteration }}</span>
                <h3 class="mt-4 text-lg font-bold">{{ $item['title'] ?? '' }}</h3>
                <p class="mt-2 text-ink-soft">{{ $item['body'] ?? '' }}</p>
            </li>
        @endforeach
    </ul>
</section>
