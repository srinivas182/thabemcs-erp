<form method="post" action="/forms/{{ $form->slug }}" class="mt-8 grid gap-4">
    @csrf
    <input type="hidden" name="page" value="{{ url()->current() }}">

    {{-- A field no person can see. Robots fill it in; people do not. --}}
    <div class="absolute -left-[9999px]" aria-hidden="true">
        <label>Website<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label>
    </div>

    @foreach ($form->fields as $field)
        <label class="grid gap-1.5 text-sm font-medium">
            {{ $field['label'] }}@if (! ($field['required'] ?? false))<span class="font-normal text-ink-soft"> (optional)</span>@endif

            @if ($field['type'] === 'textarea')
                <textarea name="answers[{{ $field['name'] }}]" rows="4" @if ($field['required'] ?? false) required @endif
                          class="rounded-lg border border-concrete bg-white p-3 font-normal focus:border-line focus:outline-none focus:ring-2 focus:ring-line/30">{{ old('answers.'.$field['name']) }}</textarea>
            @elseif ($field['type'] === 'select')
                <select name="answers[{{ $field['name'] }}]" @if ($field['required'] ?? false) required @endif
                        class="h-11 rounded-lg border border-concrete bg-white px-3 font-normal focus:border-line focus:outline-none focus:ring-2 focus:ring-line/30">
                    <option value="">Choose</option>
                    @foreach (($field['options'] ?? []) as $option)
                        <option value="{{ $option }}" @selected(old('answers.'.$field['name']) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @else
                <input type="{{ ['email' => 'email', 'phone' => 'tel', 'date' => 'date'][$field['type']] ?? 'text' }}"
                       name="answers[{{ $field['name'] }}]" value="{{ old('answers.'.$field['name']) }}"
                       @if ($field['required'] ?? false) required @endif
                       @if ($field['type'] === 'phone') inputmode="tel" @endif
                       class="h-11 rounded-lg border border-concrete bg-white px-3 font-normal focus:border-line focus:outline-none focus:ring-2 focus:ring-line/30">
            @endif

            @error('answers.'.$field['name'])<span class="text-sm font-normal text-brick">{{ $message }}</span>@enderror
        </label>
    @endforeach

    <label class="flex items-start gap-3 text-sm">
        <input type="checkbox" name="consented" value="1" required class="mt-1 size-4 accent-[color:var(--color-line)]">
        <span>I agree that {{ $brand['name'] }} may use these details to answer my enquiry, as set out in their privacy notice (POPIA).</span>
    </label>
    @error('consented')<span class="text-sm text-brick">{{ $message }}</span>@enderror

    <button type="submit" class="justify-self-start rounded-full bg-line px-7 py-3 font-semibold text-white transition hover:bg-line-deep">Send</button>
</form>
