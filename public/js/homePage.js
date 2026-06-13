/**
 * homePage.js — ChatbotScout Homepage Scripts
 * Hero carousel, Popular carousel, Scroll animations
 */

// Mark HTML element so CSS animations only activate when JS is running
document.documentElement.classList.add('hp-js');

document.addEventListener('DOMContentLoaded', function () {

    /* ════════════════════════════════════════════════
       HERO CAROUSEL
    ════════════════════════════════════════════════ */
    (function initHeroCarousel() {
        const viewport  = document.getElementById('hp-hero-carousel');
        if (!viewport) return;

        const cards     = Array.from(viewport.querySelectorAll('.hp-chatbot-card'));
        const dotsWrap  = document.getElementById('hp-carousel-dots');
        const btnPrev   = document.getElementById('hp-carousel-prev');
        const btnNext   = document.getElementById('hp-carousel-next');
        const TOTAL     = cards.length;
        let   current   = Math.floor(TOTAL / 2); // start at center card
        let   autoPlay  = null;
        let   isDragging = false;
        let   dragStarted = false;
        let   dragPointerId = null;
        let   dragStartX = 0;
        let   dragStartY = 0;
        let   dragDeltaX = 0;
        let   suppressNextClick = false;

        const POSITIONS = ['pos-far-left', 'pos-left', 'pos-center', 'pos-right', 'pos-far-right'];
        const DRAG_VISUALS = {
            opacityLoss: 0.05,
            brightnessLoss: 0.05,
            saturationLoss: 0.14,
            scaleLoss: 0.04,
            rotateFactor: 0.026,
            shadowYOffsetLoss: 7,
            shadowBlurLoss: 18,
            shadowOpacityLoss: 0.14,
        };

        /* Save theme class on each card */
        cards.forEach(card => {
            const theme = [...card.classList].find(c => c.startsWith('hp-theme-'));
            card.dataset.theme = theme || 'hp-theme-blue';
        });

        /* Build dots */
        for (let i = 0; i < TOTAL; i++) {
            const dot = document.createElement('button');
            dot.className = 'hp-dot' + (i === current ? ' active' : '');
            dot.setAttribute('aria-label', 'Go to card ' + (i + 1));
            dot.addEventListener('click', () => goTo(i));
            dotsWrap.appendChild(dot);
        }

        /* Determine CSS position class relative to current */
        function getPositionClass(cardIndex) {
            let diff = cardIndex - current;
            if (diff >  Math.floor(TOTAL / 2)) diff -= TOTAL;
            if (diff < -Math.floor(TOTAL / 2)) diff += TOTAL;

            if (diff === 0)  return 'pos-center';
            if (diff === -1) return 'pos-left';
            if (diff ===  1) return 'pos-right';
            if (diff < -1)  return 'pos-far-left';
            return 'pos-far-right';
        }

        function render() {
            cards.forEach((card, i) => {
                const posClass = getPositionClass(i);
                card.style.transform = '';
                card.style.transition = '';
                card.style.opacity = '';
                card.style.filter = '';
                card.style.boxShadow = '';
                card.className = 'hp-chatbot-card ' + card.dataset.theme + ' ' + posClass;
            });

            /* Update dots */
            const dots = dotsWrap.querySelectorAll('.hp-dot');
            dots.forEach((d, i) => d.classList.toggle('active', i === current));
        }

        function goTo(index) {
            current = ((index % TOTAL) + TOTAL) % TOTAL;
            render();
        }

        function getCenterCard() {
            return cards[current] || null;
        }

        function getDragLimit() {
            return Math.min(150, Math.max(85, viewport.clientWidth * 0.28));
        }

        function getSwipeThreshold() {
            return Math.min(85, Math.max(45, viewport.clientWidth * 0.13));
        }

        function clampDrag(delta) {
            const limit = getDragLimit();
            return Math.max(-limit, Math.min(limit, delta));
        }

        function clearDragState() {
            viewport.classList.remove('hp-carousel-viewport--dragging');
            cards.forEach(card => {
                card.classList.remove('hp-chatbot-card--dragging');
                card.style.transition = '';
                card.style.transform = '';
                card.style.opacity = '';
                card.style.filter = '';
                card.style.boxShadow = '';
            });
        }

        function moveCenterCard(delta) {
            const centerCard = getCenterCard();
            if (!centerCard) return;

            const limitedDelta = clampDrag(delta);
            const progress = Math.min(1, Math.abs(limitedDelta) / getDragLimit());
            const opacity = 1 - (progress * DRAG_VISUALS.opacityLoss);
            const brightness = 1 - (progress * DRAG_VISUALS.brightnessLoss);
            const saturation = 1 - (progress * DRAG_VISUALS.saturationLoss);
            const scale = 1 - (progress * DRAG_VISUALS.scaleLoss);
            const shadowY = 20 - (progress * DRAG_VISUALS.shadowYOffsetLoss);
            const shadowBlur = 60 - (progress * DRAG_VISUALS.shadowBlurLoss);
            const shadowOpacity = 0.35 - (progress * DRAG_VISUALS.shadowOpacityLoss);
            const rotate = limitedDelta * DRAG_VISUALS.rotateFactor;

            dragDeltaX = limitedDelta;
            viewport.classList.add('hp-carousel-viewport--dragging');
            centerCard.classList.add('hp-chatbot-card--dragging');
            centerCard.style.transition = 'none';
            centerCard.style.transform = `translateX(${limitedDelta}px) scale(${scale}) rotate(${rotate}deg)`;
            centerCard.style.opacity = opacity.toFixed(3);
            centerCard.style.filter = `brightness(${brightness.toFixed(3)}) saturate(${saturation.toFixed(3)})`;
            centerCard.style.boxShadow = `0 ${shadowY}px ${shadowBlur}px rgba(0,0,0,${shadowOpacity.toFixed(3)})`;
        }

        function finishDrag() {
            if (!isDragging) return;

            const shouldSwipe = Math.abs(dragDeltaX) >= getSwipeThreshold();
            const direction = dragDeltaX < 0 ? 1 : -1;
            const wasDragged = dragStarted;

            isDragging = false;
            dragStarted = false;
            dragPointerId = null;

            clearDragState();

            if (shouldSwipe) {
                goTo(current + direction);
            } else if (wasDragged) {
                render();
            }

            resetAutoPlay();

            if (wasDragged) {
                suppressNextClick = true;
                setTimeout(() => { suppressNextClick = false; }, 0);
            }
        }

        /* Clicking a side card centres it */
        let isAnimating = false;
        cards.forEach((card, i) => {
            card.addEventListener('click', (e) => {
                e.stopPropagation();
                if (suppressNextClick) {
                    e.preventDefault();
                    return;
                }
                if (isAnimating) return;
                const pos = getPositionClass(i);
                if (pos === 'pos-left' || pos === 'pos-right') {
                    isAnimating = true;
                    if (pos === 'pos-left')  goTo(current - 1);
                    if (pos === 'pos-right') goTo(current + 1);
                    resetAutoPlay();
                    setTimeout(() => { isAnimating = false; }, 450);
                }
            });
        });

        btnPrev.addEventListener('click', () => { goTo(current - 1); resetAutoPlay(); });
        btnNext.addEventListener('click', () => { goTo(current + 1); resetAutoPlay(); });

        /* Keyboard navigation */
        document.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft')  { goTo(current - 1); resetAutoPlay(); }
            if (e.key === 'ArrowRight') { goTo(current + 1); resetAutoPlay(); }
        });

        /* Drag / swipe support */
        viewport.addEventListener('pointerdown', e => {
            if (!e.isPrimary || (e.pointerType === 'mouse' && e.button !== 0)) return;
            if (e.target.closest('a, button')) return;

            const card = e.target.closest('.hp-chatbot-card');
            if (!card) return;

            const cardIndex = cards.indexOf(card);
            if (getPositionClass(cardIndex) !== 'pos-center') return;

            isDragging = true;
            dragStarted = false;
            dragPointerId = e.pointerId;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            dragDeltaX = 0;
            clearInterval(autoPlay);
        });

        viewport.addEventListener('pointermove', e => {
            if (!isDragging || e.pointerId !== dragPointerId) return;

            const deltaX = e.clientX - dragStartX;
            const deltaY = e.clientY - dragStartY;
            const absX = Math.abs(deltaX);
            const absY = Math.abs(deltaY);

            if (!dragStarted) {
                if (absX < 6) return;

                if (absY > absX + 4) {
                    isDragging = false;
                    dragPointerId = null;
                    resetAutoPlay();
                    return;
                }

                dragStarted = true;
                viewport.setPointerCapture?.(e.pointerId);
            }

            e.preventDefault();
            moveCenterCard(deltaX);
        });

        viewport.addEventListener('pointerup', e => {
            if (e.pointerId === dragPointerId) {
                finishDrag();
            }
        });

        window.addEventListener('pointerup', e => {
            if (e.pointerId === dragPointerId) {
                finishDrag();
            }
        });

        viewport.addEventListener('pointercancel', e => {
            if (e.pointerId === dragPointerId) {
                clearDragState();
                isDragging = false;
                dragStarted = false;
                dragPointerId = null;
                resetAutoPlay();
            }
        });

        window.addEventListener('pointercancel', e => {
            if (e.pointerId === dragPointerId) {
                clearDragState();
                isDragging = false;
                dragStarted = false;
                dragPointerId = null;
                resetAutoPlay();
            }
        });

        /* Auto-play */
        function startAutoPlay() {
            clearInterval(autoPlay);
            autoPlay = setInterval(() => goTo(current + 1), 4500);
        }

        function resetAutoPlay() {
            clearInterval(autoPlay);
            startAutoPlay();
        }

        const heroWrap = document.querySelector('.hp-carousel-wrap');
        heroWrap.addEventListener('mouseenter', () => clearInterval(autoPlay));
        heroWrap.addEventListener('mouseleave', startAutoPlay);

        render();
        startAutoPlay();
    })();


    /* ════════════════════════════════════════════════
       MOST POPULAR CAROUSEL
    ════════════════════════════════════════════════ */
    (function initPopularCarousel() {
        const track   = document.getElementById('hp-pop-track');
        const btnPrev = document.getElementById('hp-pop-prev');
        const btnNext = document.getElementById('hp-pop-next');
        if (!track || !btnPrev || !btnNext) return;

        const viewport = track.parentElement;
        const CARD_W = 212; // card width (196) + gap (16)
        let offset = 0;
        let resizeFrame = 0;

        function getVisibleStep() {
            const wrapWidth = viewport.clientWidth;
            const visibleCards = Math.max(1, Math.floor(wrapWidth / CARD_W));
            return visibleCards * CARD_W;
        }

        function getMax() {
            return Math.max(0, track.scrollWidth - viewport.clientWidth);
        }

        function snapToCard(raw) {
            return Math.round(raw / CARD_W) * CARD_W;
        }

        function updateButtons() {
            btnPrev.style.opacity = offset <= 0 ? '.35' : '1';
            btnNext.style.opacity = offset >= getMax() ? '.35' : '1';
        }

        function applyOffset() {
            offset = Math.min(Math.max(offset, 0), getMax());
            track.style.transform = `translateX(-${offset}px)`;
            updateButtons();
        }

        function scheduleLayoutUpdate() {
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(applyOffset);
        }

        btnNext.addEventListener('click', () => {
            const target = offset + getVisibleStep();
            offset = target >= getMax() ? getMax() : snapToCard(target);
            track.style.transform = `translateX(-${offset}px)`;
            updateButtons();
        });

        btnPrev.addEventListener('click', () => {
            offset = snapToCard(Math.max(offset - getVisibleStep(), 0));
            track.style.transform = `translateX(-${offset}px)`;
            updateButtons();
        });

        window.addEventListener('resize', scheduleLayoutUpdate);
        window.addEventListener('load', scheduleLayoutUpdate);

        if ('ResizeObserver' in window) {
            const resizeObserver = new ResizeObserver(scheduleLayoutUpdate);
            resizeObserver.observe(viewport);
            resizeObserver.observe(track);
        }

        updateButtons();
    })();


    /* ════════════════════════════════════════════════
       LIKE / HEART TOGGLE
    ════════════════════════════════════════════════ */
    document.querySelectorAll('.hp-pop-heart').forEach(heart => {
        heart.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.toggle('liked');
            this.innerHTML = this.classList.contains('liked')
                ? '<i class="fa-solid fa-heart"></i>'
                : '<i class="fa-regular fa-heart"></i>';
        });
    });


    /* ════════════════════════════════════════════════
       SCROLL ANIMATIONS  (IntersectionObserver)
    ════════════════════════════════════════════════ */
    const scrollObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                /* Don't unobserve bubbles so reverse-scroll keeps them */
            }
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -40px 0px'
    });

    document.querySelectorAll('.hp-fade-up, .hp-bubble, .hp-chat-animate').forEach(el => {
        scrollObserver.observe(el);
    });


    /* ════════════════════════════════════════════════
       FAQ ACCORDION
    ════════════════════════════════════════════════ */
    (function initFaqAccordion() {
        document.querySelectorAll('.hp-faq-item').forEach(item => {
            item.addEventListener('click', () => {
                const isOpen = item.classList.contains('open');
                document.querySelectorAll('.hp-faq-item.open').forEach(el => el.classList.remove('open'));
                if (!isOpen) item.classList.add('open');
            });
        });
    })();

});
