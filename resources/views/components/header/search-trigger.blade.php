@props ([
    'dialogId' => 'site-header-search-dialog',
    'label' => __('capell-search::generic.search_label'),
    'showLabel' => false,
])

<button
    type="button"
    {{
        $attributes->class([
            'hover:text-primary focus:text-primary focus:ring-primary/40 inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-100 transition focus:ring-2 focus:outline-none dark:border-gray-700' => ! $showLabel,
            'border-outline text-on-surface-variant hover:border-primary hover:text-primary focus-visible:outline-primary inline-flex h-10 items-center justify-center gap-2 border px-4 text-sm transition focus-visible:outline focus-visible:outline-2' => $showLabel,
        ])
    }}
    data-site-search-trigger
    data-site-search-target="{{ $dialogId }}"
    aria-controls="{{ $dialogId }}"
    aria-expanded="false"
    aria-haspopup="dialog"
>
    @svg ('heroicon-o-magnifying-glass', 'h-5 w-5')
    <span @class (['sr-only' => ! $showLabel])> {{ $label }} </span>
    @if ($showLabel && (bool) config('capell-search.keyboard_shortcuts.enabled', true))
        <kbd
            class="border-outline/70 text-on-surface-variant ms-1 hidden rounded border px-1.5 py-0.5 text-[11px] leading-none font-medium sm:inline-block"
            aria-hidden="true"
        >
            {{ str_contains(strtolower(request()->userAgent() ?? ''), 'mac') ? '⌘K' : 'Ctrl K' }}
        </kbd>
    @endif
</button>
