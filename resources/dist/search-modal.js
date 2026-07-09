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
        loading: '[data-site-search-loading]',
        status: '[data-site-search-status]',
        allResults: '[data-site-search-all-results]',
        region: '[data-site-search-results]',
        idle: '[data-site-search-idle]',
        empty: '[data-site-search-empty]',
    }

    // Order result groups consistently and map each result type to a
    // leading heroicon. Unknown types fall back to a document icon and
    // are appended after the known groups.
    const groupOrder = [
        'page',
        'marketing_content',
        'marketing_section',
        'marketing_widget',
        'extension',
        'whats_new',
        'advisory',
        'roadmap',
        'showcase',
    ]

    const iconPaths = {
        document:
            'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        extension:
            'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.959.401v0a.656.656 0 0 0 .658-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z',
        whats_new:
            'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z',
        advisory:
            'M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Zm0 13.036h.008v.008H12v-.008Z',
        roadmap:
            'M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z',
        showcase:
            'm2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
    }

    const typeToIconKey = {
        page: 'document',
        marketing_content: 'document',
        marketing_section: 'document',
        marketing_widget: 'document',
        extension: 'extension',
        whats_new: 'whats_new',
        advisory: 'advisory',
        roadmap: 'roadmap',
        showcase: 'showcase',
    }

    const typeIcon = (type) => {
        const key = typeToIconKey[type] || 'document'
        const path = iconPaths[key] || iconPaths.document

        return (
            '<svg xmlns="http://www.w3.org/2000/svg" fill="none" ' +
            'viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" ' +
            'class="h-4 w-4"><path stroke-linecap="round" ' +
            'stroke-linejoin="round" d="' +
            path +
            '" /></svg>'
        )
    }

    const groupResults = (results) => {
        const groups = new Map()

        results.forEach((result) => {
            const type = result.type || 'unknown'

            if (!groups.has(type)) {
                groups.set(type, {
                    type,
                    label: result.typeLabel || result.type || '',
                    items: [],
                })
            }

            groups.get(type).items.push(result)
        })

        return [...groups.values()].sort((first, second) => {
            const firstRank = groupOrder.indexOf(first.type)
            const secondRank = groupOrder.indexOf(second.type)

            return (
                (firstRank === -1 ? groupOrder.length : firstRank) -
                (secondRank === -1 ? groupOrder.length : secondRank)
            )
        })
    }

    const groupHeading = (label) => {
        const heading = document.createElement('li')

        heading.setAttribute('role', 'presentation')
        heading.className =
            'bg-surface-low/60 text-outline-variant border-outline/40 border-b px-3 py-1.5 text-xs font-semibold uppercase tracking-wide'
        heading.textContent = label

        return heading
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

    const pageElements = () => [...document.querySelectorAll('main, footer')]

    const setPageInert = (value) => {
        pageElements().forEach((element) => {
            if (value) {
                if (!element.hasAttribute('inert')) {
                    element.setAttribute('data-site-search-managed-inert', '')
                    element.setAttribute('inert', '')
                }

                if (!element.hasAttribute('aria-hidden')) {
                    element.setAttribute('data-site-search-managed-hidden', '')
                    element.setAttribute('aria-hidden', 'true')
                }

                return
            }

            if (element.hasAttribute('data-site-search-managed-inert')) {
                element.removeAttribute('data-site-search-managed-inert')
                element.removeAttribute('inert')
            }

            if (element.hasAttribute('data-site-search-managed-hidden')) {
                element.removeAttribute('data-site-search-managed-hidden')
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

    const closeDialog = (dialog, { restoreFocus = true } = {}) => {
        const state = stateFor(dialog)

        cancelPendingSearch(dialog)
        dialog.classList.add('hidden')
        dialog.classList.remove('flex')
        dialog.setAttribute('aria-hidden', 'true')
        document.body.classList.remove('overflow-hidden')
        setPageInert(false)

        const resultsRegion = dialog.querySelector(selectors.region)
        if (resultsRegion) {
            resultsRegion.hidden = true
        }

        document
            .querySelectorAll(
                `${selectors.trigger}[aria-controls="${dialog.id}"]`,
            )
            .forEach((trigger) => {
                trigger.setAttribute('aria-expanded', 'false')
            })

        if (restoreFocus) {
            state.trigger?.focus()
        }
    }

    const openDialog = (dialog, trigger) => {
        const state = stateFor(dialog)
        const input = dialog.querySelector(selectors.input)

        // Opening search is exclusive with the header menus: close the account
        // dropdown, the platform/marketplace mega-menus, and the mobile menu so
        // two overlays never sit open at once.
        window.dispatchEvent(new CustomEvent('capell-close-header-menus'))
        window.dispatchEvent(
            new CustomEvent('capell-close-mobile-menu', {
                detail: { restoreFocus: false },
            }),
        )

        state.trigger = trigger
        dialog.classList.remove('hidden')
        dialog.classList.add('flex')
        dialog.setAttribute('aria-hidden', 'false')
        trigger.setAttribute('aria-expanded', 'true')
        document.body.classList.add('overflow-hidden')
        setPageInert(true)

        // Surface the idle hint immediately so the panel never opens
        // onto an empty box.
        if ((input?.value.trim() || '') === '') {
            clearResults(dialog)
        }

        window.requestAnimationFrame(() => input?.focus())
    }

    // Reset the panel to its idle state: region visible (while the
    // dialog is open), idle hint shown, every other block hidden.
    const clearResults = (dialog) => {
        const input = dialog.querySelector(selectors.input)
        const list = dialog.querySelector(selectors.list)
        const loading = dialog.querySelector(selectors.loading)
        const status = dialog.querySelector(selectors.status)
        const allResults = dialog.querySelector(selectors.allResults)
        const resultsRegion = dialog.querySelector(selectors.region)
        const idle = dialog.querySelector(selectors.idle)
        const empty = dialog.querySelector(selectors.empty)
        const state = stateFor(dialog)

        window.clearTimeout(state.debounceTimer)
        state.activeIndex = -1
        state.results = []
        list.replaceChildren()
        list.hidden = true
        if (loading) {
            loading.hidden = true
        }
        if (empty) {
            empty.hidden = true
        }
        allResults.hidden = true
        if (idle) {
            idle.hidden = false
        }
        if (resultsRegion) {
            resultsRegion.hidden = false
        }
        input.setAttribute('aria-expanded', 'false')
        input.removeAttribute('aria-activedescendant')
        status.textContent = ''
    }

    const setLoading = (dialog, query) => {
        const input = dialog.querySelector(selectors.input)
        const list = dialog.querySelector(selectors.list)
        const loading = dialog.querySelector(selectors.loading)
        const status = dialog.querySelector(selectors.status)
        const allResults = dialog.querySelector(selectors.allResults)
        const resultsRegion = dialog.querySelector(selectors.region)
        const idle = dialog.querySelector(selectors.idle)
        const empty = dialog.querySelector(selectors.empty)
        const state = stateFor(dialog)

        state.activeIndex = -1
        state.results = []
        const hasCurrentResults = list.children.length > 0 && !list.hidden
        list.hidden = !hasCurrentResults
        allResults.hidden = true
        if (idle) {
            idle.hidden = true
        }
        if (empty) {
            empty.hidden = true
        }

        if (resultsRegion) {
            resultsRegion.hidden = false
        }

        if (loading) {
            const label =
                loading.getAttribute('data-site-search-loading-template') ||
                'Searching for ":query"'

            loading.querySelector(
                '[data-site-search-loading-label]',
            ).textContent = label.replace(':query', query)
            loading.hidden = false
        }

        input.setAttribute('aria-expanded', 'true')
        input.removeAttribute('aria-activedescendant')
        status.textContent =
            loading?.getAttribute('data-site-search-loading-status') ||
            'Searching'
    }

    const setActiveResult = (dialog, index) => {
        const input = dialog.querySelector(selectors.input)
        const items = [...dialog.querySelectorAll('[data-site-search-result]')]
        const state = stateFor(dialog)

        state.activeIndex = index

        items.forEach((item, itemIndex) => {
            const isActive = itemIndex === index

            item.toggleAttribute('data-active', isActive)
            item.classList.toggle('bg-surface-low', isActive)
        })

        if (items[index]) {
            input.setAttribute('aria-activedescendant', items[index].id)
            items[index].scrollIntoView({ block: 'nearest' })
        } else {
            input.removeAttribute('aria-activedescendant')
        }
    }

    const resultItem = (dialog, result, index, listId) => {
        const item = document.createElement('li')
        const link = document.createElement('a')
        const icon = document.createElement('span')
        const text = document.createElement('span')
        const title = document.createElement('span')
        const excerpt = document.createElement('span')

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
        link.setAttribute('data-site-search-position', String(index + 1))
        link.setAttribute('data-site-search-surface', 'autocomplete')
        link.className =
            'hover:bg-surface-low flex items-start gap-3 p-3 text-left transition'

        icon.className = 'text-outline-variant mt-0.5 shrink-0'
        icon.setAttribute('aria-hidden', 'true')
        icon.innerHTML = typeIcon(result.type)

        text.className = 'grid min-w-0 gap-1'

        title.className = 'text-on-surface text-sm font-medium'
        title.textContent = result.title

        excerpt.className = 'text-on-surface-variant line-clamp-2 text-sm'
        excerpt.textContent = result.excerpt

        text.append(title, excerpt)
        link.append(icon, text)
        item.append(link)

        return item
    }

    const querySuggestionItem = (suggestion, index, listId, label) => {
        const item = document.createElement('li')
        const link = document.createElement('a')
        const title = document.createElement('span')
        const meta = document.createElement('span')

        item.id = `${listId}-option-${index}`
        item.setAttribute('role', 'option')
        item.setAttribute('data-site-search-result', '')
        item.className = 'border-outline/50 border-b last:border-b-0'

        link.href = suggestion.url
        link.className =
            'hover:bg-surface-low grid gap-1 p-3 text-left transition'

        title.className = 'text-on-surface text-sm font-medium'
        title.textContent = suggestion.query

        meta.className =
            'text-outline-variant text-xs font-medium uppercase tracking-wide'
        meta.textContent = label

        link.append(title, meta)
        item.append(link)

        return item
    }

    const searchUrlForQuery = (payload, input, query) => {
        const url = new URL(
            payload.allResultsUrl || input.form?.action || '/search',
            window.location.origin,
        )

        url.searchParams.set('q', query)

        return url.toString()
    }

    const renderResults = (dialog, payload) => {
        const input = dialog.querySelector(selectors.input)
        const list = dialog.querySelector(selectors.list)
        const loading = dialog.querySelector(selectors.loading)
        const status = dialog.querySelector(selectors.status)
        const allResults = dialog.querySelector(selectors.allResults)
        const resultsRegion = dialog.querySelector(selectors.region)
        const idle = dialog.querySelector(selectors.idle)
        const empty = dialog.querySelector(selectors.empty)
        const results = Array.isArray(payload.results) ? payload.results : []
        const querySuggestions = Array.isArray(payload.querySuggestions)
            ? payload.querySuggestions
            : []
        const correctedQuery =
            typeof payload.metadata?.corrected === 'string'
                ? payload.metadata.corrected
                : null
        const correctedSuggestion =
            correctedQuery && correctedQuery !== payload.metadata?.normalized
                ? {
                      query: correctedQuery,
                      url: searchUrlForQuery(payload, input, correctedQuery),
                  }
                : null
        const state = stateFor(dialog)
        const suggestionsTemplate =
            resultsRegion?.getAttribute(
                'data-site-search-suggestions-template',
            ) || '__count__ suggestions available.'
        const correctedLabel =
            resultsRegion?.getAttribute('data-site-search-corrected-label') ||
            'Did you mean'
        const querySuggestionLabel =
            resultsRegion?.getAttribute('data-site-search-query-label') ||
            'Popular search'
        const suggestionItems = [
            ...(correctedSuggestion ? [correctedSuggestion] : []),
            ...querySuggestions,
        ]
        // Suggestions render first (ungrouped), then results render in
        // type groups under small headings. `orderedItems` is the flat,
        // in-DOM-order list of selectable options that keyboard
        // navigation and aria-activedescendant index into.
        const children = []
        const orderedItems = []

        suggestionItems.forEach((suggestion, index) => {
            children.push(
                querySuggestionItem(
                    suggestion,
                    orderedItems.length,
                    list.id,
                    index === 0 && correctedSuggestion
                        ? correctedLabel
                        : querySuggestionLabel,
                ),
            )
            orderedItems.push(suggestion)
        })

        groupResults(results).forEach((group) => {
            children.push(groupHeading(group.label))

            group.items.forEach((result) => {
                children.push(
                    resultItem(dialog, result, orderedItems.length, list.id),
                )
                orderedItems.push(result)
            })
        })

        const hasItems = orderedItems.length > 0
        const query = input.value.trim()

        state.results = orderedItems
        if (loading) {
            loading.hidden = true
        }
        if (idle) {
            idle.hidden = true
        }
        list.replaceChildren(...children)
        list.hidden = !hasItems
        allResults.hidden = !hasItems

        if (empty) {
            empty.hidden = hasItems

            if (!hasItems) {
                const label = empty.querySelector(
                    '[data-site-search-empty-label]',
                )
                const template =
                    empty.getAttribute('data-site-search-empty-template') ||
                    'No results for __query__.'

                if (label) {
                    label.textContent = template.replace('__query__', query)
                }
            }
        }

        if (resultsRegion) {
            resultsRegion.hidden = false
        }
        allResults.href =
            payload.allResultsUrl || input.form?.action || '/search'
        input.setAttribute('aria-expanded', hasItems ? 'true' : 'false')
        status.textContent = hasItems
            ? suggestionsTemplate.replace('__count__', orderedItems.length)
            : ''
        setActiveResult(dialog, -1)
    }

    const fetchResults = async (dialog) => {
        const input = dialog.querySelector(selectors.input)
        const url = dialog.getAttribute('data-site-search-autocomplete-url')
        const minimumLength = Number.parseInt(
            dialog.getAttribute('data-site-search-minimum-length') || '2',
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
        const input = dialog.querySelector(selectors.input)
        const state = stateFor(dialog)
        const debounceMs = Number.parseInt(
            dialog.getAttribute('data-site-search-debounce-ms') || '150',
            10,
        )
        const minimumLength = Number.parseInt(
            dialog.getAttribute('data-site-search-minimum-length') || '2',
            10,
        )
        const query = input?.value.trim() || ''

        window.clearTimeout(state.debounceTimer)

        if (query.length < minimumLength) {
            clearResults(dialog)
            return
        }

        setLoading(dialog, query)
        state.debounceTimer = window.setTimeout(
            () => fetchResults(dialog),
            Math.max(0, debounceMs),
        )
    }

    document.addEventListener('click', (event) => {
        const trackedLink = event.target.closest('[data-site-search-click]')

        if (trackedLink && !window.capellSearchClickBeaconInitialized) {
            const trackingContainer = trackedLink.closest(
                '[data-site-search-click-url]',
            )
            const url = trackingContainer?.getAttribute(
                'data-site-search-click-url',
            )

            if (url) {
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

    // The header account dropdown and mega-menus dispatch this when they open,
    // so search closes as they take over (the mirror of openDialog's dispatch).
    window.addEventListener('capell-close-site-search', () => {
        document
            .querySelectorAll(`${selectors.dialog}:not(.hidden)`)
            .forEach((dialog) => closeDialog(dialog, { restoreFocus: false }))
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
                    (event.key?.toLowerCase() === 'k' &&
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
                    .querySelectorAll(`${selectors.dialog}:not(.hidden)`)
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
            const item = dialog.querySelectorAll('[data-site-search-result]')[
                state.activeIndex
            ]
            const link = item?.querySelector('a')

            if (link) {
                event.preventDefault()
                window.location.assign(link.href)
            }
        }
    })
})()
