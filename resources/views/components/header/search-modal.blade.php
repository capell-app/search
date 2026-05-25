@php
    use Capell\Frontend\Facades\Frontend;

    $runtimeManifest = Frontend::getFrontendData('runtimeManifest');
    $usesAlpine = $runtimeManifest?->usesAlpine ?? false;
@endphp

@if ($usesAlpine)
    <div
        x-data="{ isSearchOpen: false }"
        x-on:keydown.escape.window="isSearchOpen = false"
        class="site-header-search relative flex items-center"
    >
        <button
            type="button"
            class="hover:text-primary focus:text-primary focus:ring-primary/40 inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-100 transition focus:ring-2 focus:outline-none dark:border-gray-700"
            x-on:click="
                isSearchOpen = true
                $nextTick(() => $refs.searchInput?.focus())
            "
            x-bind:aria-expanded="isSearchOpen.toString()"
            aria-controls="site-header-search-dialog"
        >
            <span class="sr-only">
                {{ __('capell-search::generic.search_label') }}
            </span>
            @svg('heroicon-o-magnifying-glass', 'h-5 w-5')
        </button>

        <div
            x-cloak
            x-show="isSearchOpen"
            x-transition.opacity
            id="site-header-search-dialog"
            class="fixed inset-0 z-[70] flex items-start justify-center bg-black/60 px-4 py-24 backdrop-blur-sm sm:py-32"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('capell-search::generic.search_label') }}"
            x-on:click.self="isSearchOpen = false"
        >
            <div
                x-transition
                class="w-full max-w-xl rounded-lg border border-gray-200 bg-white p-4 shadow-2xl dark:border-gray-700 dark:bg-gray-950"
            >
                <form
                    method="GET"
                    action="{{ route('capell-frontend.search') }}"
                    role="search"
                    class="flex items-center gap-2"
                >
                    <label class="sr-only" for="site-header-search-query">
                        {{ __('capell-search::generic.search_label') }}
                    </label>
                    <input
                        x-ref="searchInput"
                        id="site-header-search-query"
                        type="search"
                        name="q"
                        placeholder="{{ __('capell-search::generic.search_placeholder') }}"
                        class="focus:border-primary focus:ring-primary h-11 min-w-0 flex-1 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:ring-1 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                    <button
                        type="submit"
                        class="bg-primary focus:ring-primary inline-flex h-11 items-center justify-center rounded-md px-4 text-sm font-medium text-white transition hover:opacity-90 focus:ring-2 focus:ring-offset-2 focus:outline-none dark:focus:ring-offset-gray-900"
                    >
                        {{ __('capell-search::button.search') }}
                    </button>
                    <button
                        type="button"
                        class="hover:text-primary focus:text-primary focus:ring-primary/40 inline-flex h-11 w-11 items-center justify-center rounded-md border border-gray-200 focus:ring-2 focus:outline-none dark:border-gray-700"
                        x-on:click="isSearchOpen = false"
                    >
                        <span class="sr-only">
                            {{ __('capell-frontend::generic.close') }}
                        </span>
                        @svg('heroicon-o-x-mark', 'h-5 w-5')
                    </button>
                </form>
            </div>
        </div>
    </div>
@else
    <a
        href="{{ route('capell-frontend.search') }}"
        class="hover:text-primary focus:text-primary focus:ring-primary/40 inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-100 transition focus:ring-2 focus:outline-none dark:border-gray-700"
    >
        <span class="sr-only">
            {{ __('capell-search::generic.search_label') }}
        </span>
        @svg('heroicon-o-magnifying-glass', 'h-5 w-5')
    </a>
@endif
