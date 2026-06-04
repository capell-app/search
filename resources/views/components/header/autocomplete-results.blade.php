@props([
    'resultsId',
])

<div
    class="border-outline/70 bg-surface-lowest mt-3 overflow-hidden rounded-md border shadow-lg"
    data-site-search-results
    data-site-search-suggestions-template="{{ __('capell-search::generic.suggestions_available', ['count' => '__count__']) }}"
    hidden
>
    <p
        class="sr-only"
        data-site-search-status
        aria-live="polite"
    ></p>

    <ol
        id="{{ $resultsId }}"
        class="max-h-80 overflow-y-auto"
        data-site-search-list
        role="listbox"
        hidden
    ></ol>

    <a
        href="{{ route('capell-frontend.search') }}"
        class="border-outline/50 text-on-surface hover:bg-surface-low border-t px-3 py-2 text-sm font-medium transition"
        data-site-search-all-results
        hidden
    >
        {{ __('capell-search::generic.view_all_results') }}
    </a>
</div>
