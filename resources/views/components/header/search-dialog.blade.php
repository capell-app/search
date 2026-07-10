@props ([
    'dialogId' => 'site-header-search-dialog',
])

@php
    $inputId = $dialogId . '-query';
    $resultsId = $dialogId . '-results';
@endphp

<div
    id="{{ $dialogId }}"
    class="fixed inset-0 z-[90] hidden min-h-dvh items-center justify-center bg-black/70 px-4 py-10 backdrop-blur-sm"
    data-site-search-dialog
    data-site-search-autocomplete-url="{{ route('capell-frontend.search.autocomplete', absolute: false) }}"
    data-site-search-click-url="{{ route('capell-frontend.search.click', absolute: false) }}"
    data-site-search-minimum-length="{{ (int) config('capell-search.minimum_query_length', 2) }}"
    data-site-search-debounce-ms="{{ max(0, (int) config('capell-search.autocomplete.debounce_ms', 150)) }}"
    data-site-search-keyboard-shortcuts="{{ (bool) config('capell-search.keyboard_shortcuts.enabled', true) ? 'true' : 'false' }}"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-label="{{ __('capell-search::generic.search_label') }}"
>
    <button
        type="button"
        class="absolute inset-0 cursor-default"
        data-site-search-backdrop
        aria-label="{{ __('capell-frontend::generic.close') }}"
        tabindex="-1"
    ></button>

    <div
        class="border-outline/70 bg-surface-lowest text-on-surface relative w-full max-w-2xl rounded-lg border p-5 shadow-2xl shadow-black/35"
    >
        <div
            class="bg-surface-lowest absolute end-3 top-3 z-10 flex items-center gap-2 ps-2"
        >
            <kbd
                class="border-outline/70 text-on-surface-variant hidden rounded border px-1.5 py-0.5 text-[11px] leading-none font-medium sm:inline-block"
                aria-hidden="true"
            >
                {{ __('capell-search::generic.shortcut_escape') }}
            </kbd>
            <button
                type="button"
                class="border-outline/70 text-on-surface-variant hover:border-primary hover:text-primary focus-visible:outline-primary inline-flex size-9 shrink-0 items-center justify-center rounded-md border transition focus-visible:outline-2 focus-visible:outline-offset-2"
                data-site-search-close
            >
                <span class="sr-only">
                    {{ __('capell-frontend::generic.close') }}
                </span>
                @svg ('heroicon-o-x-mark', 'h-5 w-5')
            </button>
        </div>

        <form
            method="GET"
            action="{{ route('capell-frontend.search', absolute: false) }}"
            role="search"
            class="grid gap-3 pe-16"
            data-site-search-form
        >
            <label
                class="text-on-surface text-sm font-semibold"
                for="{{ $inputId }}"
            >
                {{ __('capell-search::generic.search_label') }}
            </label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    id="{{ $inputId }}"
                    type="search"
                    name="q"
                    placeholder="{{ __('capell-search::generic.search_placeholder') }}"
                    class="border-outline/70 bg-surface text-on-surface placeholder:text-outline-variant focus:border-primary focus:outline-primary h-12 min-w-0 flex-1 rounded-md border px-4 text-base transition focus:outline-2 focus:outline-offset-2"
                    data-site-search-input
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="{{ $resultsId }}"
                    aria-expanded="false"
                    autocomplete="off"
                />
                <button
                    type="submit"
                    class="bg-primary text-primary-on hover:bg-primary-container focus-visible:outline-primary inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2"
                    aria-label="{{ __('capell-search::button.search') }}"
                >
                    @svg ('heroicon-o-magnifying-glass', 'h-5 w-5')
                </button>
            </div>
        </form>

        <x-capell-search::header.autocomplete-results
            :results-id="$resultsId"
        />

        <div
            class="text-outline-variant mt-3 hidden flex-wrap items-center gap-x-4 gap-y-1 text-xs sm:flex"
            aria-hidden="true"
        >
            <span class="inline-flex items-center gap-1.5">
                <kbd
                    class="border-outline/70 rounded border px-1.5 py-0.5 leading-none font-medium"
                >
                    &uarr;
                </kbd>
                <kbd
                    class="border-outline/70 rounded border px-1.5 py-0.5 leading-none font-medium"
                >
                    &darr;
                </kbd>
                {{ __('capell-search::generic.hint_navigate') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <kbd
                    class="border-outline/70 rounded border px-1.5 py-0.5 leading-none font-medium"
                >
                    &crarr;
                </kbd>
                {{ __('capell-search::generic.hint_open') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <kbd
                    class="border-outline/70 rounded border px-1.5 py-0.5 leading-none font-medium"
                >
                    {{ __('capell-search::generic.shortcut_escape') }}
                </kbd>
                {{ __('capell-search::generic.hint_close') }}
            </span>
        </div>
    </div>
</div>

@once
    <style>
        /* Hide the browser-native clear/decoration controls so the only
           close affordance is the explicit button in the panel corner. */
        [data-site-search-input]::-webkit-search-cancel-button,
        [data-site-search-input]::-webkit-search-decoration {
            -webkit-appearance: none;
            appearance: none;
        }

        [data-site-search-input]::-ms-clear,
        [data-site-search-input]::-ms-reveal {
            display: none;
            width: 0;
            height: 0;
        }
    </style>
    {{--
        The search modal behaviour lives in a package-owned, versioned asset
        (resources/dist/search-modal.js, published to public/vendor/capell-search)
        so ~34KB of JavaScript is cached once instead of inlined on every page.
        The script reads all its config from the dialog's data-* attributes, so
        it needs no server-side interpolation. The filemtime query busts the
        cache whenever the published asset changes.
    --}}
    @php
        $searchModalAsset = public_path('vendor/capell-search/search-modal.js');
        $searchModalVersion = is_file($searchModalAsset) ? filemtime($searchModalAsset) : null;
    @endphp

    <script
        defer
        src="{{ asset('vendor/capell-search/search-modal.js') }}{{ $searchModalVersion ? '?v=' . $searchModalVersion : '' }}"
    ></script>
@endonce
