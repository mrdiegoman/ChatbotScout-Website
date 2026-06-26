(function () {
    'use strict';

    const defaults = {
        success: {
            title: 'Saved',
            icon: '✓',
        },
        error: {
            title: 'Action needed',
            icon: '!',
        },
        info: {
            title: 'Notice',
            icon: 'i',
        },
        warning: {
            title: 'Attention',
            icon: '!',
        },
    };

    function hideNotifications() {
        document.querySelectorAll('.notification').forEach(notification => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 180);
        });
    }

    function showNotification(message, type = 'info', options = {}) {
        const normalizedType = defaults[type] ? type : 'info';
        const config = defaults[normalizedType];

        document.querySelectorAll('.notification').forEach(notification => notification.remove());

        const notification = document.createElement('div');
        notification.className = `notification notification-${normalizedType}`;
        notification.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status');
        notification.setAttribute('aria-live', normalizedType === 'error' ? 'assertive' : 'polite');

        const icon = document.createElement('span');
        icon.className = 'notification-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = options.icon || config.icon;

        const content = document.createElement('span');
        content.className = 'notification-content';

        const title = document.createElement('span');
        title.className = 'notification-title';
        title.textContent = options.title || config.title;

        const text = document.createElement('span');
        text.className = 'notification-message';
        text.textContent = String(message || '');

        content.append(title, text);
        notification.append(icon, content);
        document.body.appendChild(notification);

        const duration = Number(options.duration) > 0 ? Number(options.duration) : 3500;

        setTimeout(() => notification.classList.add('show'), 10);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 260);
        }, duration);

        return notification;
    }

    window.hideNotifications = hideNotifications;
    window.showNotification = showNotification;
})();
