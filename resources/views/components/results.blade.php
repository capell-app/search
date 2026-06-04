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
        <p class="text-on-surface-variant">
            {{ __('capell-search::generic.empty_query') }}
        </p>
    @elseif ($results->isEmpty())
        <p class="text-on-surface-variant">
            {{ __('capell-search::generic.no_results', ['query' => $query]) }}
        </p>
    @else
        <p class="text-on-surface-variant mb-4 text-sm">
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
                    class="border-outline/70 bg-surface-lowest rounded-lg border p-4 shadow-sm"
                >
                    <h2 class="text-lg font-semibold">
                        <a
                            href="{{ $result->url }}"
                            class="text-on-surface hover:text-primary hover:underline"
                            data-site-search-click
                            data-site-search-query="{{ $query }}"
                            data-site-search-type="{{ $result->type }}"
                            data-site-search-position="{{ $loop->iteration }}"
                            data-site-search-surface="results"
                        >
                            {!! $search->highlight($result->title, $query) !!}
                        </a>
                    </h2>
                    <p class="text-on-surface-variant mt-1 text-sm">
                        {!! $search->highlight($result->excerpt, $query) !!}
                    </p>
                    <p class="text-outline-variant mt-2 text-xs uppercase">
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
