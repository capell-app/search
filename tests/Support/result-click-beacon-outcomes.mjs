import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import vm from 'node:vm'

const script = readFileSync(0, 'utf8')
const document = new EventTarget()
const requests = []
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
            'data-site-search-token': 'signed-click-token',
            'data-site-search-type': 'page',
            'data-site-search-position': '2',
            'data-site-search-surface': 'results',
        })[attribute] ?? null,
}
const context = vm.createContext({
    window: {},
    document,
    FormData,
    fetch: async (url, options) => {
        requests.push({ url, options })
        return {}
    },
})
vm.runInContext(script, context)
vm.runInContext(script, context)
const event = new Event('click', { cancelable: true })
Object.defineProperty(event, 'target', { value: link })
document.dispatchEvent(event)
assert.equal(event.defaultPrevented, false)
assert.equal(requests.length, 1)
const { url, options } = requests[0]
assert.equal(url, '/click')
assert.equal(options.method, 'POST')
assert.equal(options.mode, 'no-cors')
assert.equal(options.keepalive, true)
assert.deepEqual(Object.fromEntries(options.body), {
    query: 'customer story',
    url: link.href,
    token: 'signed-click-token',
    type: 'page',
    position: '2',
    surface: 'results',
})
assert.equal(options.headers?.['X-CSRF-TOKEN'], undefined)
console.log(
    'PASS result click navigation, token payload and single initialisation without CSRF',
)
