import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import vm from 'node:vm'

const document = new EventTarget()
document.querySelector = () => null
const window = new EventTarget()
const requests = []
let request
const link = {
    href: 'https://site.test/customer-story',
    closest: (selector) =>
        selector === '[data-site-search-click]'
            ? link
            : selector === '[data-site-search-click-url]'
              ? { getAttribute: () => '/click' }
              : null,
    getAttribute: (attribute) =>
        ({
            'data-site-search-query': 'customer story',
            'data-site-search-type': 'page',
            'data-site-search-position': '2',
            'data-site-search-surface': 'header',
        })[attribute] ?? null,
}
const script = readFileSync(
    process.argv[2] ??
        new URL('../../resources/dist/search-modal.js', import.meta.url),
    'utf8',
)
const context = vm.createContext({
    window,
    document,
    FormData,
    fetch: async (url, options) => {
        request = { url, options }
        requests.push(request)
        return {}
    },
})
vm.runInContext(script, context)
vm.runInContext(script, context)
const event = new Event('click', { cancelable: true })
Object.defineProperty(event, 'target', { value: link })
document.dispatchEvent(event)
assert.equal(requests.length, 1)
assert.equal(event.defaultPrevented, false)
assert.equal(request.url, '/click')
assert.equal(request.options.method, 'POST')
assert.equal(request.options.mode, 'no-cors')
assert.equal(request.options.keepalive, true)
assert.deepEqual(Object.fromEntries(request.options.body), {
    query: 'customer story',
    url: link.href,
    type: 'page',
    position: '2',
    surface: 'header',
})
assert.equal(request.options.headers?.['X-CSRF-TOKEN'], undefined)
console.log(
    'PASS click navigation and anonymous no-cors beacon payload without CSRF',
)
