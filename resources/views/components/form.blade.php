@props([
    'query' => '',
])

<form
    method="GET"
    action="{{ route('capell-frontend.search') }}"
    role="search"
    class="site-search-form grid gap-3"
>
    <label
        class="text-on-surface text-sm font-semibold"
        for="site-search-query"
    >
        {{ __('capell-search::generic.search_label') }}
    </label>
    <div class="flex flex-col gap-3 sm:flex-row">
        <input
            id="site-search-query"
            type="search"
            name="q"
            value="{{ $query }}"
            placeholder="{{ __('capell-search::generic.search_placeholder') }}"
            class="border-outline/70 bg-surface text-on-surface placeholder:text-outline-variant focus:border-primary focus:outline-primary h-12 min-w-0 flex-1 rounded-md border px-4 text-base transition focus:outline-2 focus:outline-offset-2"
        />
        <button
            type="submit"
            class="bg-primary text-primary-on hover:bg-primary-container focus-visible:outline-primary inline-flex h-12 shrink-0 items-center justify-center rounded-md px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2"
        >
            {{ __('capell-search::button.search') }}
        </button>
    </div>
</form>
