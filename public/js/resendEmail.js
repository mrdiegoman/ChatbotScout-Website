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
            showNotification('✅ Verification email sent successfully!', 'success');
            
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

// ============================================
// NOTIFICATION SYSTEM
// ============================================

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add to body
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Remove after 4s
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 4000);
}

// ============================================
// NOTIFICATION STYLES (inject into page)
// ============================================

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
        font-family: outfit-semibold, sans-serif;
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
    
    /* Button countdown state */
    .btn-resend.countdown-active {
        opacity: 0.6;
        cursor: not-allowed;
    }
`;
document.head.appendChild(notificationStyles);
