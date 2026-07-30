@props([
    'groups' => [],
])

@php
    $groups = collect($groups)->filter(fn ($group) => $group?->hasOptions())->values();
@endphp

@if ($groups->isNotEmpty())
    <aside
        class="border-outline/70 bg-surface-lowest rounded-lg border p-4"
        aria-label="{{ __('capell-search::generic.filters_label') }}"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($groups as $group)
                <section class="grid gap-2">
                    <h2 class="text-on-surface text-sm font-semibold">
                        {{ $group->label }}
                    </h2>

                    <ul class="flex flex-wrap gap-2">
                        @foreach ($group->options as $option)
                            <li>
                                <a
                                    href="{{ $option->url }}"
                                    class="{{ $option->selected ? 'border-primary bg-primary text-primary-on' : 'border-outline/70 text-on-surface hover:border-primary hover:text-primary' }} inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition"
                                    @if ($option->selected) aria-current="true" @endif
                                >
                                    <span>{{ $option->label }}</span>
                                    <span
                                        class="{{ $option->selected ? 'text-primary-on/80' : 'text-outline-variant' }} text-xs"
                                    >
                                        {{ $option->count }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </aside>
@endif
