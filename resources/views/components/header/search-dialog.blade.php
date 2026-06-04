@props([
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
    data-site-search-autocomplete-url="{{ route('capell-frontend.search.autocomplete') }}"
    data-site-search-click-url="{{ route('capell-frontend.search.click') }}"
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
        <form
            method="GET"
            action="{{ route('capell-frontend.search') }}"
            role="search"
            class="grid gap-3"
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
                    class="bg-primary text-primary-on hover:bg-primary-container focus-visible:outline-primary inline-flex h-12 shrink-0 items-center justify-center rounded-md px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2"
                >
                    {{ __('capell-search::button.search') }}
                </button>
                <button
                    type="button"
                    class="border-outline/70 text-on-surface-variant hover:border-primary hover:text-primary focus-visible:outline-primary inline-flex h-12 w-full shrink-0 items-center justify-center rounded-md border transition focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-12"
                    data-site-search-close
                >
                    <span class="sr-only">
                        {{ __('capell-frontend::generic.close') }}
                    </span>
                    @svg('heroicon-o-x-mark', 'h-5 w-5')
                </button>
            </div>
        </form>

        <x-capell-search::header.autocomplete-results
            :results-id="$resultsId"
        />
    </div>
</div>

@once
    <script>
        ;(() => {
            if (window.siteSearchModalInitialized) {
                return
            }

            window.siteSearchModalInitialized = true

            const selectors = {
                trigger: '[data-site-search-trigger]',
                dialog: '[data-site-search-dialog]',
                input: '[data-site-search-input]',
                list: '[data-site-search-list]',
                status: '[data-site-search-status]',
                allResults: '[data-site-search-all-results]',
            }
            const focusableSelector = [
                'a[href]',
                'button:not([disabled])',
                'input:not([disabled])',
                'select:not([disabled])',
                'textarea:not([disabled])',
                '[tabindex]:not([tabindex="-1"])',
            ].join(',')

            const stateByDialog = new WeakMap()

            const dialogForTrigger = (trigger) => {
                const target =
                    trigger.getAttribute('data-site-search-target') ||
                    trigger.getAttribute('aria-controls')

                return target ? document.getElementById(target) : null
            }

            const firstDialog = () => document.querySelector(selectors.dialog)

            const isTypingTarget = (target) =>
                target instanceof HTMLElement &&
                (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) ||
                    target.isContentEditable)

            const stateFor = (dialog) => {
                if (!stateByDialog.has(dialog)) {
                    stateByDialog.set(dialog, {
                        activeIndex: -1,
                        abortController: null,
                        debounceTimer: null,
                        results: [],
                        trigger: null,
                    })
                }

                return stateByDialog.get(dialog)
            }

            const pageElements = () => [
                ...document.querySelectorAll('main, footer'),
            ]

            const setPageInert = (value) => {
                pageElements().forEach((element) => {
                    if (value) {
                        if (!element.hasAttribute('inert')) {
                            element.setAttribute(
                                'data-site-search-managed-inert',
                                '',
                            )
                            element.setAttribute('inert', '')
                        }

                        if (!element.hasAttribute('aria-hidden')) {
                            element.setAttribute(
                                'data-site-search-managed-hidden',
                                '',
                            )
                            element.setAttribute('aria-hidden', 'true')
                        }

                        return
                    }

                    if (
                        element.hasAttribute('data-site-search-managed-inert')
                    ) {
                        element.removeAttribute(
                            'data-site-search-managed-inert',
                        )
                        element.removeAttribute('inert')
                    }

                    if (
                        element.hasAttribute('data-site-search-managed-hidden')
                    ) {
                        element.removeAttribute(
                            'data-site-search-managed-hidden',
                        )
                        element.removeAttribute('aria-hidden')
                    }
                })
            }

            const focusableElements = (dialog) =>
                [...dialog.querySelectorAll(focusableSelector)].filter(
                    (element) =>
                        element instanceof HTMLElement &&
                        !element.hidden &&
                        element.offsetParent !== null &&
                        element.getAttribute('aria-hidden') !== 'true',
                )

            const trapFocus = (event, dialog) => {
                const elements = focusableElements(dialog)
                const first = elements[0]
                const last = elements[elements.length - 1]

                if (!first || !last) {
                    return
                }

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault()
                    last.focus()
                    return
                }

                if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault()
                    first.focus()
                }
            }

            const cancelPendingSearch = (dialog) => {
                const state = stateFor(dialog)

                window.clearTimeout(state.debounceTimer)
                state.abortController?.abort()
                state.abortController = null
            }

            const closeDialog = (dialog) => {
                const state = stateFor(dialog)

                cancelPendingSearch(dialog)
                dialog.classList.add('hidden')
                dialog.classList.remove('flex')
                dialog.setAttribute('aria-hidden', 'true')
                document.body.classList.remove('overflow-hidden')
                setPageInert(false)

                document
                    .querySelectorAll(
                        `${selectors.trigger}[aria-controls="${dialog.id}"]`,
                    )
                    .forEach((trigger) => {
                        trigger.setAttribute('aria-expanded', 'false')
                    })

                state.trigger?.focus()
            }

            const openDialog = (dialog, trigger) => {
                const state = stateFor(dialog)
                const input = dialog.querySelector(selectors.input)

                state.trigger = trigger
                dialog.classList.remove('hidden')
                dialog.classList.add('flex')
                dialog.setAttribute('aria-hidden', 'false')
                trigger.setAttribute('aria-expanded', 'true')
                document.body.classList.add('overflow-hidden')
                setPageInert(true)

                window.requestAnimationFrame(() => input?.focus())
            }

            const clearResults = (dialog) => {
                const input = dialog.querySelector(selectors.input)
                const list = dialog.querySelector(selectors.list)
                const status = dialog.querySelector(selectors.status)
                const allResults = dialog.querySelector(selectors.allResults)
                const resultsRegion = dialog.querySelector(
                    '[data-site-search-results]',
                )
                const state = stateFor(dialog)

                window.clearTimeout(state.debounceTimer)
                state.activeIndex = -1
                state.results = []
                list.replaceChildren()
                list.hidden = true
                allResults.hidden = true
                if (resultsRegion) {
                    resultsRegion.hidden = true
                }
                input.setAttribute('aria-expanded', 'false')
                input.removeAttribute('aria-activedescendant')
                status.textContent = ''
            }

            const setActiveResult = (dialog, index) => {
                const input = dialog.querySelector(selectors.input)
                const items = [
                    ...dialog.querySelectorAll('[data-site-search-result]'),
                ]
                const state = stateFor(dialog)

                state.activeIndex = index

                items.forEach((item, itemIndex) => {
                    const isActive = itemIndex === index

                    item.toggleAttribute('data-active', isActive)
                    item.classList.toggle('bg-surface-low', isActive)
                })

                if (items[index]) {
                    input.setAttribute('aria-activedescendant', items[index].id)
                } else {
                    input.removeAttribute('aria-activedescendant')
                }
            }

            const resultItem = (dialog, result, index, listId) => {
                const item = document.createElement('li')
                const link = document.createElement('a')
                const title = document.createElement('span')
                const excerpt = document.createElement('span')
                const type = document.createElement('span')

                item.id = `${listId}-option-${index}`
                item.setAttribute('role', 'option')
                item.setAttribute('data-site-search-result', '')
                item.className = 'border-outline/50 border-b last:border-b-0'

                link.href = result.url
                link.setAttribute('data-site-search-click', '')
                link.setAttribute(
                    'data-site-search-query',
                    dialog.querySelector(selectors.input)?.value || '',
                )
                link.setAttribute('data-site-search-type', result.type || '')
                link.setAttribute(
                    'data-site-search-position',
                    String(index + 1),
                )
                link.setAttribute('data-site-search-surface', 'autocomplete')
                link.className =
                    'hover:bg-surface-low grid gap-1 p-3 text-left transition'

                title.className = 'text-on-surface text-sm font-medium'
                title.textContent = result.title

                excerpt.className =
                    'text-on-surface-variant line-clamp-2 text-sm'
                excerpt.textContent = result.excerpt

                type.className =
                    'text-outline-variant text-xs font-medium uppercase tracking-wide'
                type.textContent = result.typeLabel || result.type

                link.append(title, excerpt, type)
                item.append(link)

                return item
            }

            const renderResults = (dialog, payload) => {
                const input = dialog.querySelector(selectors.input)
                const list = dialog.querySelector(selectors.list)
                const status = dialog.querySelector(selectors.status)
                const allResults = dialog.querySelector(selectors.allResults)
                const resultsRegion = dialog.querySelector(
                    '[data-site-search-results]',
                )
                const results = Array.isArray(payload.results)
                    ? payload.results
                    : []
                const state = stateFor(dialog)
                const suggestionsTemplate =
                    resultsRegion?.getAttribute(
                        'data-site-search-suggestions-template',
                    ) || '__count__ suggestions available.'

                state.results = results
                list.replaceChildren(
                    ...results.map((result, index) =>
                        resultItem(dialog, result, index, list.id),
                    ),
                )
                list.hidden = results.length === 0
                allResults.hidden = results.length === 0
                if (resultsRegion) {
                    resultsRegion.hidden = results.length === 0
                }
                allResults.href =
                    payload.allResultsUrl || input.form?.action || '/search'
                input.setAttribute(
                    'aria-expanded',
                    results.length > 0 ? 'true' : 'false',
                )
                status.textContent =
                    results.length > 0
                        ? suggestionsTemplate.replace(
                              '__count__',
                              results.length,
                          )
                        : ''
                setActiveResult(dialog, -1)
            }

            const fetchResults = async (dialog) => {
                const input = dialog.querySelector(selectors.input)
                const url = dialog.getAttribute(
                    'data-site-search-autocomplete-url',
                )
                const minimumLength = Number.parseInt(
                    dialog.getAttribute('data-site-search-minimum-length') ||
                        '2',
                    10,
                )
                const query = input.value.trim()
                const state = stateFor(dialog)

                if (query.length < minimumLength || !url) {
                    clearResults(dialog)
                    return
                }

                state.abortController?.abort()
                state.abortController = new AbortController()

                const endpoint = new URL(url, window.location.origin)
                endpoint.searchParams.set('q', query)

                try {
                    const response = await fetch(endpoint, {
                        headers: { Accept: 'application/json' },
                        signal: state.abortController.signal,
                    })

                    if (!response.ok) {
                        clearResults(dialog)
                        return
                    }

                    renderResults(dialog, await response.json())
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        clearResults(dialog)
                    }
                }
            }

            const scheduleFetchResults = (dialog) => {
                const state = stateFor(dialog)
                const debounceMs = Number.parseInt(
                    dialog.getAttribute('data-site-search-debounce-ms') ||
                        '150',
                    10,
                )

                window.clearTimeout(state.debounceTimer)
                state.debounceTimer = window.setTimeout(
                    () => fetchResults(dialog),
                    Math.max(0, debounceMs),
                )
            }

            document.addEventListener('click', (event) => {
                const trackedLink = event.target.closest(
                    '[data-site-search-click]',
                )

                if (trackedLink) {
                    const trackingContainer = trackedLink.closest(
                        '[data-site-search-click-url]',
                    )
                    const token = document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content')
                    const url = trackingContainer?.getAttribute(
                        'data-site-search-click-url',
                    )

                    if (token && url) {
                        const body = new FormData()
                        body.set(
                            'query',
                            trackedLink.getAttribute(
                                'data-site-search-query',
                            ) || '',
                        )
                        body.set('url', trackedLink.href)
                        body.set(
                            'type',
                            trackedLink.getAttribute('data-site-search-type') ||
                                '',
                        )
                        body.set(
                            'position',
                            trackedLink.getAttribute(
                                'data-site-search-position',
                            ) || '',
                        )
                        body.set(
                            'surface',
                            trackedLink.getAttribute(
                                'data-site-search-surface',
                            ) || '',
                        )

                        fetch(url, {
                            method: 'POST',
                            body,
                            headers: { 'X-CSRF-TOKEN': token },
                            keepalive: true,
                        }).catch(() => {})
                    }
                }

                const trigger = event.target.closest(selectors.trigger)

                if (trigger) {
                    const dialog = dialogForTrigger(trigger)

                    if (dialog) {
                        event.preventDefault()
                        openDialog(dialog, trigger)
                    }

                    return
                }

                const closeControl = event.target.closest(
                    '[data-site-search-close], [data-site-search-backdrop]',
                )

                if (closeControl) {
                    const dialog = closeControl.closest(selectors.dialog)

                    if (dialog) {
                        closeDialog(dialog)
                    }
                }
            })

            document.addEventListener('input', (event) => {
                const input = event.target.closest(selectors.input)

                if (input) {
                    scheduleFetchResults(input.closest(selectors.dialog))
                }
            })

            document.addEventListener('keydown', (event) => {
                const dialog = event.target.closest(selectors.dialog)

                if (!dialog) {
                    if (
                        (event.key === '/' ||
                            (event.key.toLowerCase() === 'k' &&
                                (event.metaKey || event.ctrlKey))) &&
                        !isTypingTarget(event.target)
                    ) {
                        const targetDialog = firstDialog()

                        if (
                            targetDialog?.getAttribute(
                                'data-site-search-keyboard-shortcuts',
                            ) === 'true'
                        ) {
                            event.preventDefault()
                            openDialog(targetDialog, document.body)
                        }

                        return
                    }

                    if (event.key === 'Escape') {
                        document
                            .querySelectorAll(
                                `${selectors.dialog}:not(.hidden)`,
                            )
                            .forEach(closeDialog)
                    }

                    return
                }

                const state = stateFor(dialog)

                if (event.key === 'Escape') {
                    event.preventDefault()
                    closeDialog(dialog)
                    return
                }

                if (event.key === 'Tab') {
                    trapFocus(event, dialog)
                    return
                }

                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    if (state.results.length === 0) {
                        return
                    }

                    event.preventDefault()

                    const direction = event.key === 'ArrowDown' ? 1 : -1
                    const nextIndex =
                        (state.activeIndex + direction + state.results.length) %
                        state.results.length

                    setActiveResult(dialog, nextIndex)
                    return
                }

                if (event.key === 'Enter' && state.activeIndex >= 0) {
                    const item = dialog.querySelectorAll(
                        '[data-site-search-result]',
                    )[state.activeIndex]
                    const link = item?.querySelector('a')

                    if (link) {
                        event.preventDefault()
                        window.location.assign(link.href)
                    }
                }
            })
        })()
    </script>
@endonce
