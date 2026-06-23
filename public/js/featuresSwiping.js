// Custom feature-card scrollbar for list views in both showcase pages.
// It activates only when content really overflows.
function initializeScrollbars() {
    const containers = document.querySelectorAll(
        '.chatbot-page-main-content-chatbots-list-view-item-scroll-container, ' +
        '.favourites-page-main-content-chatbots-list-view-item-scroll-container'
    );

    containers.forEach((container) => {
        if (container.dataset.scrollbarInitialized === 'true') {
            return;
        }

        const scrollbar = container.parentElement.querySelector(
            '.chatbot-page-main-content-chatbots-list-view-item-scrollbar, ' +
            '.favourites-page-main-content-chatbots-list-view-item-scrollbar'
        );
        const thumb = scrollbar?.querySelector(
            '.chatbot-page-main-content-chatbots-list-view-item-scrollbar-thumb, ' +
            '.favourites-page-main-content-chatbots-list-view-item-scrollbar-thumb'
        );

        if (!scrollbar || !thumb) {
            return;
        }

        container.dataset.scrollbarInitialized = 'true';

        let hasOverflow = false;
        let maxScroll = 0;
        let maxThumbOffset = 0;
        let scrollbarLeft = 0;
        let metricsUpdateFrame = 0;
        let contentPointerId = null;
        let thumbPointerId = null;
        let contentStartX = 0;
        let contentStartScrollLeft = 0;
        let thumbClickOffset = 0;

        const updateThumbPosition = () => {
            const scrollRatio = hasOverflow && maxScroll > 0
                ? container.scrollLeft / maxScroll
                : 0;
            thumb.style.transform = `translate3d(${scrollRatio * maxThumbOffset}px, 0, 0)`;
        };

        const updateMetrics = () => {
            metricsUpdateFrame = 0;

            const clientWidth = container.clientWidth;
            const scrollWidth = container.scrollWidth;
            maxScroll = Math.max(0, scrollWidth - clientWidth);
            hasOverflow = clientWidth > 0 && maxScroll > 1;

            container.classList.toggle('is-scrollable', hasOverflow);
            scrollbar.classList.toggle('is-scrollable', hasOverflow);

            if (hasOverflow) {
                const trackWidth = scrollbar.clientWidth;
                const thumbWidth = Math.min(
                    trackWidth,
                    Math.max(trackWidth * 0.1, (clientWidth / scrollWidth) * trackWidth)
                );

                thumb.style.width = `${thumbWidth}px`;
                maxThumbOffset = trackWidth - thumbWidth;
            } else {
                thumb.style.width = '100%';
                maxThumbOffset = 0;
            }

            updateThumbPosition();
        };

        const scheduleMetricsUpdate = () => {
            if (!metricsUpdateFrame) {
                metricsUpdateFrame = requestAnimationFrame(updateMetrics);
            }
        };

        const moveContentTo = (clientX) => {
            container.scrollLeft = contentStartScrollLeft - (clientX - contentStartX) * 1.25;
        };

        const moveThumbTo = (clientX) => {
            if (!hasOverflow || maxScroll <= 0 || maxThumbOffset <= 0) {
                return;
            }

            const targetLeft = clientX - scrollbarLeft - thumbClickOffset;
            const clampedLeft = Math.max(0, Math.min(targetLeft, maxThumbOffset));
            container.scrollLeft = (clampedLeft / maxThumbOffset) * maxScroll;
        };

        const finishContentDrag = (event) => {
            if (contentPointerId === null || event.pointerId !== contentPointerId) {
                return;
            }

            if (container.hasPointerCapture(contentPointerId)) {
                container.releasePointerCapture(contentPointerId);
            }
            contentPointerId = null;
            container.classList.remove('is-dragging');
        };

        const finishThumbDrag = (event) => {
            if (thumbPointerId === null || event.pointerId !== thumbPointerId) {
                return;
            }

            if (scrollbar.hasPointerCapture(thumbPointerId)) {
                scrollbar.releasePointerCapture(thumbPointerId);
            }
            thumbPointerId = null;
            scrollbar.classList.remove('is-dragging');
        };

        container.addEventListener('pointerdown', (event) => {
            const interactiveElement = event.target.closest('a, button, input, select, textarea, label');
            if (!hasOverflow || event.pointerType !== 'mouse' || event.button !== 0 || interactiveElement) {
                return;
            }

            contentPointerId = event.pointerId;
            contentStartX = event.clientX;
            contentStartScrollLeft = container.scrollLeft;
            container.setPointerCapture(event.pointerId);
            container.classList.add('is-dragging');
            event.preventDefault();
        });

        container.addEventListener('pointermove', (event) => {
            if (event.pointerId !== contentPointerId) {
                return;
            }

            moveContentTo(event.clientX);
            event.preventDefault();
        });
        container.addEventListener('pointerup', finishContentDrag);
        container.addEventListener('pointercancel', finishContentDrag);
        container.addEventListener('lostpointercapture', () => {
            contentPointerId = null;
            container.classList.remove('is-dragging');
        });

        scrollbar.addEventListener('pointerdown', (event) => {
            if (!hasOverflow || event.pointerType !== 'mouse' || event.button !== 0) {
                return;
            }

            const targetIsThumb = thumb.contains(event.target);
            thumbClickOffset = targetIsThumb
                ? event.clientX - thumb.getBoundingClientRect().left
                : thumb.clientWidth / 2;
            scrollbarLeft = scrollbar.getBoundingClientRect().left;
            thumbPointerId = event.pointerId;
            scrollbar.setPointerCapture(event.pointerId);
            scrollbar.classList.add('is-dragging');
            moveThumbTo(event.clientX);
            event.preventDefault();
        });

        scrollbar.addEventListener('pointermove', (event) => {
            if (event.pointerId !== thumbPointerId) {
                return;
            }

            moveThumbTo(event.clientX);
            event.preventDefault();
        });
        scrollbar.addEventListener('pointerup', finishThumbDrag);
        scrollbar.addEventListener('pointercancel', finishThumbDrag);
        scrollbar.addEventListener('lostpointercapture', () => {
            thumbPointerId = null;
            scrollbar.classList.remove('is-dragging');
        });

        // This only writes a compositor-friendly transform, so updating it in the
        // scroll event keeps the thumb exactly under the pointer without a frame of lag.
        container.addEventListener('scroll', updateThumbPosition, { passive: true });
        const resizeObserver = new ResizeObserver(scheduleMetricsUpdate);
        resizeObserver.observe(container);
        resizeObserver.observe(scrollbar);
        scheduleMetricsUpdate();
    });
}

initializeScrollbars();
