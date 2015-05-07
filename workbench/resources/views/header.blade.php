<x-filament-panels::layout.base>
    <header class="mx-auto flex max-w-4xl justify-end p-6">
        <x-capell-search::header.search-modal :show-trigger-label="true" />
    </header>
    <main>
        @include('capell-search::pages.search')
    </main>
</x-filament-panels::layout.base>
