<section class="reveal bg-line-wash">
    <div class="mx-auto max-w-6xl px-5 py-16 sm:py-20">
        <ul class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (($data['items'] ?? []) as $item)
                <li class="rounded-2xl bg-white p-7 shadow-sm">
                    <blockquote class="text-lg leading-relaxed">&ldquo;{{ $item['quote'] ?? '' }}&rdquo;</blockquote>
                    <p class="mt-4 font-semibold">{{ $item['name'] ?? '' }}</p>
                    <p class="text-sm text-ink-soft">{{ $item['role'] ?? '' }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
