/**
 * visitor.js - consent-gated visitor identification
 *
 * The server-side HttpOnly visitor_id cookie is canonical. Browser
 * fingerprinting and localStorage backup are used only after Statistics consent.
 */
(function () {
    'use strict';

    var COOKIE_V2 = 'cookie_consent_v2';
    var COOKIE_OLD = 'cookie_consent';
    var STORAGE_KEY = 'chatbotscout_visitor_id';
    var UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
    var pendingIdentify = null;

    // ============================================
    // CONSENT
    // ============================================

    function getCookie(name) {
        var escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function parseConsent(raw) {
        if (!raw) return null;
        try {
            var consent = JSON.parse(raw);
            if (consent && typeof consent === 'object' && consent.necessary !== undefined) {
                return consent;
            }
        } catch (e) {}
        return null;
    }

    function hasStatisticsConsent() {
        if (window.chatbotScoutConsent && typeof window.chatbotScoutConsent.hasStatistics === 'function') {
            return window.chatbotScoutConsent.hasStatistics();
        }

        var cookieConsent = parseConsent(getCookie(COOKIE_V2));
        if (cookieConsent) return !!cookieConsent.statistics;

        try {
            var storageConsent = parseConsent(localStorage.getItem(COOKIE_V2));
            if (storageConsent) return !!storageConsent.statistics;
        } catch (e) {}

        return getCookie(COOKIE_OLD) === 'accepted';
    }

    function getStoredVisitorId() {
        try {
            var value = localStorage.getItem(STORAGE_KEY);
            return UUID_PATTERN.test(value || '') ? value : null;
        } catch (e) {
            return null;
        }
    }

    function setStoredVisitorId(visitorId) {
        if (!UUID_PATTERN.test(visitorId || '') || !hasStatisticsConsent()) return;

        try {
            localStorage.setItem(STORAGE_KEY, visitorId);
        } catch (e) {}
    }

    function clearStoredVisitorId() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
    }

    // ============================================
    // SIGNALS
    // ============================================

    function getCanvasFingerprint() {
        try {
            var canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 50;
            var ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(100, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('FP test', 2, 2);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('FP test', 4, 4);
            return canvas.toDataURL();
        } catch (e) {
            return 'canvas_error';
        }
    }

    function getWebGLFingerprint() {
        try {
            var canvas = document.createElement('canvas');
            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) return 'webgl_unsupported';
            var info = gl.getExtension('WEBGL_debug_renderer_info');
            if (!info) return 'webgl_no_info';
            return [
                gl.getParameter(info.UNMASKED_VENDOR_WEBGL),
                gl.getParameter(info.UNMASKED_RENDERER_WEBGL)
            ].join('::');
        } catch (e) {
            return 'webgl_error';
        }
    }

    function getScreenInfo() {
        return [
            screen.width,
            screen.height,
            screen.colorDepth,
            screen.pixelDepth,
            window.devicePixelRatio || 1
        ].join('x');
    }

    function getHardwareInfo() {
        return [
            navigator.hardwareConcurrency || 'unknown',
            navigator.deviceMemory || 'unknown',
            navigator.platform || 'unknown'
        ].join('|');
    }

    function getTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch (e) {
            return new Date().getTimezoneOffset().toString();
        }
    }

    function getLanguages() {
        return (navigator.languages || [navigator.language]).join(',');
    }

    function getPlugins() {
        try {
            var plugins = [];
            for (var i = 0; i < navigator.plugins.length; i++) {
                plugins.push(navigator.plugins[i].name);
            }
            return plugins.sort().join('|');
        } catch (e) {
            return 'plugins_error';
        }
    }

    // ============================================
    // SHA-256
    // ============================================

    function sha256Fallback(str) {
        function rightRotate(value, amount) {
            return (value >>> amount) | (value << (32 - amount));
        }

        str = encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function (_, p1) {
            return String.fromCharCode(parseInt(p1, 16));
        });

        var mathPow = Math.pow;
        var maxWord = mathPow(2, 32);
        var lengthProperty = 'length';
        var i, j;
        var result = '';
        var words = [];
        var asciiBitLength = str[lengthProperty] * 8;
        var hash = sha256Fallback.h ? sha256Fallback.h.slice(0) : [];
        var k = sha256Fallback.k ? sha256Fallback.k.slice(0) : [];
        var primeCounter = k[lengthProperty];
        var isComposite = {};

        for (var candidate = 2; primeCounter < 64; candidate++) {
            if (!isComposite[candidate]) {
                for (i = 0; i < 313; i += candidate) isComposite[i] = candidate;
                hash[primeCounter] = (mathPow(candidate, 0.5) * maxWord) | 0;
                k[primeCounter++] = (mathPow(candidate, 1 / 3) * maxWord) | 0;
            }
        }

        sha256Fallback.h = hash.slice(0);
        sha256Fallback.k = k.slice(0);

        str += '\x80';
        while (str[lengthProperty] % 64 - 56) str += '\x00';
        for (i = 0; i < str[lengthProperty]; i++) {
            j = str.charCodeAt(i);
            words[i >> 2] |= j << ((3 - i) % 4) * 8;
        }
        words[words[lengthProperty]] = ((asciiBitLength / maxWord) | 0);
        words[words[lengthProperty]] = asciiBitLength;

        for (j = 0; j < words[lengthProperty];) {
            var w = words.slice(j, j += 16);
            var oldHash = hash.slice(0);

            for (i = 0; i < 64; i++) {
                var w15 = w[i - 15], w2 = w[i - 2];
                var a = hash[0], e = hash[4];
                var temp1 = hash[7]
                    + (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25))
                    + ((e & hash[5]) ^ ((~e) & hash[6]))
                    + k[i]
                    + (w[i] = (i < 16) ? w[i] : (
                        w[i - 16]
                        + (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15 >>> 3))
                        + w[i - 7]
                        + (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2 >>> 10))
                    ) | 0);
                var temp2 = (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22))
                    + ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));

                hash = [(temp1 + temp2) | 0].concat(hash);
                hash[4] = (hash[4] + temp1) | 0;
                hash.length = 8;
            }

            for (i = 0; i < 8; i++) {
                hash[i] = (hash[i] + oldHash[i]) | 0;
            }
        }

        for (i = 0; i < 8; i++) {
            for (j = 3; j + 1; j--) {
                var b = (hash[i] >> (j * 8)) & 255;
                result += ((b < 16) ? 0 : '') + b.toString(16);
            }
        }
        return result;
    }

    function sha256(str) {
        if (window.crypto && window.crypto.subtle && window.TextEncoder) {
            return window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(str))
                .then(function (buffer) {
                    return Array.prototype.map.call(new Uint8Array(buffer), function (x) {
                        return ('00' + x.toString(16)).slice(-2);
                    }).join('');
                });
        }

        return Promise.resolve(sha256Fallback(str));
    }

    // ============================================
    // COLLECT + SEND
    // ============================================

    function getUtmPayload() {
        var payload = {};
        var params = new URLSearchParams(window.location.search);
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function (key) {
            var value = params.get(key);
            if (value) payload[key] = value;
        });
        return payload;
    }

    function buildSignals() {
        return [
            getCanvasFingerprint(),
            getWebGLFingerprint(),
            getScreenInfo(),
            getHardwareInfo(),
            getTimezone(),
            getLanguages(),
            getPlugins(),
            navigator.userAgent
        ].join('|||');
    }

    function collectAndSend() {
        if (!hasStatisticsConsent()) {
            clearStoredVisitorId();
            return Promise.resolve({ success: true, action: 'no_consent_client' });
        }

        if (pendingIdentify) {
            return pendingIdentify;
        }

        pendingIdentify = sha256(buildSignals())
            .then(function (fingerprint) {
                if (!fingerprint) return null;

                var payload = Object.assign({
                    fingerprint: fingerprint,
                    stored_visitor_id: getStoredVisitorId(),
                    landing_page: window.location.pathname + window.location.search,
                    referrer_url: document.referrer || null
                }, getUtmPayload());

                return fetch('api/identify.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
            })
            .then(function (response) {
                return response ? response.json() : null;
            })
            .then(function (data) {
                if (data && data.visitor_id) {
                    setStoredVisitorId(data.visitor_id);
                }
                pendingIdentify = null;
                return data;
            })
            .catch(function () {
                pendingIdentify = null;
                return null;
            });

        return pendingIdentify;
    }

    window.collectAndSendVisitor = collectAndSend;
    window.chatbotScoutVisitor = {
        ready: collectAndSend,
        getStoredVisitorId: getStoredVisitorId,
        clearStoredVisitorId: clearStoredVisitorId
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', collectAndSend);
    } else {
        collectAndSend();
    }
})();
