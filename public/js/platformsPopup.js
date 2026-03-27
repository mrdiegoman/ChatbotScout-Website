/**
 * platformsPopup.js
 *
 * Zdieľaný popup pre "Platforms" button na chatbotShowcase aj favouritesShowcase.
 * Číta integrácie priamo z data-integrations atribútu buttonu — žiadny extra API call.
 *
 * Použitie:
 *   <button ... data-integrations='["Shopify","WordPress"]' data-chatbot-name="Tidio" onclick="openPlatformsPopup(event, this)">
 */

(function () {
    'use strict';

    // ============================================
    // CREATE POPUP ELEMENT (raz pri načítaní)
    // ============================================

    const overlay = document.createElement('div');
    overlay.id        = 'platformsPopupOverlay';
    overlay.className = 'platforms-popup-overlay';
    overlay.innerHTML = `
        <div class="platforms-popup" role="dialog" aria-modal="true" aria-label="Platforms">
            <div class="platforms-popup-header">
                <p class="platforms-popup-title">Platforms</p>
                <button class="platforms-popup-close" aria-label="Close">&times;</button>
            </div>
            <ul class="platforms-popup-list" id="platformsPopupList"></ul>
        </div>
    `;
    document.body.appendChild(overlay);

    const popup  = overlay.querySelector('.platforms-popup');
    const list   = overlay.querySelector('#platformsPopupList');
    const closeB = overlay.querySelector('.platforms-popup-close');
    const title  = overlay.querySelector('.platforms-popup-title');

    // ============================================
    // OPEN
    // ============================================

    function openPopup(integrations, chatbotName) {
        // Nastav titulek
        title.textContent = chatbotName ? `${chatbotName} · Platforms` : 'Platforms';

        // Naplň zoznam
        list.innerHTML = '';

        if (!integrations || integrations.length === 0) {
            list.innerHTML = '<li class="platforms-popup-empty">No integrations listed.</li>';
        } else {
            integrations.forEach(name => {
                const li = document.createElement('li');
                li.className   = 'platforms-popup-item';
                li.textContent = name;
                list.appendChild(li);
            });
        }

        // Zablokuj scroll stránky
        document.body.style.overflow = 'hidden';

        // Zobraz overlay, popup je centrovaný cez CSS
        overlay.style.display = 'flex';

        // Animácia
        requestAnimationFrame(() => popup.classList.add('platforms-popup--open'));
    }

    // ============================================
    // CLOSE
    // ============================================

    function closePopup() {
        popup.classList.remove('platforms-popup--open');
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 180);

        // Obnov scroll stránky
        document.body.style.overflow = '';
    }

    // ============================================
    // EVENT LISTENERS
    // ============================================

    closeB.addEventListener('click', closePopup);

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closePopup();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closePopup();
    });

    // ============================================
    // GLOBAL EXPOSE
    // ============================================

    window.openPlatformsPopup = function (e, btn) {
        e.stopPropagation();

        let integrations = [];
        try {
            integrations = JSON.parse(btn.dataset.integrations || '[]');
        } catch (_) {
            integrations = [];
        }

        const chatbotName = btn.dataset.chatbotName || '';

        openPopup(integrations, chatbotName);
    };

})();