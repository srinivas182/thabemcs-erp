<section class="reveal bg-ink text-plaster">
    <dl class="mx-auto grid max-w-6xl gap-10 px-5 py-16 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (($data['items'] ?? []) as $item)
            <div>
                <dt class="text-5xl font-bold tracking-tight text-white">{{ $item['value'] ?? '' }}</dt>
                <dd class="mt-2 text-sm uppercase tracking-wide text-plaster/60">{{ $item['label'] ?? '' }}</dd>
            </div>
        @endforeach
    </dl>
</section>
