@php
    use Capell\Frontend\Facades\Frontend;

    $runtimeManifest = Frontend::getFrontendData('runtimeManifest');
    $usesAlpine = $runtimeManifest?->usesAlpine ?? false;
@endphp

@if ($usesAlpine)
    <div
        x-data="{
            isSearchOpen: false,
            setPageInert(value) {
                document.body.classList.toggle('overflow-hidden', value)

                document.querySelectorAll('main, footer').forEach((element) => {
                    element.toggleAttribute('inert', value)

                    if (value) {
                        element.setAttribute('aria-hidden', 'true')
                        return
                    }

                    element.removeAttribute('aria-hidden')
                })
            },
        }"
        x-effect="setPageInert(isSearchOpen)"
        x-on:keydown.escape.window="isSearchOpen = false"
        class="site-header-search relative flex items-center justify-end lg:ml-2"
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
            class="fixed inset-0 z-[90] flex min-h-dvh items-center justify-center bg-black/70 px-4 py-10 backdrop-blur-sm"
            style="
                align-items: center;
                background-color: rgba(0, 0, 0, 0.7);
                justify-content: center;
                min-height: 100dvh;
                z-index: 90;
            "
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('capell-search::generic.search_label') }}"
            x-on:click.self="isSearchOpen = false"
        >
            <div
                x-transition
                class="w-full max-w-2xl rounded-lg border border-gray-200 bg-white p-4 shadow-2xl dark:border-white/10 dark:bg-gray-950"
            >
                <form
                    method="GET"
                    action="{{ route('capell-frontend.search') }}"
                    role="search"
                    class="flex items-center gap-2"
                >
                    <label
                        class="sr-only"
                        for="site-header-search-query"
                    >
                        {{ __('capell-search::generic.search_label') }}
                    </label>
                    <input
                        x-ref="searchInput"
                        id="site-header-search-query"
                        type="search"
                        name="q"
                        placeholder="{{ __('capell-search::generic.search_placeholder') }}"
                        class="focus:border-primary focus:ring-primary h-12 min-w-0 flex-1 rounded-md border border-gray-200 bg-white px-4 text-base text-gray-900 shadow-sm placeholder:text-gray-400 focus:ring-1 focus:outline-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
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
