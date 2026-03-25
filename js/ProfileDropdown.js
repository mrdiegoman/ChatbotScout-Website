document.addEventListener('DOMContentLoaded', () => {
    const avatarBtn = document.getElementById('profileAvatarBtn');
    const dropdown  = document.getElementById('profileDropdown');

    if (!avatarBtn || !dropdown) return;

    avatarBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('active');
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
    const iconShow    = document.getElementById('dropdown-email-icon-show');
    const iconHide    = document.getElementById('dropdown-email-icon-hide');

    if (!emailText || !emailToggle || !emailFull) return;

    const full = emailFull.value;

    function maskEmail(email) {
        const atIndex = email.indexOf('@');
        if (atIndex < 0) return email;
        return email[0] + '***' + email.slice(atIndex);
    }

    let visible = false;
    emailText.textContent = maskEmail(full);

    emailToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        visible = !visible;
        emailText.textContent = visible ? full : maskEmail(full);
        iconShow.style.display = visible ? 'none'  : '';
        iconHide.style.display = visible ? ''      : 'none';
    });
});