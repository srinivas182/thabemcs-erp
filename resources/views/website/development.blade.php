@extends('website.layout')

@section('content')
    <section class="mx-auto max-w-6xl px-5 pb-8 pt-16">
        <p class="text-sm uppercase tracking-wide text-ink-soft">{{ $project['town'] }} &middot; {{ $project['stage'] }}</p>
        <h1 class="mt-2 text-4xl font-bold tracking-tight sm:text-5xl">{{ $project['name'] }}</h1>
        @if ($project['description'])<p class="mt-4 max-w-2xl text-lg text-ink-soft">{{ $project['description'] }}</p>@endif
        <p class="mt-6 text-lg font-semibold text-line-deep">
            {{ $available > 0 ? $available.' units still available' : 'Fully sold' }}
        </p>
    </section>

    @if ($units !== [])
        <section class="mx-auto max-w-6xl px-5 pb-16">
            <h2 class="text-2xl font-bold">What is available</h2>
            <div class="mt-6 overflow-x-auto rounded-2xl border border-concrete bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-plaster text-ink-soft">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Unit</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Size</th>
                            <th class="px-4 py-3 font-semibold">Bedrooms</th>
                            @if (($units[0]['price'] ?? null) !== null)<th class="px-4 py-3 text-right font-semibold">Price</th>@endif
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($units as $unit)
                            <tr class="border-t border-concrete">
                                <td class="px-4 py-3 font-medium">{{ $unit['reference'] }}</td>
                                <td class="px-4 py-3">{{ $unit['type'] }}</td>
                                <td class="px-4 py-3">{{ $unit['size'] ? $unit['size'].' m2' : '-' }}</td>
                                <td class="px-4 py-3">{{ $unit['bedrooms'] ?? '-' }}</td>
                                @if ($unit['price'] !== null)
                                    <td class="px-4 py-3 text-right">R{{ number_format($unit['price'], 0, '.', ' ') }}</td>
                                @endif
                                <td class="px-4 py-3">
                                    <span class="{{ $unit['status'] === 'available' ? 'text-line-deep' : 'text-ink-soft' }}">
                                        {{ $unit['status'] === 'available' ? 'Available' : 'Reserved' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-sm text-ink-soft">Availability is updated as units are reserved and sold.</p>
        </section>
    @endif

    @if ($enquiryForm)
        <section class="mx-auto max-w-3xl px-5 pb-20">
            <h2 class="text-2xl font-bold">Enquire about {{ $project['name'] }}</h2>
            @include('website.partials.form', ['form' => $enquiryForm])
        </section>
    @endif
@endsection
