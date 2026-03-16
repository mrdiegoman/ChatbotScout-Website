/**
 * cookieConsent.js
 *
 * Manages cookie consent banner.
 * - Shows banner if no consent decision has been made yet
 * - Accept  → sets cookie_consent=accepted (6 months) → runs visitor fingerprinting
 * - Decline → sets cookie_consent=declined (6 months) → visitor.js never runs
 */

(function () {
    'use strict';

    var COOKIE_NAME    = 'cookie_consent';
    var COOKIE_MONTHS  = 6;

    // ============================================
    // COOKIE HELPERS
    // ============================================

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, months) {
        var expires = new Date();
        expires.setMonth(expires.getMonth() + months);
        document.cookie = name + '=' + encodeURIComponent(value)
            + '; path=/'
            + '; expires=' + expires.toUTCString()
            + '; SameSite=Lax';
    }

    // ============================================
    // VISITOR.JS LOADER
    // ============================================

    function loadVisitorScript() {
        var script    = document.createElement('script');
        script.src    = '../js/visitor.js';
        script.async  = true;
        document.body.appendChild(script);
    }

    // ============================================
    // BANNER ACTIONS
    // ============================================

    function hideBanner(banner) {
        banner.classList.remove('cookie-banner--visible');
        banner.classList.add('cookie-banner--hidden');
        setTimeout(function () {
            banner.style.display = 'none';
            document.body.style.paddingBottom = '';
        }, 380);
    }

    function onAccept() {
        setCookie(COOKIE_NAME, 'accepted', COOKIE_MONTHS);
        var banner = document.getElementById('cookie-consent-banner');
        if (banner) hideBanner(banner);
        loadVisitorScript();
    }

    function onDecline() {
        setCookie(COOKIE_NAME, 'declined', COOKIE_MONTHS);
        var banner = document.getElementById('cookie-consent-banner');
        if (banner) hideBanner(banner);
    }

    // ============================================
    // INIT
    // ============================================

    function init() {
        var consent = getCookie(COOKIE_NAME);

        // Already accepted → load visitor script and do nothing else
        if (consent === 'accepted') {
            loadVisitorScript();
            return;
        }

        // Already declined → do nothing
        if (consent === 'declined') {
            return;
        }

        // No decision yet → show banner
        var banner = document.getElementById('cookie-consent-banner');
        if (!banner) return;

        // Wire up buttons
        var btnAccept  = document.getElementById('cookie-btn-accept');
        var btnDecline = document.getElementById('cookie-btn-decline');

        if (btnAccept)  btnAccept.addEventListener('click', onAccept);
        if (btnDecline) btnDecline.addEventListener('click', onDecline);

        // Slide up after a short delay so the animation is visible
        banner.style.display = 'block';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                banner.classList.add('cookie-banner--visible');
            });
        });
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();