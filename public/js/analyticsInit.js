(function () {
    'use strict';

    const script = document.currentScript;
    const measurementId = script ? script.getAttribute('data-measurement-id') : '';

    if (!measurementId || typeof window.gtag !== 'function') return;

    window.gtag('js', new Date());
    window.gtag('config', measurementId);
})();
