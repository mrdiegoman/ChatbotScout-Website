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
        let   isAnimating = false;

        const POSITIONS = ['pos-far-left', 'pos-left', 'pos-center', 'pos-right', 'pos-far-right'];
        const CAROUSEL_TRANSITION_MS = 520;
        const POSITION_STYLES = {
            'pos-center': {
                transform: 'translateX(0) scale(1)',
                opacity: '',
                filter: '',
                boxShadow: '',
            },
            'pos-left': {
                transform: 'translateX(-200px) scale(.82) rotateY(8deg)',
                opacity: '.7',
                filter: 'blur(.3px)',
                boxShadow: '',
            },
            'pos-right': {
                transform: 'translateX(200px) scale(.82) rotateY(-8deg)',
                opacity: '.7',
                filter: 'blur(.3px)',
                boxShadow: '',
            },
            'pos-far-left': {
                transform: 'translateX(-340px) scale(.68)',
                opacity: '0',
                filter: '',
                boxShadow: '',
            },
            'pos-far-right': {
                transform: 'translateX(340px) scale(.68)',
                opacity: '0',
                filter: '',
                boxShadow: '',
            },
        };
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
            dot.addEventListener('click', () => navigateTo(i, { resetTimer: true }));
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

        function captureCardStyles() {
            return cards.map((card, i) => {
                if (card.classList.contains('hp-chatbot-card--dragging')) {
                    return {
                        transform: card.style.transform || POSITION_STYLES['pos-center'].transform,
                        opacity: card.style.opacity || POSITION_STYLES['pos-center'].opacity,
                        filter: card.style.filter || POSITION_STYLES['pos-center'].filter,
                        boxShadow: card.style.boxShadow || POSITION_STYLES['pos-center'].boxShadow,
                    };
                }

                const posClass = getPositionClass(i);
                return getPositionSnapshot(posClass);
            });
        }

        function getPositionSnapshot(posClass) {
            const base = POSITION_STYLES[posClass] || POSITION_STYLES['pos-far-right'];
            const snapshot = { ...base };

            if (
                window.matchMedia('(max-width: 600px)').matches &&
                (posClass === 'pos-left' || posClass === 'pos-right')
            ) {
                snapshot.opacity = '0';
            }

            return snapshot;
        }

        function clearInlineMotionStyles(card) {
            card.style.transform = '';
            card.style.transition = '';
            card.style.opacity = '';
            card.style.filter = '';
            card.style.boxShadow = '';
        }

        function releaseInlineMotionStyles(card) {
            card.style.transition = '';
            card.style.transform = '';
            card.style.opacity = '';
            card.style.filter = '';
            card.style.boxShadow = '';
        }

        function updateDots() {
            const dots = dotsWrap.querySelectorAll('.hp-dot');
            dots.forEach((d, i) => d.classList.toggle('active', i === current));
        }

        function render(options = {}) {
            const snapshots = options.snapshots || null;

            if (snapshots) {
                cards.forEach((card, i) => {
                    const posClass = getPositionClass(i);
                    const snapshot = snapshots[i];

                    card.style.transition = 'none';
                    card.style.transform = snapshot.transform;
                    card.style.opacity = snapshot.opacity;
                    card.style.filter = snapshot.filter;
                    card.style.boxShadow = snapshot.boxShadow;
                    card.className = 'hp-chatbot-card ' + card.dataset.theme + ' ' + posClass;
                });

                updateDots();
                void viewport.offsetWidth;

                requestAnimationFrame(() => {
                    cards.forEach(releaseInlineMotionStyles);
                });

                return;
            }

            cards.forEach((card, i) => {
                const posClass = getPositionClass(i);
                clearInlineMotionStyles(card);
                card.className = 'hp-chatbot-card ' + card.dataset.theme + ' ' + posClass;
            });

            updateDots();
        }

        function goTo(index, options = {}) {
            const next = ((index % TOTAL) + TOTAL) % TOTAL;
            if (next === current && !options.snapshots) return;

            const snapshots = options.snapshots || (options.animate ? captureCardStyles() : null);
            current = next;
            render({ snapshots });
        }

        function navigateTo(index, options = {}) {
            const {
                resetTimer = false,
                force = false,
                snapshots = null,
                animate = false,
            } = options;
            if (!force && (isAnimating || isDragging)) return;

            isAnimating = true;
            viewport.classList.add('hp-carousel-viewport--animating');
            goTo(index, { animate, snapshots });

            if (resetTimer) {
                resetAutoPlay();
            }

            setTimeout(() => {
                isAnimating = false;
                viewport.classList.remove('hp-carousel-viewport--animating');
            }, CAROUSEL_TRANSITION_MS);
        }

        function stepCarousel(direction, options = {}) {
            navigateTo(current + direction, options);
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
                clearInlineMotionStyles(card);
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
            const snapshots = wasDragged ? captureCardStyles() : null;

            isDragging = false;
            dragStarted = false;
            dragPointerId = null;
            viewport.classList.remove('hp-carousel-viewport--dragging');

            if (shouldSwipe) {
                stepCarousel(direction, { resetTimer: true, force: true, snapshots });
            } else if (wasDragged) {
                render({ snapshots });
                resetAutoPlay();
            } else {
                resetAutoPlay();
            }

            if (wasDragged) {
                suppressNextClick = true;
                setTimeout(() => { suppressNextClick = false; }, 0);
            }
        }

        /* Clicking a side card centres it */
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
                    if (pos === 'pos-left')  stepCarousel(-1, { resetTimer: true });
                    if (pos === 'pos-right') stepCarousel(1, { resetTimer: true });
                }
            });
        });

        btnPrev.addEventListener('click', () => stepCarousel(-1, { resetTimer: true }));
        btnNext.addEventListener('click', () => stepCarousel(1, { resetTimer: true }));

        /* Keyboard navigation */
        document.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft')  stepCarousel(-1, { resetTimer: true });
            if (e.key === 'ArrowRight') stepCarousel(1, { resetTimer: true });
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
            autoPlay = setInterval(() => stepCarousel(1), 4500);
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
        const carousel = viewport.parentElement;
        let offset = 0;
        let resizeFrame = 0;
        let isDragging = false;
        let dragStarted = false;
        let dragPointerId = null;
        let dragStartX = 0;
        let dragStartY = 0;
        let dragStartOffset = 0;
        let suppressCardClick = false;

        function getViewportPadding() {
            const styles = window.getComputedStyle(viewport);
            return {
                left: Number.parseFloat(styles.paddingLeft || '0') || 0,
                right: Number.parseFloat(styles.paddingRight || '0') || 0,
            };
        }

        function getCards() {
            return Array.from(track.querySelectorAll('.hp-pop-card'));
        }

        function getCardStep() {
            const firstCard = track.querySelector('.hp-pop-card');
            if (!firstCard) return 0;

            const trackStyles = window.getComputedStyle(track);
            const gap = Number.parseFloat(trackStyles.columnGap || trackStyles.gap || '0') || 0;
            return firstCard.getBoundingClientRect().width + gap;
        }

        function getVisibleStep() {
            const padding = getViewportPadding();
            const wrapWidth = viewport.clientWidth - padding.left - padding.right;
            const cardStep = getCardStep();
            if (!cardStep) return wrapWidth;

            const visibleCards = Math.max(1, Math.floor(wrapWidth / cardStep));
            return visibleCards * cardStep;
        }

        function getMax() {
            const cards = getCards();
            const lastCard = cards[cards.length - 1];
            if (!lastCard) return 0;

            const trackRect = track.getBoundingClientRect();
            const lastRect = lastCard.getBoundingClientRect();
            const padding = getViewportPadding();
            const lastCardRightFromTrackStart = lastRect.right - trackRect.left;
            const visibleWidth = viewport.clientWidth - padding.left - padding.right;

            return Math.max(0, lastCardRightFromTrackStart - visibleWidth);
        }

        function snapToCard(raw) {
            const cardStep = getCardStep();
            if (!cardStep) return raw;

            return Math.round(raw / cardStep) * cardStep;
        }

        function setButtonHidden(button, shouldHide) {
            button.classList.toggle('is-hidden', shouldHide);
            button.setAttribute('aria-hidden', shouldHide ? 'true' : 'false');
            button.tabIndex = shouldHide ? -1 : 0;
        }

        function updateButtons() {
            const max = getMax();
            const canScroll = max > 1;
            const atStart = offset <= 1;
            const atEnd = max - offset <= 1;

            setButtonHidden(btnPrev, !canScroll || atStart);
            setButtonHidden(btnNext, !canScroll || atEnd);

            carousel.classList.toggle('is-overflowing', canScroll);
            carousel.classList.toggle('has-left-overflow', canScroll && !atStart);
            carousel.classList.toggle('has-right-overflow', canScroll && !atEnd);
        }

        function applyOffset() {
            offset = Math.min(Math.max(offset, 0), getMax());
            track.style.transform = `translateX(-${offset}px)`;
            updateButtons();
        }

        function setOffset(nextOffset) {
            offset = Math.min(Math.max(nextOffset, 0), getMax());
            track.style.transform = `translateX(-${offset}px)`;
            updateButtons();
        }

        function scheduleLayoutUpdate() {
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(applyOffset);
        }

        function clearDragState() {
            isDragging = false;
            dragStarted = false;
            dragPointerId = null;
            carousel.classList.remove('is-dragging');
        }

        function finishDrag() {
            if (!isDragging) return;

            const shouldSuppressClick = dragStarted;
            const finalOffset = offset;
            clearDragState();

            if (shouldSuppressClick) {
                setOffset(finalOffset);
                suppressCardClick = true;
                setTimeout(() => { suppressCardClick = false; }, 350);
            }
        }

        btnNext.addEventListener('click', () => {
            const target = offset + getVisibleStep();
            setOffset(target >= getMax() ? getMax() : snapToCard(target));
        });

        btnPrev.addEventListener('click', () => {
            setOffset(snapToCard(Math.max(offset - getVisibleStep(), 0)));
        });

        viewport.addEventListener('pointerdown', (event) => {
            if (!event.isPrimary || (event.pointerType === 'mouse' && event.button !== 0)) return;
            if (event.target.closest('button')) return;
            if (getMax() <= 1) return;

            isDragging = true;
            dragStarted = false;
            dragPointerId = event.pointerId;
            dragStartX = event.clientX;
            dragStartY = event.clientY;
            dragStartOffset = offset;
        });

        viewport.addEventListener('pointermove', (event) => {
            if (!isDragging || event.pointerId !== dragPointerId) return;

            const deltaX = event.clientX - dragStartX;
            const deltaY = event.clientY - dragStartY;
            const absX = Math.abs(deltaX);
            const absY = Math.abs(deltaY);

            if (!dragStarted) {
                if (absX < 6) return;

                if (absY > absX + 4) {
                    clearDragState();
                    return;
                }

                dragStarted = true;
                carousel.classList.add('is-dragging');
                viewport.setPointerCapture?.(event.pointerId);
            }

            event.preventDefault();
            setOffset(dragStartOffset - deltaX);
        });

        viewport.addEventListener('pointerup', (event) => {
            if (event.pointerId === dragPointerId) {
                finishDrag();
            }
        });

        viewport.addEventListener('pointercancel', (event) => {
            if (event.pointerId === dragPointerId) {
                clearDragState();
            }
        });

        window.addEventListener('pointerup', (event) => {
            if (event.pointerId === dragPointerId) {
                finishDrag();
            }
        });

        track.addEventListener('click', (event) => {
            if (!suppressCardClick) return;

            event.preventDefault();
            event.stopPropagation();
            suppressCardClick = false;
        }, true);

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
