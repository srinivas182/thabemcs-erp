@php $form = ($forms ?? collect())->get($data['form'] ?? ''); @endphp
<section class="reveal mx-auto max-w-3xl px-5 py-16 sm:py-20">
    @if (! empty($data['heading']))<h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $data['heading'] }}</h2>@endif
    @if (! empty($data['body']))<p class="mt-3 text-lg text-ink-soft">{{ $data['body'] }}</p>@endif

    @if ($form)
        @include('website.partials.form', ['form' => $form])
    @else
        <p class="mt-6 text-ink-soft">This form is not set up yet.</p>
    @endif
</section>
