document.addEventListener('DOMContentLoaded', () => {
    const avatarBtn = document.getElementById('profileAvatarBtn');
    const dropdown  = document.getElementById('profileDropdown');

    if (!avatarBtn || !dropdown) return;

    function hideNotifications() {
        if (typeof window.hideNotifications === 'function') {
            window.hideNotifications();
        }
    }

    avatarBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('active');
        if (dropdown.classList.contains('active')) {
            hideNotifications();
        }
    });

    // Zatvor pri kliknutí kdekoľvek inde
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });

    // Zatvor pri stlačení Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') dropdown.classList.remove('active');
    });

    // ============================================
    // EMAIL MASKING
    // ============================================

    const emailText   = document.getElementById('dropdown-email-text');
    const emailToggle = document.getElementById('dropdown-email-toggle');
    const emailFull   = document.getElementById('dropdown-email-full');
    if (!emailText || !emailToggle || !emailFull) return;

    const full = emailFull.value;

    function maskEmail(email) {
        const atIndex = email.indexOf('@');
        if (atIndex < 0) return email;
        return email[0] + '***' + email.slice(atIndex);
    }

    let visible = false;

    function renderEmailVisibility() {
        emailText.textContent = visible ? full : maskEmail(full);
        emailToggle.setAttribute('aria-pressed', String(visible));
        emailToggle.setAttribute('aria-label', visible ? 'Hide email' : 'Show email');
    }

    renderEmailVisibility();

    emailToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        visible = !visible;
        renderEmailVisibility();
    });
});
