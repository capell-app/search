@props([
    'query' => '',
])

<form
    method="GET"
    action="{{ route('capell-frontend.search') }}"
    role="search"
    class="site-search-form flex flex-col gap-2 sm:flex-row sm:items-center"
>
    <label
        class="sr-only"
        for="site-search-query"
    >
        {{ __('capell-search::generic.search_label') }}
    </label>
    <input
        id="site-search-query"
        type="search"
        name="q"
        value="{{ $query }}"
        placeholder="{{ __('capell-search::generic.search_placeholder') }}"
        class="focus:border-primary focus:ring-primary h-11 min-w-0 flex-1 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:ring-1 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
    />
    <button
        type="submit"
        class="bg-primary focus:ring-primary inline-flex h-11 items-center justify-center rounded-md px-4 text-sm font-medium text-white transition hover:opacity-90 focus:ring-2 focus:ring-offset-2 focus:outline-none dark:focus:ring-offset-gray-900"
    >
        {{ __('capell-search::button.search') }}
    </button>
</form>
