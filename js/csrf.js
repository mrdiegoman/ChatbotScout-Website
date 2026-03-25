(function () {
    'use strict';

    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) return;

    var csrfToken = meta.getAttribute('content');
    if (!csrfToken) return;

    var originalFetch = window.fetch;

    window.fetch = function (url, options) {
        options = options || {};
        var method = (options.method || 'GET').toUpperCase();

        if (method !== 'GET' && method !== 'HEAD') {
            if (options.headers instanceof Headers) {
                options.headers.set('X-CSRF-Token', csrfToken);
            } else {
                options.headers = Object.assign({}, options.headers, { 'X-CSRF-Token': csrfToken });
            }
        }

        return originalFetch.call(this, url, options);
    };
})();
