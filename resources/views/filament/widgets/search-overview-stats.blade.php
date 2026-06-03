<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('capell-search::dashboard.search_overview') }}
        </x-slot>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('capell-search::dashboard.searches') }}
                </div>
                <div class="text-2xl font-semibold">
                    {{ number_format($totalSearches) }}
                </div>
            </div>

            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('capell-search::dashboard.query') }}
                </div>
                <div class="text-2xl font-semibold">
                    {{ number_format($uniqueQueries) }}
                </div>
            </div>

            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('capell-search::dashboard.results') }}
                </div>
                <div class="text-2xl font-semibold">
                    {{ number_format($totalResults) }}
                </div>
            </div>

            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('capell-search::dashboard.zero_result_rate') }}
                </div>
                <div class="text-2xl font-semibold">
                    {{ number_format($zeroResultRate, 1) }}%
                </div>
            </div>

            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('capell-search::dashboard.click_through_rate') }}
                </div>
                <div class="text-2xl font-semibold">
                    {{ number_format($clickThroughRate, 1) }}%
                </div>
            </div>
        </div>

        @if ($topClickedResults->isNotEmpty())
            <div class="mt-6 grid gap-2">
                <div
                    class="text-sm font-medium text-gray-700 dark:text-gray-200"
                >
                    {{ __('capell-search::dashboard.top_clicked_results') }}
                </div>
                <ol class="grid gap-2 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ($topClickedResults as $result)
                        <li class="flex items-center justify-between gap-4">
                            <span class="truncate">{{ $result['url'] }}</span>
                            <span class="font-medium">
                                {{ number_format($result['clicks']) }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
