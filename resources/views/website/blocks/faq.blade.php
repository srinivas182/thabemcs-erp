<section class="reveal mx-auto max-w-3xl px-5 py-16 sm:py-20">
    @if (! empty($data['heading']))<h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $data['heading'] }}</h2>@endif
    <div class="mt-8 divide-y divide-concrete border-y border-concrete">
        @foreach (($data['items'] ?? []) as $item)
            <details class="group py-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold">
                    {{ $item['question'] ?? '' }}
                    <span class="text-line transition group-open:rotate-45">+</span>
                </summary>
                <p class="mt-3 text-ink-soft">{{ $item['answer'] ?? '' }}</p>
            </details>
        @endforeach
    </div>
</section>
