@props([
    'highlightedResults' => null,
    'results',
    'query' => '',
])

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
                @php
                    $highlightedResult = $highlightedResults?->get($loop->index);
                @endphp

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
                            {!! $highlightedResult['title'] ?? e($result->title) !!}
                        </a>
                    </h2>
                    <p class="text-on-surface-variant mt-1 text-sm">
                        {!! $highlightedResult['excerpt'] ?? e($result->excerpt) !!}
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

@once
    <script>
        ;(() => {
            if (window.capellSearchClickBeaconInitialized) {
                return
            }

            window.capellSearchClickBeaconInitialized = true

            document.addEventListener('click', (event) => {
                const trackedLink = event.target.closest(
                    '[data-site-search-click]',
                )

                if (!trackedLink) {
                    return
                }

                const trackingContainer = trackedLink.closest(
                    '[data-site-search-click-url]',
                )
                const url = trackingContainer?.getAttribute(
                    'data-site-search-click-url',
                )

                if (!url) {
                    return
                }

                const body = new FormData()
                body.set(
                    'query',
                    trackedLink.getAttribute('data-site-search-query') || '',
                )
                body.set('url', trackedLink.href)
                body.set(
                    'type',
                    trackedLink.getAttribute('data-site-search-type') || '',
                )
                body.set(
                    'position',
                    trackedLink.getAttribute('data-site-search-position') || '',
                )
                body.set(
                    'surface',
                    trackedLink.getAttribute('data-site-search-surface') || '',
                )

                fetch(url, {
                    method: 'POST',
                    body,
                    mode: 'no-cors',
                    keepalive: true,
                }).catch(() => {})
            })
        })()
    </script>
@endonce
