/**
 * cookieConsent.js v2 — ChatbotScout
 *
 * Full GDPR-compliant cookie consent:
 *  - Backward compatible with old cookie_consent = accepted/declined
 *  - Granular categories: necessary, statistics, preferences, marketing
 *  - Consent ID + timestamp for GDPR records
 *  - Expandable category accordions with cookie tables
 *  - Fixed "Cookie Settings" button
 */

(function () {
    'use strict';

    const COOKIE_V2     = 'cookie_consent_v2';
    const COOKIE_OLD    = 'cookie_consent';
    const COOKIE_MONTHS = 6;

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

    function generateConsentId() {
        return 'CBS-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9).toUpperCase();
    }

    // ============================================
    // CONSENT READ / WRITE
    // ============================================

    function getConsent() {
        // Check new v2 cookie first
        var v2 = getCookie(COOKIE_V2);
        if (v2) {
            try {
                var parsed = JSON.parse(v2);
                if (parsed && typeof parsed === 'object' && parsed.necessary !== undefined) {
                    return parsed;
                }
            } catch (e) {}
        }

        // Migrate old cookie format
        var old = getCookie(COOKIE_OLD);
        if (old === 'accepted') {
            return buildConsent(true, true, true, generateConsentId());
        }
        if (old === 'declined') {
            return buildConsent(false, false, false, generateConsentId());
        }

        return null;
    }

    function buildConsent(statistics, preferences, marketing, id) {
        return {
            necessary:   true,
            statistics:  !!statistics,
            preferences: !!preferences,
            marketing:   !!marketing,
            consentId:   id || generateConsentId(),
            date:        new Date().toISOString()
        };
    }

    function saveConsent(consent) {
        if (!consent.consentId) consent.consentId = generateConsentId();
        consent.date = new Date().toISOString();
        setCookie(COOKIE_V2, JSON.stringify(consent), COOKIE_MONTHS);
        // Keep old cookie for backward compatibility with identify.php / bootstrap.php
        setCookie(COOKIE_OLD, consent.statistics ? 'accepted' : 'declined', COOKIE_MONTHS);
    }

    // ============================================
    // VISITOR.JS LOADER
    // visitor.js sa postará o všetko — vytvorí visitora s fingerprintom hneď
    // cez identify.php (createWithFingerprint). Žiadny dvojkrokový proces.
    // ============================================

    function loadVisitorScript() {
        if (document.getElementById('visitor-script')) return;
        var script = document.createElement('script');
        script.id    = 'visitor-script';
        script.src   = '../js/visitor.js';
        script.async = true;
        document.body.appendChild(script);
    }

    function applyConsent(consent) {
        if (consent.statistics) {
            loadVisitorScript();
        }
    }

    // ============================================
    // BANNER
    // ============================================

    function showBanner() {
        var banner = document.getElementById('cookie-banner');
        if (!banner) return;
        banner.style.display = 'block';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                banner.classList.add('cookie-banner--visible');
            });
        });
    }

    function hideBanner() {
        var banner = document.getElementById('cookie-banner');
        if (!banner) return;
        banner.classList.remove('cookie-banner--visible');
        banner.classList.add('cookie-banner--hidden');
        setTimeout(function () { banner.style.display = 'none'; }, 380);
    }

    // ============================================
    // MODAL
    // ============================================

    function showModal() {
        var overlay = document.getElementById('cookie-modal-overlay');
        if (!overlay) return;

        var consent = getConsent();
        if (consent) {
            setToggle('cookie-toggle-statistics',  consent.statistics);
            setToggle('cookie-toggle-preferences', consent.preferences);
            setToggle('cookie-toggle-marketing',   consent.marketing);

            var idEl   = document.getElementById('cookie-consent-id');
            var dateEl = document.getElementById('cookie-consent-date');
            if (idEl)   idEl.textContent   = 'Consent ID: ' + consent.consentId;
            if (dateEl) {
                var d = new Date(consent.date);
                dateEl.textContent = 'Date: ' + d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
            }
        } else {
            setToggle('cookie-toggle-statistics',  false);
            setToggle('cookie-toggle-preferences', false);
            setToggle('cookie-toggle-marketing',   false);

            var idEl2 = document.getElementById('cookie-consent-id');
            if (idEl2) idEl2.textContent = '';
            var dateEl2 = document.getElementById('cookie-consent-date');
            if (dateEl2) dateEl2.textContent = '';
        }

        overlay.style.display = 'flex';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                overlay.classList.add('cookie-modal--visible');
            });
        });
        document.body.style.overflow = 'hidden';
    }

    function hideModal() {
        var overlay = document.getElementById('cookie-modal-overlay');
        if (!overlay) return;
        overlay.classList.remove('cookie-modal--visible');
        setTimeout(function () {
            overlay.style.display = 'none';
        }, 280);
        document.body.style.overflow = '';
    }

    // ============================================
    // TOGGLES
    // ============================================

    function setToggle(id, value) {
        var toggle = document.getElementById(id);
        if (!toggle) return;
        toggle.classList.toggle('cookie-toggle--on', !!value);
    }

    function getToggleValue(id) {
        var toggle = document.getElementById(id);
        return toggle ? toggle.classList.contains('cookie-toggle--on') : false;
    }

    // ============================================
    // CATEGORY ACCORDION
    // ============================================

    function toggleCategory(header) {
        var category = header.closest('.cookie-category');
        if (category) category.classList.toggle('cookie-category--open');
    }

    // ============================================
    // REVOKE — vymaže cookies pri odvolaní súhlasu
    // ============================================

    function deleteCookie(name) {
        document.cookie = name + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
    }

    function revokeStatisticsCookies() {
        // visitor_id je httponly — JS k nemu nemá prístup, mažeme server-side
        fetch('../api/revokeConsent.php', { method: 'POST' }).catch(function () {});
        // Fallback pokus pre prípad že nie je httponly (dev prostredie)
        deleteCookie('visitor_id');
    }

    function revokePreferencesCookies() {
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
        revokeStatisticsCookies();
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

        if (!consent.statistics)  revokeStatisticsCookies();
        if (!consent.preferences) revokePreferencesCookies();

        hideBanner();
        hideModal();
    }

    // ============================================
    // WIRE UP EVENT LISTENERS
    // ============================================

    function wireUp() {
        var btnAcceptAll = document.getElementById('cookie-btn-accept-all');
        var btnRejectAll = document.getElementById('cookie-btn-reject-all');
        var btnManage    = document.getElementById('cookie-btn-manage');

        if (btnAcceptAll) btnAcceptAll.addEventListener('click', acceptAll);
        if (btnRejectAll) btnRejectAll.addEventListener('click', rejectAll);
        if (btnManage)    btnManage.addEventListener('click', showModal);

        var btnModalAccept = document.getElementById('cookie-modal-accept-all');
        var btnModalReject = document.getElementById('cookie-modal-reject-all');
        var btnModalSave   = document.getElementById('cookie-modal-save');
        var btnModalClose  = document.getElementById('cookie-modal-close');

        if (btnModalAccept) btnModalAccept.addEventListener('click', acceptAll);
        if (btnModalReject) btnModalReject.addEventListener('click', rejectAll);
        if (btnModalSave)   btnModalSave.addEventListener('click', savePreferences);
        if (btnModalClose)  btnModalClose.addEventListener('click', hideModal);

        ['statistics', 'preferences', 'marketing'].forEach(function (cat) {
            var toggle = document.getElementById('cookie-toggle-' + cat);
            if (toggle) {
                toggle.addEventListener('click', function () {
                    this.classList.toggle('cookie-toggle--on');
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

        var overlay = document.getElementById('cookie-modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) hideModal();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') hideModal();
        });

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
            showBanner();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();