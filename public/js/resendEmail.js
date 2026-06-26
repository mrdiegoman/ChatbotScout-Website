/**
 * Resend Verification Email with 60-second countdown timer
 * 
 * Features:
 * - LocalStorage persistent countdown (survives page refresh)
 * - Backend timestamp validation
 * - Visual countdown in button
 */

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    const resendButton = document.querySelector('.btn-resend');
    
    if (resendButton) {
        const email = resendButton.dataset.email;
        
        // Check if there's an existing countdown
        checkExistingCountdown(resendButton, email);
    }
});

// ============================================
// CHECK EXISTING COUNTDOWN (from LocalStorage)
// ============================================

function checkExistingCountdown(button, email) {
    const storageKey = `resend_timer_${email}`;
    const lastSentTime = localStorage.getItem(storageKey);
    
    if (lastSentTime) {
        const elapsedSeconds = Math.floor((Date.now() - parseInt(lastSentTime)) / 1000);
        const remainingSeconds = 60 - elapsedSeconds;
        
        if (remainingSeconds > 0) {
            // Countdown still active
            startCountdown(button, email, remainingSeconds);
        } else {
            // Countdown expired, clean up
            localStorage.removeItem(storageKey);
            enableButton(button);
        }
    } else {
        // No countdown, button enabled
        enableButton(button);
    }
}

// ============================================
// RESEND EMAIL FUNCTION
// ============================================

async function resendEmail() {
    const button = document.querySelector('.btn-resend');
    const email = button.dataset.email;
    
    if (button.disabled) return; // Prevent spam clicking
    
    // Disable button immediately
    button.disabled = true;
    button.textContent = 'Sending...';
    
    try {
        const response = await fetch('app/profileActions.php?action=resend_verification', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // ✅ Success - start countdown
            showNotification('Verification email sent successfully!', 'success');
            
            // Save timestamp to LocalStorage
            const storageKey = `resend_timer_${email}`;
            localStorage.setItem(storageKey, Date.now().toString());
            
            // Start 60-second countdown
            startCountdown(button, email, 60);
            
        } else {
            // ❌ Error from backend
            showNotification(result.message || 'Failed to send email', 'error');
            
            // Check if it's a timer error (backend returned remainingSeconds)
            if (result.remainingSeconds) {
                // Backend says we need to wait - start countdown with remaining time
                const storageKey = `resend_timer_${email}`;
                const backtrackTime = Date.now() - ((60 - result.remainingSeconds) * 1000);
                localStorage.setItem(storageKey, backtrackTime.toString());
                startCountdown(button, email, result.remainingSeconds);
            } else {
                // Other error - re-enable button
                enableButton(button);
            }
        }
        
    } catch (error) {
        console.error('Resend error:', error);
        showNotification('Network error. Please try again.', 'error');
        enableButton(button);
    }
}

// ============================================
// COUNTDOWN TIMER
// ============================================

function startCountdown(button, email, seconds) {
    let remaining = seconds;
    
    // Update button immediately
    updateButtonCountdown(button, remaining);
    
    // Create interval
    const interval = setInterval(() => {
        remaining--;
        
        if (remaining > 0) {
            updateButtonCountdown(button, remaining);
        } else {
            // Countdown finished
            clearInterval(interval);
            
            // Clean up LocalStorage
            const storageKey = `resend_timer_${email}`;
            localStorage.removeItem(storageKey);
            
            // Re-enable button
            enableButton(button);
            
            showNotification('You can now resend the email', 'info');
        }
    }, 1000);
}

// ============================================
// BUTTON STATE MANAGEMENT
// ============================================

function updateButtonCountdown(button, seconds) {
    button.disabled = true;
    button.textContent = `Resend in ${seconds}s`;
    button.classList.add('countdown-active');
}

function enableButton(button) {
    button.disabled = false;
    button.textContent = 'Resend Verification Email';
    button.classList.remove('countdown-active');
}
