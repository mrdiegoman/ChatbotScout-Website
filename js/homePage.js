/**
 * homePage.js — ChatbotHub Homepage Scripts
 * Hero carousel, Popular carousel, Scroll animations
 */

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

        const POSITIONS = ['pos-far-left', 'pos-left', 'pos-center', 'pos-right', 'pos-far-right'];

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

        /* Clicking a side card centres it */
        cards.forEach((card, i) => {
            card.addEventListener('click', () => {
                const pos = getPositionClass(i);
                if (pos === 'pos-left')  goTo(current - 1);
                if (pos === 'pos-right') goTo(current + 1);
            });
        });

        btnPrev.addEventListener('click', () => { goTo(current - 1); resetAutoPlay(); });
        btnNext.addEventListener('click', () => { goTo(current + 1); resetAutoPlay(); });

        /* Keyboard navigation */
        document.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft')  { goTo(current - 1); resetAutoPlay(); }
            if (e.key === 'ArrowRight') { goTo(current + 1); resetAutoPlay(); }
        });

        /* Touch / swipe support */
        let touchStartX = 0;
        viewport.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
        viewport.addEventListener('touchend', e => {
            const diff = touchStartX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) {
                diff > 0 ? goTo(current + 1) : goTo(current - 1);
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

        const CARD_W  = 212; // card width (196) + gap (16)
        const STEP    = CARD_W * 2;
        let   offset  = 0;

        function getMax() {
            return Math.max(0, track.scrollWidth - track.parentElement.clientWidth);
        }

        function updateButtons() {
            btnPrev.style.opacity = offset <= 0 ? '.35' : '1';
            btnNext.style.opacity = offset >= getMax() ? '.35' : '1';
        }

        btnNext.addEventListener('click', () => {
            offset = Math.min(offset + STEP, getMax());
            track.style.transform = `translateX(-${offset}px)`;
            updateButtons();
        });

        btnPrev.addEventListener('click', () => {
            offset = Math.max(offset - STEP, 0);
            track.style.transform = `translateX(-${offset}px)`;
            updateButtons();
        });

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

    document.querySelectorAll('.hp-fade-up, .hp-bubble').forEach(el => {
        scrollObserver.observe(el);
    });

});