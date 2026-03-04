/**
 * Profile Page JavaScript
 * 
 * Handles tab switching, inline editing, modals, AJAX requests
 */

// ============================================
// TAB SWITCHING
// ============================================

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        
        // Update URL bez page reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);
        
        // Update active states
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        
        btn.classList.add('active');
        document.getElementById(tab + '-tab').classList.add('active');
    });
});

// ============================================
// AVATAR UPLOAD
// ============================================

const avatarUpload = document.getElementById('avatar-upload');
const currentAvatar = document.getElementById('current-avatar');

if (avatarUpload) {
    avatarUpload.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validácia
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('File is too large. Maximum size is 5MB.');
            return;
        }
        
        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            alert('Invalid file type. Please use JPG or PNG.');
            return;
        }
        
        // Ulož starú URL
        const oldAvatarUrl = currentAvatar.src;
        
        // Preview
        const reader = new FileReader();
        reader.onload = (e) => {
            // ✅ Preview pre VŠETKY avatary
            document.querySelectorAll('.user-avatar-img').forEach(img => {
                img.src = e.target.result;
            });
        };
        reader.readAsDataURL(file);
        
        // Upload
        const formData = new FormData();
        formData.append('avatar', file);
        
        try {
            const response = await fetch('../app/profileActions.php?action=upload_avatar', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Profile picture updated successfully!', 'success');
                
                // ✅ Update VŠETKY avatary
                document.querySelectorAll('.user-avatar-img').forEach(img => {
                    img.src = '../' + result.url;
                });
            } else {
                showNotification(result.message || 'Upload failed', 'error');
                
                // ✅ Vráť starú pre VŠETKY
                document.querySelectorAll('.user-avatar-img').forEach(img => {
                    img.src = '../' + oldAvatarUrl;
                });
            }
        } catch (error) {
            showNotification('Upload failed. Please try again.', 'error');
            console.error('Upload error:', error);
            
            // ✅ Vráť starú pre VŠETKY
            document.querySelectorAll('.user-avatar-img').forEach(img => {
                img.src = oldAvatarUrl;
            });
        }
    });
}

// ============================================
// INLINE NAME EDITING
// ============================================

const editNameBtn = document.getElementById('edit-name-btn');
const cancelNameBtn = document.getElementById('cancel-name-btn');
const saveNameBtn = document.getElementById('save-name-btn');
const nameField = document.getElementById('name-field');

// ✅ A) LIVE KAPITALIZÁCIA
const firstNameInput = document.getElementById('first-name-input');
const lastNameInput = document.getElementById('last-name-input');

function capitalizeInput(input) {
    if (!input) return;
    
    input.addEventListener('input', function(e) {
        const start = this.selectionStart;
        const end = this.selectionEnd;
        
        // Kapitalizuj každé slovo
        this.value = this.value
            .toLowerCase()
            .split(' ')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
        
        this.setSelectionRange(start, end);
    });
}

capitalizeInput(firstNameInput);
capitalizeInput(lastNameInput);

// Edit button - zobraz form
if (editNameBtn) {
    editNameBtn.addEventListener('click', () => {
        nameField.querySelector('.field-display').style.display = 'none';
        nameField.querySelector('.field-edit').style.display = 'block';
        document.getElementById('first-name-input').focus();
    });
}

// Cancel button - skry form
if (cancelNameBtn) {
    cancelNameBtn.addEventListener('click', () => {
        nameField.querySelector('.field-display').style.display = 'flex';
        nameField.querySelector('.field-edit').style.display = 'none';
    });
}

// ✅ B) SAVE CALLBACK (upravený)
if (saveNameBtn) {
    saveNameBtn.addEventListener('click', async () => {
        const firstName = document.getElementById('first-name-input').value.trim();
        const lastName = document.getElementById('last-name-input').value.trim();
        
        // ✅ NOVÉ: Frontend validácia
        if (!firstName || !lastName) {
            showNotification('Both first and last name are required', 'error');
            return;
        }
        
        // if (firstName.length < 2 || lastName.length < 2) {
        //     showNotification('Names must be at least 2 characters long', 'error');
        //     return;
        // }
        
        if (firstName.length > 50 || lastName.length > 50) {
            showNotification('Names cannot exceed 50 characters', 'error');
            return;
        }
        
        try {
            const response = await fetch('../app/profileActions.php?action=update_name', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ firstName, lastName })
            });
            
            const result = await response.json();
            
            if (result.success) {
                const capitalizedFirst = result.firstName || firstName;
                const capitalizedLast = result.lastName || lastName;
                
                document.getElementById('name-display').textContent = `${capitalizedFirst} ${capitalizedLast}`;
                nameField.querySelector('.field-display').style.display = 'flex';
                nameField.querySelector('.field-edit').style.display = 'none';
                showNotification('Name updated successfully!', 'success');
            } else {
                showNotification(result.message || 'Update failed', 'error');
            }
        } catch (error) {
            showNotification('Update failed. Please try again.', 'error');
            console.error('Update error:', error);
        }
    });
}

