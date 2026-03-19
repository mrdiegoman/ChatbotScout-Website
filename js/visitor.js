/**
 * visitor.js - Custom Browser Fingerprinting
 *
 * Zbiera stabilné signály z browsera a generuje hash.
 * Nemení sa po vymazaní cookies.
 */
(function () {

    // ============================================
    // SIGNÁLY
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
            ctx.fillText('FP test 🔐', 2, 2);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('FP test 🔐', 4, 4);
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
    // SHA-256 HASH (pure JS, bez externých knižníc)
    // ============================================

    function sha256(str) {
        function rightRotate(value, amount) {
            return (value >>> amount) | (value << (32 - amount));
        }

        var mathPow = Math.pow;
        var maxWord = mathPow(2, 32);
        var lengthProperty = 'length';
        var i, j;
        var result = '';

        var words = [];
        var asciiBitLength = str[lengthProperty] * 8;

        var hash = sha256.h = sha256.h || [];
        var k = sha256.k = sha256.k || [];
        var primeCounter = k[lengthProperty];

        var isComposite = {};
        for (var candidate = 2; primeCounter < 64; candidate++) {
            if (!isComposite[candidate]) {
                for (i = 0; i < 313; i += candidate) {
                    isComposite[i] = candidate;
                }
                hash[primeCounter] = (mathPow(candidate, .5) * maxWord) | 0;
                k[primeCounter++] = (mathPow(candidate, 1 / 3) * maxWord) | 0;
            }
        }

        str += '\x80';
        while (str[lengthProperty] % 64 - 56) str += '\x00';
        for (i = 0; i < str[lengthProperty]; i++) {
            j = str.charCodeAt(i);
            if (j >> 8) return '';
            words[i >> 2] |= j << ((3 - i) % 4) * 8;
        }
        words[words[lengthProperty]] = ((asciiBitLength / maxWord) | 0);
        words[words[lengthProperty]] = (asciiBitLength);

        for (j = 0; j < words[lengthProperty];) {
            var w = words.slice(j, j += 16);
            var oldHash = hash.slice(0, 8);

            for (i = 0; i < 64; i++) {
                var i2 = i + j - 16;
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

    // ============================================
    // ZBIERANIE + ODOSLANIE
    // ============================================

    function collectAndSend() {
        var signals = [
            getCanvasFingerprint(),
            getWebGLFingerprint(),
            getScreenInfo(),
            getHardwareInfo(),
            getTimezone(),
            getLanguages(),
            getPlugins(),
            navigator.userAgent
        ].join('|||');

        var fingerprint = sha256(signals);

        if (!fingerprint) return;
        
        fetch('../api/identify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fingerprint: fingerprint })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            // Ak server vrátil iné visitor_id (restored), okamžite aktualizuj cookie
            // aby ďalšie AJAX volania (napr. recordVisit) použili správne ID
            if (data && data.visitor_id) {
                var expires = new Date();
                expires.setFullYear(expires.getFullYear() + 1);
                document.cookie = 'visitor_id=' + data.visitor_id
                    + '; path=/'
                    + '; expires=' + expires.toUTCString();
            }
        })
        .catch(function () {});
    }

    // Spusti po načítaní stránky
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', collectAndSend);
    } else {
        collectAndSend();
    }

})();