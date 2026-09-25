@extends('website.layout')

@section('content')
    <section class="mx-auto max-w-4xl px-5 pb-8 pt-16">
        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">News and insight</h1>
    </section>
    <section class="mx-auto max-w-4xl px-5 pb-20">
        <ul class="divide-y divide-concrete border-y border-concrete">
            @forelse ($articles as $article)
                <li class="py-7">
                    <a href="/news/{{ $article['slug'] }}" class="group block">
                        <p class="text-xs uppercase tracking-wide text-ink-soft">
                            {{ collect([$article['category'], $article['published']])->filter()->join(' · ') }}
                        </p>
                        <h2 class="mt-1 text-2xl font-bold group-hover:text-line">{{ $article['title'] }}</h2>
                        @if ($article['excerpt'])<p class="mt-2 text-ink-soft">{{ $article['excerpt'] }}</p>@endif
                    </a>
                </li>
            @empty
                <li class="py-7 text-ink-soft">Nothing published yet.</li>
            @endforelse
        </ul>
    </section>
@endsection
