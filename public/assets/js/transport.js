window.StoreTransport = (() => {
    let tokenPromise;
    const nativeFetch = window.fetch.bind(window);
    function endpoint(route) {
        const original = new URL(route, window.location.href);
        const result = new URL('api.php', window.location.href);
        result.search = original.search;
        result.searchParams.set('route', original.pathname);
        return result;
    }
    async function token() {
        tokenPromise ??= nativeFetch(endpoint('/api/session'), { credentials: 'same-origin' })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Unable to start session.');
                return data.csrfToken;
            }).catch(error => { tokenPromise = undefined; throw error; });
        return tokenPromise;
    }
    async function request(route, options = {}) {
        const headers = new Headers(options.headers);
        if (!['GET', 'HEAD'].includes((options.method || 'GET').toUpperCase())) {
            headers.set('X-CSRF-Token', await token());
        }
        return nativeFetch(endpoint(route), { ...options, headers, credentials: 'same-origin' });
    }
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
    }
    return { fetch: request, escapeHtml };
})();