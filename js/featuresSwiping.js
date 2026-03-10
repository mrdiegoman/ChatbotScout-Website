// Nájdi všetky scrollovacie kontajnery (grid aj list view)
function initializeScrollbars() {
    const containers = document.querySelectorAll('.chatbot-page-main-content-chatbots-list-view-item-scroll-container, .favourites-page-main-content-chatbots-list-view-item-scroll-container'

    );

    containers.forEach(container => {
        // Preskočiť, ak už je inicializovaný
        if (container.dataset.scrollbarInitialized) return;
        container.dataset.scrollbarInitialized = 'true';

        const scrollbar = container.parentElement.querySelector('.chatbot-page-main-content-chatbots-list-view-item-scrollbar, .favourites-page-main-content-chatbots-list-view-item-scrollbar');
        const thumb = scrollbar?.querySelector('.chatbot-page-main-content-chatbots-list-view-item-scrollbar-thumb, .favourites-page-main-content-chatbots-list-view-item-scrollbar-thumb');
        const DRAG_SPEED = 1.25;

        if (!scrollbar || !thumb) return;

        let isDown = false;
        let lastX;
        let isDraggingThumb = false;
        let thumbClickOffset = 0;

        function updateScrollbar() {
            // Počkaj na layout reflow
            requestAnimationFrame(() => {
                const scrollRatio = container.clientWidth / container.scrollWidth;
                const thumbWidth = Math.max(scrollRatio * 100, 10); // Min 10%
                thumb.style.width = thumbWidth + '%';
                
                const maxScroll = container.scrollWidth - container.clientWidth;
                if (maxScroll > 0) {
                    const scrollPercentage = container.scrollLeft / maxScroll;
                    thumb.style.left = (scrollPercentage * (100 - thumbWidth)) + '%';
                    scrollbar.style.opacity = '1'; // Zobraz scrollbar
                } else {
                    scrollbar.style.opacity = '0'; // Skry ak nie je čo scrollovať
                }
            });
        }

        // Posúvanie myšou v container
        container.addEventListener('mousedown', (e) => {
            isDown = true;
            container.style.cursor = 'grabbing';
            document.body.style.userSelect = 'none'; // Zakáž výber textu
            lastX = e.pageX;
        });

        document.addEventListener('mouseup', () => {
            if (isDown) {
                isDown = false;
                container.style.cursor = 'grab';
                document.body.style.userSelect = ''; // Obnov výber textu
            }
            if (isDraggingThumb) {
                isDraggingThumb = false;
                document.body.style.userSelect = ''; // Obnov výber textu
            }
        });

        document.addEventListener('mousemove', (e) => {
            if (isDown) {
                e.preventDefault();
                const delta = e.pageX - lastX;
                lastX = e.pageX;
                container.scrollLeft -= delta * DRAG_SPEED;
            }
            
            if (isDraggingThumb) {
                e.preventDefault();
                const scrollbarRect = scrollbar.getBoundingClientRect();
                const mouseX = e.clientX - scrollbarRect.left;
                const thumbWidth = thumb.clientWidth;
                
                const newThumbLeft = mouseX - thumbClickOffset;
                const maxThumbLeft = scrollbar.clientWidth - thumbWidth;
                const clampedThumbLeft = Math.max(0, Math.min(newThumbLeft, maxThumbLeft));
                
                const scrollPercentage = clampedThumbLeft / maxThumbLeft;
                container.scrollLeft = scrollPercentage * (container.scrollWidth - container.clientWidth);
            }
        });

        container.addEventListener('scroll', updateScrollbar);

        thumb.addEventListener('mousedown', (e) => {
            e.stopPropagation();
            e.preventDefault(); // Zabráň označeniu textu
            isDraggingThumb = true;
            thumbClickOffset = e.clientX - thumb.getBoundingClientRect().left;
            document.body.style.userSelect = 'none'; // Zakáž výber textu
        });

        scrollbar.addEventListener('mousedown', (e) => {
            if (e.target === scrollbar) {
                e.preventDefault(); // Zabráň označeniu textu
                document.body.style.userSelect = 'none'; // Zakáž výber textu
                
                const rect = scrollbar.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const thumbWidth = thumb.clientWidth;
                
                const newThumbLeft = x - (thumbWidth / 2);
                const maxThumbLeft = scrollbar.clientWidth - thumbWidth;
                const clampedThumbLeft = Math.max(0, Math.min(newThumbLeft, maxThumbLeft));
                
                const scrollPercentage = clampedThumbLeft / maxThumbLeft;
                container.scrollLeft = scrollPercentage * (container.scrollWidth - container.clientWidth);
                
                thumbClickOffset = thumbWidth / 2;
                isDraggingThumb = true;
            }
        });

        // Inicializuj scrollbar s oneskorením
        setTimeout(updateScrollbar, 0);
        window.addEventListener('resize', updateScrollbar);
        
        // Observer pre zmeny obsahu
        const resizeObserver = new ResizeObserver(updateScrollbar);
        resizeObserver.observe(container);
    });
}

// Inicializuj pri načítaní
initializeScrollbars();

// Sleduj DOM zmeny (prepínanie layoutu)
const observer = new MutationObserver(() => {
    initializeScrollbars();
});

observer.observe(document.body, {
    childList: true,
    subtree: true
});