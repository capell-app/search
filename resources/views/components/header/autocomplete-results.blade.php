@props([
    'resultsId',
])

<div
    class="mt-3 overflow-hidden border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-950"
    data-site-search-results
    data-site-search-suggestions-template="{{ __('capell-search::generic.suggestions_available', ['count' => '__count__']) }}"
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
        class="border-t border-gray-100 px-3 py-2 text-sm font-medium text-gray-950 transition hover:bg-gray-50 dark:border-white/10 dark:text-white dark:hover:bg-white/5"
        data-site-search-all-results
        hidden
    >
        {{ __('capell-search::generic.view_all_results') }}
    </a>
</div>
