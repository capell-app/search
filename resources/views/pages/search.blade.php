<section
    class="search-page mx-auto w-full max-w-4xl space-y-8 px-4 py-10 sm:px-6 lg:px-8"
>
    <header class="grid gap-3">
        <p class="text-sm font-medium text-gray-500 uppercase">
            {{ __('capell-search::generic.search_label') }}
        </p>
        <h1 class="text-3xl font-semibold text-gray-950 dark:text-white">
            {{ __('capell-search::generic.page_title') }}
        </h1>
    </header>

    <x-capell-search::form :query="$query" />

    <x-capell-search::results
        :query="$query"
        :results="$results"
    />
</section>
