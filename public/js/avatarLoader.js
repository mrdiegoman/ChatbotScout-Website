(function () {
    'use strict';

    function loadAvatar(image) {
        const realSrc = image.getAttribute('data-src');
        if (!realSrc || image.dataset.avatarLoaded === 'true') return;

        const preload = new Image();
        preload.onload = () => {
            image.dataset.avatarLoaded = 'true';
            image.src = realSrc;
        };
        preload.src = realSrc;
    }

    function loadAvatars() {
        document.querySelectorAll('img.user-avatar-img[data-src]').forEach(loadAvatar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAvatars, { once: true });
    } else {
        loadAvatars();
    }
})();
