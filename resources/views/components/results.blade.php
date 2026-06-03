@props([
    'results',
    'query' => '',
])

@php
    use Capell\Search\Contracts\Search;

    /** @var Search $search */
    $search = app(Search::class);
@endphp

<section
    class="site-search-results"
    aria-label="{{ __('capell-search::generic.results_label') }}"
    data-site-search-click-url="{{ route('capell-frontend.search.click') }}"
>
    @if ($query === '')
        <p class="text-gray-600">
            {{ __('capell-search::generic.empty_query') }}
        </p>
    @elseif ($results->isEmpty())
        <p class="text-gray-600">
            {{ __('capell-search::generic.no_results', ['query' => $query]) }}
        </p>
    @else
        <p class="mb-4 text-sm text-gray-500">
            {{
                trans_choice('capell-search::generic.results_count', $results->total(), [
                    'count' => $results->total(),
                    'query' => $query,
                ])
            }}
        </p>
        <ol
            class="space-y-4"
            role="list"
        >
            @foreach ($results as $result)
                <li
                    class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-950"
                >
                    <h2 class="text-lg font-semibold">
                        <a
                            href="{{ $result->url }}"
                            class="hover:underline"
                            data-site-search-click
                            data-site-search-query="{{ $query }}"
                            data-site-search-type="{{ $result->type }}"
                            data-site-search-position="{{ $loop->iteration }}"
                            data-site-search-surface="results"
                        >
                            {!! $search->highlight($result->title, $query) !!}
                        </a>
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {!! $search->highlight($result->excerpt, $query) !!}
                    </p>
                    <p class="mt-2 text-xs text-gray-400 uppercase">
                        {{ $result->typeLabel ?? $result->type }}
                    </p>
                </li>
            @endforeach
        </ol>
        <div class="mt-6">
            {{ $results->links() }}
        </div>
    @endif
</section>
