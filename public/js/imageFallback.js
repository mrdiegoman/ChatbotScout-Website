(function () {
    'use strict';

    document.addEventListener('error', event => {
        const image = event.target;
        if (!(image instanceof HTMLImageElement)) return;

        const fallbackSrc = image.getAttribute('data-fallback-src');
        if (!fallbackSrc || image.dataset.fallbackApplied === 'true') return;

        image.dataset.fallbackApplied = 'true';
        image.src = fallbackSrc;
    }, true);
})();
