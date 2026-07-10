@props ([
    'resultsId',
])

<div
    class="border-outline/70 bg-surface-lowest mt-3 overflow-hidden rounded-md border shadow-lg"
    data-site-search-results
    data-site-search-corrected-label="{{ __('capell-search::generic.did_you_mean') }}"
    data-site-search-query-label="{{ __('capell-search::generic.popular_search') }}"
    data-site-search-suggestions-template="{{ __('capell-search::generic.suggestions_available', ['count' => '__count__']) }}"
    hidden
>
    <p
        class="sr-only"
        data-site-search-status
        aria-live="polite"
    ></p>

    <div
        class="flex items-center gap-3 p-4"
        data-site-search-idle
    >
        <span
            class="border-outline bg-surface text-on-surface-variant inline-grid size-8 shrink-0 place-items-center rounded-md border"
            aria-hidden="true"
        >
            @svg ('heroicon-o-magnifying-glass', 'h-4 w-4')
        </span>
        <div class="min-w-0">
            <p class="text-on-surface text-sm font-medium">
                {{ __('capell-search::generic.empty_query') }}
            </p>
            <p class="text-on-surface-variant text-sm">
                {{ __('capell-search::generic.idle_hint') }}
            </p>
        </div>
    </div>

    <div
        class="grid gap-3 p-3"
        data-site-search-loading
        data-site-search-loading-template="{{ __('capell-search::generic.searching_for') }}"
        data-site-search-loading-status="{{ __('capell-search::generic.searching') }}"
        hidden
    >
        <div class="flex items-center gap-3">
            <span
                class="border-outline bg-surface inline-grid size-8 shrink-0 place-items-center rounded-md border"
                aria-hidden="true"
            >
                @svg ('heroicon-o-arrow-path', 'text-primary h-4 w-4 animate-spin')
            </span>
            <div class="min-w-0">
                <p
                    class="text-on-surface text-sm font-medium"
                    data-site-search-loading-label
                >
                    {{ __('capell-search::generic.searching') }}
                </p>
                <p class="text-on-surface-variant text-sm">
                    {{ __('capell-search::generic.searching_hint') }}
                </p>
            </div>
        </div>
        <div
            class="grid gap-2"
            aria-hidden="true"
        >
            <span
                class="bg-surface h-3 w-11/12 animate-pulse rounded-sm"
            ></span>
            <span class="bg-surface h-3 w-7/12 animate-pulse rounded-sm"></span>
        </div>
    </div>

    <ol
        id="{{ $resultsId }}"
        class="max-h-80 overflow-y-auto"
        data-site-search-list
        role="listbox"
        hidden
    ></ol>

    <div
        class="flex items-center gap-3 p-4"
        data-site-search-empty
        data-site-search-empty-template="{{ __('capell-search::generic.no_results', ['query' => '__query__']) }}"
        hidden
    >
        <span
            class="border-outline bg-surface text-on-surface-variant inline-grid size-8 shrink-0 place-items-center rounded-md border"
            aria-hidden="true"
        >
            @svg ('heroicon-o-face-frown', 'h-4 w-4')
        </span>
        <p
            class="text-on-surface-variant text-sm"
            data-site-search-empty-label
        >
            {{ __('capell-search::generic.no_results', ['query' => '']) }}
        </p>
    </div>

    <a
        href="{{ route('capell-frontend.search', absolute: false) }}"
        class="border-outline/50 text-on-surface hover:bg-surface-low border-t px-3 py-2 text-sm font-medium transition"
        data-site-search-all-results
        hidden
    >
        {{ __('capell-search::generic.view_all_results') }}
    </a>
</div>
