<section class="reveal mx-auto max-w-4xl px-5 py-16 sm:py-20">
    @if (! empty($data['heading']))<h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $data['heading'] }}</h2>@endif
    <ol class="mt-10 grid gap-8">
        @foreach (($data['steps'] ?? []) as $step)
            <li class="grid grid-cols-[auto_1fr] gap-5">
                <span class="flex size-10 items-center justify-center rounded-full border-2 border-line font-bold text-line">{{ $loop->iteration }}</span>
                <div>
                    <h3 class="text-lg font-bold">{{ $step['title'] ?? '' }}</h3>
                    <p class="mt-1 text-ink-soft">{{ $step['body'] ?? '' }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</section>
