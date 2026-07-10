/**
 * cookieConsent.js v2 - ChatbotScout
 *
 * Granular consent for necessary, statistics, preferences and marketing.
 * Statistics controls custom visitor tracking, browser fingerprinting and GA.
 */

(function () {
    'use strict';

    const COOKIE_V2 = 'cookie_consent_v2';
    const COOKIE_OLD = 'cookie_consent';
    const COOKIE_MONTHS = 6;
    const VISITOR_STORAGE_KEY = 'chatbotscout_visitor_id';

    // ============================================
    // COOKIE HELPERS
    // ============================================

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, months) {
        var expires = new Date();
        expires.setMonth(expires.getMonth() + months);

        document.cookie = name + '=' + encodeURIComponent(value)
            + '; path=/'
            + '; expires=' + expires.toUTCString()
            + '; SameSite=Lax'
            + (window.location.protocol === 'https:' ? '; Secure' : '');
    }

    function domainVariants() {
        var host = window.location.hostname;
        var variants = [''];
        var isIpAddress = /^(\d{1,3}\.){3}\d{1,3}$/.test(host) || host === 'localhost';

        if (host && !isIpAddress && host.indexOf('.') !== -1) {
            variants.push(host);
            variants.push('.' + host);

            var parts = host.split('.');
            if (parts.length > 2) {
                variants.push('.' + parts.slice(-2).join('.'));
            }
        }

        return Array.from(new Set(variants));
    }

    function deleteCookie(name) {
        domainVariants().forEach(function (domain) {
            document.cookie = name + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; SameSite=Lax'
                + (domain ? '; domain=' + domain : '')
                + (window.location.protocol === 'https:' ? '; Secure' : '');
        });
    }

    function deleteCookiesByPrefix(prefix) {
        document.cookie.split(';').forEach(function (part) {
            var name = part.split('=')[0].trim();
            if (name.indexOf(prefix) === 0) {
                deleteCookie(name);
            }
        });
    }

    function generateConsentId() {
        return 'CBS-' + Date.now() + '-' + Math.random().toString(36).slice(2, 11).toUpperCase();
    }

    // ============================================
    // CONSENT READ / WRITE
    // ============================================

    function normaliseConsent(consent) {
        if (!consent || typeof consent !== 'object' || consent.necessary === undefined) {
            return null;
        }

        return {
            necessary: true,
            statistics: !!consent.statistics,
            preferences: !!consent.preferences,
            marketing: !!consent.marketing,
            consentId: consent.consentId || generateConsentId(),
            date: consent.date || new Date().toISOString()
        };
    }

    function buildConsent(statistics, preferences, marketing, id) {
        return {
            necessary: true,
            statistics: !!statistics,
            preferences: !!preferences,
            marketing: !!marketing,
            consentId: id || generateConsentId(),
            date: new Date().toISOString()
        };
    }

    function getConsent() {
        var v2 = getCookie(COOKIE_V2);
        if (v2) {
            try {
                var parsed = normaliseConsent(JSON.parse(v2));
                if (parsed) return parsed;
            } catch (e) {}
        }

        try {
            var ls = localStorage.getItem(COOKIE_V2);
            if (ls) {
                var parsedLs = normaliseConsent(JSON.parse(ls));
                if (parsedLs) return parsedLs;
            }
        } catch (e) {}

        var old = getCookie(COOKIE_OLD);
        if (old === 'accepted') {
            return buildConsent(true, true, true, generateConsentId());
        }
        if (old === 'declined') {
            return buildConsent(false, false, false, generateConsentId());
        }

        return null;
    }

    function saveConsent(consent) {
        consent = normaliseConsent(consent) || buildConsent(false, false, false);
        consent.date = new Date().toISOString();

        setCookie(COOKIE_V2, JSON.stringify(consent), COOKIE_MONTHS);
        setCookie(COOKIE_OLD, consent.statistics ? 'accepted' : 'declined', COOKIE_MONTHS);

        try {
            localStorage.setItem(COOKIE_V2, JSON.stringify(consent));
        } catch (e) {}
    }

    function hasStatisticsConsent() {
        var consent = getConsent();
        return !!(consent && consent.statistics);
    }

    window.chatbotScoutConsent = {
        get: getConsent,
        hasStatistics: hasStatisticsConsent,
        hasPreferences: function () {
            var consent = getConsent();
            return !!(consent && consent.preferences);
        },
        hasMarketing: function () {
            var consent = getConsent();
            return !!(consent && consent.marketing);
        }
    };

    // ============================================
    // VISITOR.JS LOADER
    // ============================================

    function loadVisitorScript() {
        if (!hasStatisticsConsent()) return;

        if (document.getElementById('visitor-script')) {
            if (typeof window.collectAndSendVisitor === 'function') {
                window.collectAndSendVisitor();
            }
            return;
        }

        var script = document.createElement('script');
        script.id = 'visitor-script';
        script.src = 'js/visitor.js';
        script.async = true;
        document.body.appendChild(script);
    }

    function revokeStatisticsCookies() {
        try {
            localStorage.removeItem(VISITOR_STORAGE_KEY);
        } catch (e) {}

        fetch('api/revokeConsent.php', { method: 'POST' }).catch(function () {});

        deleteCookie('visitor_id');
        deleteCookie('_ga');
        deleteCookiesByPrefix('_ga_');
    }

    function applyConsent(consent) {
        consent = normaliseConsent(consent);
        if (!consent) return;

        if (typeof gtag === 'function') {
            gtag('consent', 'update', {
                'analytics_storage': consent.statistics ? 'granted' : 'denied',
                'ad_storage': consent.marketing ? 'granted' : 'denied',
                'ad_user_data': consent.marketing ? 'granted' : 'denied',
                'ad_personalization': consent.marketing ? 'granted' : 'denied'
            });
        }

        if (consent.statistics) {
            loadVisitorScript();
        } else {
            revokeStatisticsCookies();
        }
    }

    // ============================================
    // BANNER
    // ============================================

    function showBanner() {
        var banner = document.getElementById('cookie-banner');
        if (!banner) return;
        banner.hidden = false;
    }

    function hideBanner() {
        var banner = document.getElementById('cookie-banner');
        if (!banner) return;
        banner.hidden = true;
    }

    // ============================================
    // MODAL
    // ============================================

    function showModal() {
        var overlay = document.getElementById('cookie-modal-overlay');
        if (!overlay) return;

        var consent = getConsent();
        if (consent) {
            setToggle('cookie-toggle-statistics', consent.statistics);
            setToggle('cookie-toggle-preferences', consent.preferences);
            setToggle('cookie-toggle-marketing', consent.marketing);

            var idEl = document.getElementById('cookie-consent-id');
            var dateEl = document.getElementById('cookie-consent-date');
            if (idEl) idEl.textContent = 'Consent ID: ' + consent.consentId;
            if (dateEl) {
                var d = new Date(consent.date);
                dateEl.textContent = 'Date: ' + d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
            }
        } else {
            setToggle('cookie-toggle-statistics', false);
            setToggle('cookie-toggle-preferences', false);
            setToggle('cookie-toggle-marketing', false);

            var idEl2 = document.getElementById('cookie-consent-id');
            var dateEl2 = document.getElementById('cookie-consent-date');
            if (idEl2) idEl2.textContent = '';
            if (dateEl2) dateEl2.textContent = '';
        }

        overlay.hidden = false;
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                overlay.classList.add('cookie-modal--visible');
                if (getComputedStyle(overlay).display !== 'none') {
                    document.body.classList.add('cookie-modal-open');
                }
            });
        });
    }

    function hideModal() {
        var overlay = document.getElementById('cookie-modal-overlay');
        if (!overlay) return;
        overlay.classList.remove('cookie-modal--visible');
        document.body.classList.remove('cookie-modal-open');
        setTimeout(function () {
            overlay.hidden = true;
        }, 280);
    }

    // ============================================
    // TOGGLES
    // ============================================

    function setToggle(id, value) {
        var toggle = document.getElementById(id);
        if (!toggle) return;
        toggle.classList.toggle('cookie-toggle--on', !!value);
        toggle.setAttribute('aria-checked', value ? 'true' : 'false');
    }

    function getToggleValue(id) {
        var toggle = document.getElementById(id);
        return toggle ? toggle.classList.contains('cookie-toggle--on') : false;
    }

    function toggleCategory(header) {
        var category = header.closest('.cookie-category');
        if (category) category.classList.toggle('cookie-category--open');
    }

    function revokePreferencesCookies() {
        try { localStorage.removeItem('preferredLayout'); } catch (e) {}
        try { localStorage.removeItem('chatbot_showcase_view'); } catch (e) {}
        try { localStorage.removeItem('favourites_showcase_view'); } catch (e) {}
    }

    // ============================================
    // ACTIONS
    // ============================================

    function acceptAll() {
        var consent = buildConsent(true, true, true);
        saveConsent(consent);
        applyConsent(consent);
        hideBanner();
        hideModal();
    }

    function rejectAll() {
        var consent = buildConsent(false, false, false);
        saveConsent(consent);
        applyConsent(consent);
        revokePreferencesCookies();
        hideBanner();
        hideModal();
    }

    function savePreferences() {
        var consent = buildConsent(
            getToggleValue('cookie-toggle-statistics'),
            getToggleValue('cookie-toggle-preferences'),
            getToggleValue('cookie-toggle-marketing')
        );
        saveConsent(consent);
        applyConsent(consent);

        if (!consent.preferences) revokePreferencesCookies();

        hideBanner();
        hideModal();
    }

    // ============================================
    // WIRE UP
    // ============================================

    function wireUp() {
        var btnAcceptAll = document.getElementById('cookie-btn-accept-all');
        var btnRejectAll = document.getElementById('cookie-btn-reject-all');
        var btnManage = document.getElementById('cookie-btn-manage');

        if (btnAcceptAll) btnAcceptAll.addEventListener('click', acceptAll);
        if (btnRejectAll) btnRejectAll.addEventListener('click', rejectAll);
        if (btnManage) btnManage.addEventListener('click', showModal);

        var btnModalAccept = document.getElementById('cookie-modal-accept-all');
        var btnModalReject = document.getElementById('cookie-modal-reject-all');
        var btnModalSave = document.getElementById('cookie-modal-save');
        var btnModalClose = document.getElementById('cookie-modal-close');
        var modalOverlay = document.getElementById('cookie-modal-overlay');

        if (btnModalAccept) btnModalAccept.addEventListener('click', acceptAll);
        if (btnModalReject) btnModalReject.addEventListener('click', rejectAll);
        if (btnModalSave) btnModalSave.addEventListener('click', savePreferences);
        if (btnModalClose) btnModalClose.addEventListener('click', hideModal);
        if (modalOverlay) {
            modalOverlay.addEventListener('click', function (event) {
                if (event.target === modalOverlay) hideModal();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modalOverlay.hidden) hideModal();
            });
        }

        ['statistics', 'preferences', 'marketing'].forEach(function (cat) {
            var toggle = document.getElementById('cookie-toggle-' + cat);
            if (toggle) {
                toggle.addEventListener('click', function () {
                    var next = !this.classList.contains('cookie-toggle--on');
                    setToggle(this.id, next);
                });
                toggle.addEventListener('keydown', function (event) {
                    if (event.key === ' ' || event.key === 'Enter') {
                        event.preventDefault();
                        var next = !this.classList.contains('cookie-toggle--on');
                        setToggle(this.id, next);
                    }
                });
            }
        });

        var modalBody = document.querySelector('.cookie-modal-body');
        if (modalBody) {
            modalBody.addEventListener('click', function (e) {
                var header = e.target.closest('.cookie-category-header');
                if (header) toggleCategory(header);
            });
        }

        var settingsBtn = document.getElementById('cookie-settings-btn');
        if (settingsBtn) {
            settingsBtn.addEventListener('click', showModal);
        }
    }

    // ============================================
    // INIT
    // ============================================

    function init() {
        wireUp();

        var consent = getConsent();

        if (consent) {
            applyConsent(consent);
            if (!getCookie(COOKIE_V2)) {
                saveConsent(consent);
            }
        } else {
            revokeStatisticsCookies();
            showBanner();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
