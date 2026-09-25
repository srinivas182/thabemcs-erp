@extends('website.layout')

@section('content')
    <article class="mx-auto max-w-3xl px-5 pb-20 pt-16">
        <p class="text-xs uppercase tracking-wide text-ink-soft">
            {{ collect([$article['category'], $article['published'], $article['author']])->filter()->join(' · ') }}
        </p>
        <h1 class="mt-2 text-4xl font-bold leading-tight tracking-tight">{{ $article['title'] }}</h1>
        @if ($article['excerpt'])<p class="mt-4 text-xl text-ink-soft">{{ $article['excerpt'] }}</p>@endif

        <div class="mt-8 space-y-5 text-lg leading-relaxed">
            @foreach ($article['blocks'] as $block)
                @foreach (preg_split('/\n\s*\n/', (string) ($block['data']['body'] ?? '')) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            @endforeach
        </div>

        <p class="mt-10"><a href="/news" class="font-medium text-line hover:underline">All articles</a></p>
    </article>
@endsection
