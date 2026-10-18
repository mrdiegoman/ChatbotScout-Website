document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.verification-resend-form');
    const button = form?.querySelector('.btn-resend');
    const status = document.getElementById('verification-resend-status');

    if (!form || !button || !status) return;

    const defaultLabel = button.dataset.defaultLabel || 'Resend verification email';
    const serverNow = Number(form.dataset.serverNow) || Math.floor(Date.now() / 1000);
    const serverOffsetMs = (serverNow * 1000) - Date.now();
    let cooldownUntil = Number(form.dataset.cooldownUntil) || 0;
    let resendAvailable = form.dataset.resendAvailable === 'true';
    let countdownInterval = null;

    function serverTimeSeconds() {
        return Math.floor((Date.now() + serverOffsetMs) / 1000);
    }

    function remainingSeconds() {
        return Math.max(0, Math.ceil(cooldownUntil - serverTimeSeconds()));
    }

    function renderCountdown() {
        const remaining = remainingSeconds();

        if (remaining > 0) {
            button.disabled = true;
            button.textContent = `Resend in ${remaining}s`;
            button.classList.add('countdown-active');
            return;
        }

        button.disabled = !resendAvailable;
        button.textContent = defaultLabel;
        button.classList.remove('countdown-active');
        if (resendAvailable) status.textContent = '';

        if (countdownInterval !== null) {
            window.clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    function startCountdown(seconds) {
        cooldownUntil = serverTimeSeconds() + Math.max(0, Number(seconds) || 0);
        renderCountdown();

        if (remainingSeconds() > 0 && countdownInterval === null) {
            countdownInterval = window.setInterval(renderCountdown, 1000);
        }
    }

    if (remainingSeconds() > 0) {
        startCountdown(remainingSeconds());
    } else {
        renderCountdown();
    }

    form.addEventListener('submit', async (event) => {
        if (!window.fetch || button.disabled) return;

        event.preventDefault();
        button.disabled = true;
        button.textContent = 'Sending...';
        status.textContent = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(form),
            });
            const result = await response.json();
            if (window.turnstile) window.turnstile.reset();
            const remaining = Math.max(0, Number(result.remainingSeconds) || 0);

            if (result.verified) resendAvailable = false;

            if (remaining > 0) {
                status.textContent = '';
                startCountdown(remaining);
            } else {
                renderCountdown();
                status.textContent = result.message || '';
            }

            if (typeof window.showNotification === 'function') {
                window.showNotification(
                    result.message || (result.success ? 'Verification email sent.' : 'Unable to resend the email.'),
                    result.success ? 'success' : 'error'
                );
            }
        } catch (error) {
            renderCountdown();
            status.textContent = 'A network error occurred. Please try again.';
            if (typeof window.showNotification === 'function') {
                window.showNotification('A network error occurred. Please try again.', 'error');
            }
        }
    });
});
