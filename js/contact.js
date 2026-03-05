// ============================================
// NOTIFICATION SYSTEM (same as profile.js)
// ============================================

function showNotification(message, type = 'info') {
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();

    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add('show'), 10);

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Inject notification styles
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        opacity: 0;
        transform: translateX(100px);
        transition: all 0.3s ease;
        max-width: 400px;
    }
    .notification.show {
        opacity: 1;
        transform: translateX(0);
    }
    .notification-success {
        border-left: 4px solid #4CAF50;
        color: #2E7D32;
    }
    .notification-error {
        border-left: 4px solid #EF233C;
        color: #C62828;
    }
    .notification-info {
        border-left: 4px solid #2196F3;
        color: #1976D2;
    }
    .input-error {
        border-color: #EF233C !important;
    }
    .dropdown-wrapper.input-error {
        border-color: #EF233C !important;
    }
`;
document.head.appendChild(notificationStyles);

// ============================================
// SHOW FLASH MESSAGES FROM PHP (PRG pattern)
// ============================================

document.addEventListener('DOMContentLoaded', () => {

    if (window._contactSuccess) {
        showNotification(window._contactSuccess, 'success');
    }

    if (window._contactError) {
        showNotification(window._contactError, 'error');
    }

    if (window._contactErrors && window._contactErrors.length > 0) {
        showNotification(window._contactErrors[0], 'error');
    }

    // ============================================
    // DROPDOWN — sync selected value to hidden input
    // ============================================

    const dropdownWrapper  = document.getElementById('subjectDropdown');
    const subjectSelected  = document.getElementById('subjectSelected');
    const subjectHidden    = document.getElementById('subjectValue');
    const options          = document.querySelectorAll('#subjectDropdown .dropdown-option');

    if (dropdownWrapper) {
        // Open / close
        dropdownWrapper.addEventListener('click', (e) => {
            if (!e.target.classList.contains('dropdown-option')) {
                dropdownWrapper.classList.toggle('active');
            }
        });

        // Select option
        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();

                options.forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');

                subjectSelected.textContent = option.textContent.trim();
                subjectHidden.value         = option.getAttribute('data-value');

                dropdownWrapper.classList.remove('active');
                dropdownWrapper.classList.remove('input-error');
            });
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!dropdownWrapper.contains(e.target)) {
                dropdownWrapper.classList.remove('active');
            }
        });

        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') dropdownWrapper.classList.remove('active');
        });
    }

    // ============================================
    // CLIENT-SIDE VALIDATION
    // ============================================

    const form      = document.getElementById('contactForm');
    const firstName = document.getElementById('firstName');
    const lastName  = document.getElementById('lastName');
    const email     = document.getElementById('email');
    const message   = document.getElementById('message');

    if (!form) return;

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        clearErrors();

        const errors = [];

        // First name
        const fnVal = firstName.value.trim();
        if (!fnVal || fnVal.length < 1) {
            errors.push('First name is required.');
            markError(firstName);
        } else if (fnVal.length > 50) {
            errors.push('First name cannot exceed 50 characters.');
            markError(firstName);
        }

        // Last name
        const lnVal = lastName.value.trim();
        if (!lnVal || lnVal.length < 1) {
            errors.push('Last name is required.');
            markError(lastName);
        } else if (lnVal.length > 50) {
            errors.push('Last name cannot exceed 50 characters.');
            markError(lastName);
        }

        // Email
        const emailVal = email.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailVal) {
            errors.push('Email address is required.');
            markError(email);
        } else if (!emailRegex.test(emailVal)) {
            errors.push('Please enter a valid email address.');
            markError(email);
        }

        // Subject
        if (!subjectHidden.value) {
            errors.push('Please select a subject topic.');
            dropdownWrapper.classList.add('input-error');
        }

        // Message
        const msgVal = message.value.trim();
        if (!msgVal || msgVal.length < 10) {
            errors.push('Message must be at least 10 characters.');
            markError(message);
        } else if (msgVal.length > 400) {
            errors.push('Message cannot exceed 400 characters.');
            markError(message);
        }

        if (errors.length > 0) {
            showNotification(errors[0], 'error');
            return;
        }

        form.submit();
    });

    function markError(input) {
        input.classList.add('input-error');
        input.addEventListener('input', () => input.classList.remove('input-error'), { once: true });
    }

    function clearErrors() {
        [firstName, lastName, email, message].forEach(el => el.classList.remove('input-error'));
        if (dropdownWrapper) dropdownWrapper.classList.remove('input-error');
    }
});