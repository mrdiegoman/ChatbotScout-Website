(function () {
    'use strict';

    var lockCount = 0;

    window.lockScroll = function () {
        if (lockCount === 0) {
            var scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
            document.documentElement.style.setProperty(
                '--scrollbar-compensation',
                scrollbarWidth ? scrollbarWidth + 'px' : '0px'
            );
            document.body.style.paddingRight = scrollbarWidth ? scrollbarWidth + 'px' : '';
            document.body.style.overflow = 'hidden';
        }
        lockCount++;
    };

    window.unlockScroll = function () {
        if (lockCount > 0) lockCount--;
        if (lockCount === 0) {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            document.documentElement.style.removeProperty('--scrollbar-compensation');
        }
    };
})();