// ============================================
// CHANGE PASSWORD
// ============================================

const changePasswordBtn = document.getElementById('change-password-btn');
const cancelPasswordBtn = document.getElementById('cancel-password-btn');
const savePasswordBtn = document.getElementById('save-password-btn');
const passwordForm = document.getElementById('password-form');

if (changePasswordBtn) {
    changePasswordBtn.addEventListener('click', () => {
        changePasswordBtn.style.display = 'none';
        passwordForm.style.display = 'block';
        document.getElementById('current-password').focus();
    });
}

if (cancelPasswordBtn) {
    cancelPasswordBtn.addEventListener('click', () => {
        changePasswordBtn.style.display = 'block';
        passwordForm.style.display = 'none';
        // Clear inputs
        document.getElementById('current-password').value = '';
        document.getElementById('new-password').value = '';
        document.getElementById('confirm-password').value = '';
    });
}

if (savePasswordBtn) {
    savePasswordBtn.addEventListener('click', async () => {
        const currentPassword = document.getElementById('current-password').value;
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        
        // Validácia
        if (!currentPassword || !newPassword || !confirmPassword) {
            showNotification('All password fields are required', 'error');
            return;
        }
        
        if (newPassword.length < 8) {
            showNotification('New password must be at least 8 characters', 'error');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showNotification('New passwords do not match', 'error');
            return;
        }
        
        try {
            const response = await fetch('../app/profileActions.php?action=change_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ currentPassword, newPassword })
            });
            
            const result = await response.json();
            
            if (result.success) {
                changePasswordBtn.style.display = 'block';
                passwordForm.style.display = 'none';
                // Clear inputs
                document.getElementById('current-password').value = '';
                document.getElementById('new-password').value = '';
                document.getElementById('confirm-password').value = '';
                showNotification('Password updated successfully!', 'success');
            } else {
                showNotification(result.message || 'Password update failed', 'error');
            }
        } catch (error) {
            showNotification('Update failed. Please try again.', 'error');
            console.error('Update error:', error);
        }
    });
}

// ============================================
// MARKETING EMAILS TOGGLE
// ============================================

const marketingToggle = document.getElementById('marketing-emails-toggle');

if (marketingToggle) {
    marketingToggle.addEventListener('change', async (e) => {
        const enabled = e.target.checked;
        
        try {
            const response = await fetch('../app/profileActions.php?action=update_marketing_emails', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification(
                    enabled ? 'Marketing emails enabled' : 'Marketing emails disabled', 
                    'success'
                );
            } else {
                // Revert toggle
                e.target.checked = !enabled;
                showNotification(result.message || 'Update failed', 'error');
            }
        } catch (error) {
            // Revert toggle
            e.target.checked = !enabled;
            showNotification('Update failed. Please try again.', 'error');
            console.error('Update error:', error);
        }
    });
}

// ============================================
// BILLING FILTERS
// ============================================

const dateFilter = document.getElementById('date-filter');
const statusFilter = document.getElementById('status-filter');
const methodFilter = document.getElementById('method-filter');
const resetFiltersBtn = document.getElementById('reset-filters');

function filterPayments() {
    const dateValue = dateFilter.value;
    const statusValue = statusFilter.value;
    const methodValue = methodFilter.value;
    
    const rows = document.querySelectorAll('#payments-tbody tr[data-date]');
    
    rows.forEach(row => {
        let show = true;
        
        // Date filter
        if (dateValue !== 'all') {
            const rowDate = new Date(row.dataset.date);
            const now = new Date();
            
            if (dateValue === 'this-month') {
                show = show && rowDate.getMonth() === now.getMonth() && rowDate.getFullYear() === now.getFullYear();
            } else if (dateValue === 'last-month') {
                const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1);
                show = show && rowDate.getMonth() === lastMonth.getMonth() && rowDate.getFullYear() === lastMonth.getFullYear();
            } else if (dateValue === 'this-year') {
                show = show && rowDate.getFullYear() === now.getFullYear();
            }
        }
        
        // Status filter
        if (statusValue !== 'all') {
            show = show && row.dataset.status === statusValue;
        }
        
        // Method filter
        if (methodValue !== 'all') {
            show = show && row.dataset.method === methodValue;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

if (dateFilter) dateFilter.addEventListener('change', filterPayments);
if (statusFilter) statusFilter.addEventListener('change', filterPayments);
if (methodFilter) methodFilter.addEventListener('change', filterPayments);

if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener('click', () => {
        dateFilter.value = 'all';
        statusFilter.value = 'all';
        methodFilter.value = 'all';
        filterPayments();
    });
}

// ============================================
// MODALS
// ============================================

// Helper: Open modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Helper: Close modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Close modal on X button or outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.querySelector('.modal-close')?.addEventListener('click', () => {
        closeModal(modal.id);
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal(modal.id);
        }
    });
});

// Close modals with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            if (modal.style.display === 'flex') {
                closeModal(modal.id);
            }
        });
    }
});

// ============================================
// MANAGE SUBSCRIPTION MODAL
// ============================================

const manageSubBtn = document.getElementById('manage-subscription-btn');
if (manageSubBtn) {
    manageSubBtn.addEventListener('click', () => {
        openModal('manage-subscription-modal');
    });
}

// Change Plan
const changePlanBtn = document.getElementById('change-plan-btn');
if (changePlanBtn) {
    changePlanBtn.addEventListener('click', () => {
        // Redirect to pricing/plans page
        window.location.href = 'pricing.php?action=change';
    });
}

// Update Payment Method (Stripe Customer Portal)
const updatePaymentBtn = document.getElementById('update-payment-btn');
if (updatePaymentBtn) {
    updatePaymentBtn.addEventListener('click', async () => {
        try {
            const response = await fetch('../app/profileActions.php?action=stripe_customer_portal', {
                method: 'POST'
            });
            
            const result = await response.json();
            
            if (result.success && result.url) {
                window.location.href = result.url;
            } else {
                showNotification('Failed to open payment portal', 'error');
            }
        } catch (error) {
            showNotification('Error opening payment portal', 'error');
            console.error('Portal error:', error);
        }
    });
}

// View Latest Invoice
const viewInvoiceBtn = document.getElementById('view-invoice-btn');
if (viewInvoiceBtn) {
    viewInvoiceBtn.addEventListener('click', () => {
        // Switch to billing tab and show latest invoice
        document.querySelector('[data-tab="billing"]').click();
        closeModal('manage-subscription-modal');
    });
}

// Cancel Subscription (open cancel modal)
const cancelSubModalBtn = document.getElementById('cancel-subscription-modal-btn');
if (cancelSubModalBtn) {
    cancelSubModalBtn.addEventListener('click', () => {
        closeModal('manage-subscription-modal');
        openModal('cancel-subscription-modal');
    });
}

// ============================================
// CANCEL SUBSCRIPTION MODAL
// ============================================

const confirmCancelBtn = document.getElementById('confirm-cancel-btn');
if (confirmCancelBtn) {
    confirmCancelBtn.addEventListener('click', async () => {
        const cancelType = document.querySelector('input[name="cancel-type"]:checked').value;
        const reason = document.getElementById('cancel-reason').value;
        
        if (!confirm('Are you sure you want to cancel your subscription?')) {
            return;
        }
        
        try {
            const response = await fetch('../app/profileActions.php?action=cancel_subscription', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cancelType, reason })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Subscription cancelled', 'success');
                closeModal('cancel-subscription-modal');
                
                // Reload page after 2s
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showNotification(result.message || 'Cancellation failed', 'error');
            }
        } catch (error) {
            showNotification('Cancellation failed. Please try again.', 'error');
            console.error('Cancel error:', error);
        }
    });
}

// ============================================
// DELETE ACCOUNT MODAL
// ============================================

const deleteAccountBtn = document.getElementById('delete-account-btn');
const deleteConfirmInput = document.getElementById('delete-confirmation-input');
const confirmDeleteBtn = document.getElementById('confirm-delete-btn');

if (deleteAccountBtn) {
    deleteAccountBtn.addEventListener('click', () => {
        openModal('delete-account-modal');
    });
}

if (deleteConfirmInput) {
    deleteConfirmInput.addEventListener('input', (e) => {
        confirmDeleteBtn.disabled = e.target.value !== 'DELETE';
    });
}

if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', async () => {
        if (deleteConfirmInput.value !== 'DELETE') {
            showNotification('Please type DELETE to confirm', 'error');
            return;
        }
        
        try {
            const response = await fetch('../app/profileActions.php?action=delete_account', {
                method: 'POST'
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Account deleted. Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = '../public/index.php';
                }, 2000);
            } else {
                showNotification(result.message || 'Deletion failed', 'error');
            }
        } catch (error) {
            showNotification('Deletion failed. Please try again.', 'error');
            console.error('Delete error:', error);
        }
    });
}

// ============================================
// NOTIFICATIONS
// ============================================

function showNotification(message, type = 'info') {
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
    
    // Remove after 3s
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Add notification styles
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    .notification {
        position: fixed;
        top: 70px;
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
`;
document.head.appendChild(notificationStyles);
