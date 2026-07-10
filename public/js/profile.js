/**
 * Profile Page JavaScript
 * 
 * Handles tab switching, inline editing, modals, AJAX requests
 */

function setHidden(element, hidden) {
    if (element) element.hidden = hidden;
}

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

const refreshPromotionsBtn = document.getElementById('refresh-promotions-btn');
if (refreshPromotionsBtn) {
    refreshPromotionsBtn.addEventListener('click', () => {
        refreshPromotionsBtn.classList.add('is-refreshing');
        refreshPromotionsBtn.disabled = true;

        const url = new URL(window.location.href);
        url.searchParams.set('tab', 'profile');
        url.hash = 'your-promotions';

        if (url.toString() === window.location.href) {
            window.location.reload();
            return;
        }

        window.location.assign(url.toString());
    });
}

// ============================================
// EMAIL MASKING
// ============================================

(function () {
    const emailDisplay = document.getElementById('email-display');
    const emailFull    = document.getElementById('email-full');
    const emailToggle  = document.getElementById('email-toggle');

    if (!emailDisplay || !emailFull || !emailToggle) return;

    const full = emailFull.value;

    function maskEmail(email) {
        const atIndex = email.indexOf('@');
        if (atIndex < 0) return email;
        return email[0] + '***' + email.slice(atIndex);
    }

    let visible = false;

    function renderEmailVisibility() {
        emailDisplay.textContent = visible ? full : maskEmail(full);
        emailToggle.setAttribute('aria-pressed', String(visible));
        emailToggle.setAttribute('aria-label', visible ? 'Hide email' : 'Show email');
    }

    renderEmailVisibility();

    emailToggle.addEventListener('click', () => {
        visible = !visible;
        renderEmailVisibility();
    });
})();

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
            showNotification('File is too large. Maximum size is 5MB.', 'error');
            return;
        }
        
        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            showNotification('Invalid file type. Please use JPG or PNG.', 'error');
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
            const response = await fetch('app/profileActions.php?action=upload_avatar', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Profile picture updated successfully!', 'success');
                
                // ✅ Update VŠETKY avatary
                document.querySelectorAll('.user-avatar-img').forEach(img => {
                    img.src = result.url;
                });
            } else {
                showNotification(result.message || 'Upload failed', 'error');
                
                // ✅ Vráť starú pre VŠETKY
                document.querySelectorAll('.user-avatar-img').forEach(img => {
                    img.src = oldAvatarUrl;
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
        setHidden(nameField.querySelector('.field-display'), true);
        setHidden(nameField.querySelector('.field-edit'), false);
        document.getElementById('first-name-input').focus();
    });
}

// Cancel button - skry form
if (cancelNameBtn) {
    cancelNameBtn.addEventListener('click', () => {
        setHidden(nameField.querySelector('.field-display'), false);
        setHidden(nameField.querySelector('.field-edit'), true);
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
            const response = await fetch('app/profileActions.php?action=update_name', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ firstName, lastName })
            });
            
            const result = await response.json();
            
            if (result.success) {
                const capitalizedFirst = result.firstName || firstName;
                const capitalizedLast = result.lastName || lastName;
                
                document.getElementById('name-display').textContent = `${capitalizedFirst} ${capitalizedLast}`;
                setHidden(nameField.querySelector('.field-display'), false);
                setHidden(nameField.querySelector('.field-edit'), true);
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
        setHidden(changePasswordBtn, true);
        setHidden(passwordForm, false);
        document.getElementById('current-password').focus();
    });
}

if (cancelPasswordBtn) {
    cancelPasswordBtn.addEventListener('click', () => {
        setHidden(changePasswordBtn, false);
        setHidden(passwordForm, true);
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
        
        const passwordErrors = [];
        if (newPassword.length < 8) passwordErrors.push('at least 8 characters');
        if (!/\p{Lu}/u.test(newPassword)) passwordErrors.push('an uppercase letter');
        if (!/\p{Ll}/u.test(newPassword)) passwordErrors.push('a lowercase letter');
        if (!/\p{N}/u.test(newPassword)) passwordErrors.push('a number');
        if (!/[^\p{L}\p{N}\s]/u.test(newPassword)) passwordErrors.push('a special character');

        if (passwordErrors.length) {
            showNotification(`New password must contain ${passwordErrors.join(', ')}`, 'error');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showNotification('New passwords do not match', 'error');
            return;
        }
        
        try {
            const response = await fetch('app/profileActions.php?action=change_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ currentPassword, newPassword })
            });
            
            const result = await response.json();
            
            if (result.success) {
                setHidden(changePasswordBtn, false);
                setHidden(passwordForm, true);
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
let marketingToggleLocked = false;

if (marketingToggle) {
    marketingToggle.addEventListener('change', async (e) => {
        if (marketingToggleLocked) {
            return;
        }

        const enabled = e.target.checked;
        const previousValue = !enabled;

        marketingToggleLocked = true;
        e.target.disabled = true;
        
        try {
            const response = await fetch('app/profileActions.php?action=update_marketing_emails', {
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
                e.target.checked = previousValue;
                showNotification(result.message || 'Update failed', 'error');
            }
        } catch (error) {
            // Revert toggle
            e.target.checked = previousValue;
            showNotification('Update failed. Please try again.', 'error');
            console.error('Update error:', error);
        } finally {
            e.target.disabled = false;
            marketingToggleLocked = false;
        }
    });
}

// ============================================
// CUSTOM CONFIRM POPUP
// ============================================

function showConfirm(title, message, options = {}) {
    return new Promise((resolve) => {
        const modal      = document.getElementById('confirm-modal');
        const titleEl    = document.getElementById('confirm-modal-title');
        const msgEl      = document.getElementById('confirm-modal-message');
        const extraEl    = document.getElementById('confirm-modal-extra');
        const confirmBtn = document.getElementById('confirm-modal-confirm');
        const cancelBtn  = document.getElementById('confirm-modal-cancel');
        const closeBtn   = document.getElementById('confirm-modal-close');

        titleEl.textContent = title;
        msgEl.textContent   = message;
        if (extraEl) extraEl.innerHTML = '';
        cancelBtn.textContent = options.cancelText || 'Go Back';
        confirmBtn.textContent = options.confirmText || 'Yes, Cancel';
        confirmBtn.disabled = false;
        setHidden(modal, false);
        lockScroll();

        function cleanup(result) {
            setHidden(modal, true);
            unlockScroll();
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            closeBtn?.removeEventListener('click', onCancel);
            resolve(result);
        }
        function onConfirm() { cleanup(true);  }
        function onCancel()  { cleanup(false); }

        confirmBtn.addEventListener('click', onConfirm);
        cancelBtn.addEventListener('click',  onCancel);
        closeBtn?.addEventListener('click', onCancel);
    });
}

// ============================================
// BILLING FILTERS
// ============================================

const dateFilter = document.getElementById('date-filter');
const statusFilter = document.getElementById('status-filter');
const methodFilter = document.getElementById('method-filter');
const resetFiltersBtn = document.getElementById('reset-filters');

const paymentsTbody = document.getElementById('payments-tbody');
const paymentsTableWrapper = document.getElementById('payments-table-wrapper');
const billingContainer = document.querySelector('.billing-container');
const billingPaginationRow = document.getElementById('billing-pagination-row');
const billingPaginationSummary = document.getElementById('billing-pagination-summary');
const billingPagination = document.getElementById('billing-pagination');
let billingHistoryRequest = null;

function billingHistoryPageRange(currentPage, totalPages) {
    if (totalPages <= 7) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }
    if (currentPage <= 4) {
        return [1, 2, 3, 4, 5, '…', totalPages];
    }
    if (currentPage >= totalPages - 3) {
        return [1, '…', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
    }
    return [1, '…', currentPage - 1, currentPage, currentPage + 1, '…', totalPages];
}

function billingHistoryFilters() {
    return {
        billing_date: dateFilter?.value || 'all',
        billing_status: statusFilter?.value || 'all',
        billing_method: methodFilter?.value || 'all',
    };
}

function billingHistoryUrl(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'billing');

    if (page > 1) {
        url.searchParams.set('billing_page', String(page));
    } else {
        url.searchParams.delete('billing_page');
    }

    Object.entries(billingHistoryFilters()).forEach(([parameter, value]) => {
        if (value === 'all') {
            url.searchParams.delete(parameter);
        } else {
            url.searchParams.set(parameter, value);
        }
    });

    return url;
}

function paymentInfoIcon() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 5.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm1.5 9h-3v-1.5h.75v-3h-.75v-1.5h2.25v4.5h.75v1.5Z');
    svg.append(path);
    return svg;
}

function paginationArrowIcon(direction) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', direction === 'previous' ? 'm15 18-6-6 6-6' : 'm9 18 6-6-6-6');
    svg.append(path);
    return svg;
}

function paymentStatusElement(payment) {
    const status = String(payment.status || '').replace(/[^a-z_]/g, '');
    const wrapper = document.createElement('div');
    wrapper.className = 'payment-status';

    if (status !== 'pending') {
        const badge = document.createElement('span');
        badge.className = `status-badge status-${status}`;
        badge.textContent = payment.status_label;
        wrapper.append(badge);
        return wrapper;
    }

    const hintId = `payment-status-hint-${payment.id}`;
    const help = document.createElement('span');
    help.className = 'payment-status-help';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = `status-badge status-${status} payment-status-help__trigger`;
    trigger.setAttribute('aria-label', 'Why is this payment awaiting payment?');
    trigger.setAttribute('aria-describedby', hintId);
    const label = document.createElement('span');
    label.textContent = payment.status_label;
    trigger.append(label, paymentInfoIcon());

    const tooltip = document.createElement('span');
    tooltip.className = 'payment-status-help__tooltip';
    tooltip.id = hintId;
    tooltip.setAttribute('role', 'tooltip');
    tooltip.textContent = 'This invoice is automatically cancelled if it remains unpaid for approximately 24 hours.';

    help.append(trigger, tooltip);
    wrapper.append(help);
    return wrapper;
}

function paymentInvoiceElement(invoiceUrl) {
    if (!invoiceUrl || !/^https?:\/\//i.test(invoiceUrl)) {
        const empty = document.createElement('span');
        empty.className = 'text-muted';
        empty.textContent = '—';
        return empty;
    }

    const link = document.createElement('a');
    link.className = 'invoice-link';
    link.href = invoiceUrl;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = 'View invoice';
    return link;
}

function renderBillingPayments(data) {
    if (!paymentsTbody || !billingPaginationRow || !billingPaginationSummary || !billingPagination) {
        return;
    }

    const previousScrollLeft = paymentsTableWrapper?.scrollLeft || 0;
    const rows = [];

    if (data.payments.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 6;
        cell.className = 'no-payments';
        cell.textContent = data.has_filters ? 'No payments match these filters' : 'No payment history yet';
        row.append(cell);
        rows.push(row);
    } else {
        data.payments.forEach((payment) => {
            const row = document.createElement('tr');
            const date = document.createElement('td');
            date.textContent = payment.date_label;

            const packageCell = document.createElement('td');
            packageCell.className = 'payment-package-cell';
            const packageName = document.createElement('strong');
            packageName.textContent = payment.package_name;
            packageCell.append(packageName);
            if (payment.package_meta) {
                const packageMeta = document.createElement('span');
                packageMeta.textContent = payment.package_meta;
                packageCell.append(packageMeta);
            }

            const method = document.createElement('td');
            method.textContent = payment.method_label;
            const amount = document.createElement('td');
            amount.textContent = payment.amount_label;
            const status = document.createElement('td');
            status.append(paymentStatusElement(payment));
            const invoice = document.createElement('td');
            invoice.append(paymentInvoiceElement(payment.invoice_url));

            row.append(date, packageCell, method, amount, status, invoice);
            rows.push(row);
        });
    }

    paymentsTbody.replaceChildren(...rows);
    if (paymentsTableWrapper) {
        paymentsTableWrapper.scrollLeft = previousScrollLeft;
    }

    const { page, per_page: perPage, total, total_pages: totalPages, offset } = data.pagination;
    billingPaginationRow.hidden = total === 0;
    billingPaginationSummary.textContent = `Showing ${offset + 1}–${Math.min(offset + perPage, total)} of ${total} payments`;
    billingPagination.replaceChildren();
    billingPagination.hidden = totalPages <= 1;

    if (totalPages <= 1) {
        return;
    }

    const addArrow = (direction, targetPage, disabled) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `billing-pagination__arrow${disabled ? ' is-disabled' : ''}`;
        button.disabled = disabled;
        button.setAttribute('aria-label', `${direction === 'previous' ? 'Previous' : 'Next'} billing history page`);
        button.append(paginationArrowIcon(direction));
        if (!disabled) {
            button.addEventListener('click', () => loadBillingHistory(targetPage));
        }
        billingPagination.append(button);
    };

    addArrow('previous', page - 1, page === 1);
    billingHistoryPageRange(page, totalPages).forEach((pageItem) => {
        if (pageItem === '…') {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'billing-pagination__ellipsis';
            ellipsis.setAttribute('aria-hidden', 'true');
            ellipsis.textContent = '…';
            billingPagination.append(ellipsis);
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = `billing-pagination__page${pageItem === page ? ' is-active' : ''}`;
        button.textContent = String(pageItem);
        if (pageItem === page) {
            button.setAttribute('aria-current', 'page');
        } else {
            button.addEventListener('click', () => loadBillingHistory(pageItem));
        }
        billingPagination.append(button);
    });
    addArrow('next', page + 1, page === totalPages);
}

async function loadBillingHistory(page = 1, { updateUrl = true } = {}) {
    if (!paymentsTbody || !billingContainer || !dateFilter || !statusFilter || !methodFilter) {
        return;
    }

    billingHistoryRequest?.abort();
    const requestController = new AbortController();
    billingHistoryRequest = requestController;
    const profileUrl = billingHistoryUrl(page);
    const apiUrl = new URL('api/getBillingHistory.php', window.location.href);
    profileUrl.searchParams.forEach((value, key) => apiUrl.searchParams.set(key, value));

    billingContainer.classList.add('is-loading');
    paymentsTableWrapper?.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(apiUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: requestController.signal,
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not load billing history.');
        }

        renderBillingPayments(data);
        if (updateUrl) {
            window.history.pushState({ billingHistory: true }, '', `${profileUrl.pathname}${profileUrl.search}`);
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Billing history load error:', error);
            showNotification(error.message || 'Could not load billing history. Please try again.', 'error');
        }
    } finally {
        if (billingHistoryRequest === requestController) {
            billingContainer.classList.remove('is-loading');
            paymentsTableWrapper?.removeAttribute('aria-busy');
        }
    }
}

if (dateFilter && statusFilter && methodFilter) {
    dateFilter.addEventListener('change', () => loadBillingHistory());
    statusFilter.addEventListener('change', () => loadBillingHistory());
    methodFilter.addEventListener('change', () => loadBillingHistory());
}

if (resetFiltersBtn && dateFilter && statusFilter && methodFilter) {
    resetFiltersBtn.addEventListener('click', (event) => {
        event.preventDefault();
        dateFilter.value = 'all';
        statusFilter.value = 'all';
        methodFilter.value = 'all';
        loadBillingHistory();
    });
}

billingPagination?.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link) {
        return;
    }

    event.preventDefault();
    const targetUrl = new URL(link.href);
    loadBillingHistory(Number(targetUrl.searchParams.get('billing_page')) || 1);
});

window.addEventListener('popstate', () => {
    const url = new URL(window.location.href);
    if (url.searchParams.get('tab') !== 'billing' || !dateFilter || !statusFilter || !methodFilter) {
        return;
    }

    dateFilter.value = url.searchParams.get('billing_date') || 'all';
    statusFilter.value = url.searchParams.get('billing_status') || 'all';
    methodFilter.value = url.searchParams.get('billing_method') || 'all';
    loadBillingHistory(Number(url.searchParams.get('billing_page')) || 1, { updateUrl: false });
});

function initPaymentStatusTooltips() {
    if (!paymentsTableWrapper || paymentsTableWrapper.dataset.tooltipEventsBound === 'true') {
        return;
    }

    paymentsTableWrapper.dataset.tooltipEventsBound = 'true';
    const updateDirection = (statusHelp) => {
        const trigger = statusHelp.querySelector('.payment-status-help__trigger');
        const tooltip = statusHelp.querySelector('.payment-status-help__tooltip');
        if (!trigger || !tooltip) {
            return;
        }

        const triggerRect = trigger.getBoundingClientRect();
        const wrapperRect = paymentsTableWrapper.getBoundingClientRect();
        const tooltipHeight = tooltip.offsetHeight;
        const spacing = 10;
        const viewportPadding = 12;
        const lowerBoundary = Math.min(window.innerHeight - viewportPadding, wrapperRect.bottom - viewportPadding);
        const upperBoundary = Math.max(viewportPadding, wrapperRect.top + viewportPadding);
        const canOpenBelow = triggerRect.bottom + spacing + tooltipHeight <= lowerBoundary;
        const canOpenAbove = triggerRect.top - spacing - tooltipHeight >= upperBoundary;

        statusHelp.classList.toggle('payment-status-help--above', !canOpenBelow && canOpenAbove);
    };

    paymentsTableWrapper.addEventListener('pointerover', (event) => {
        const statusHelp = event.target.closest('.payment-status-help');
        if (!statusHelp || !paymentsTableWrapper.contains(statusHelp) || statusHelp.contains(event.relatedTarget)) {
            return;
        }
        updateDirection(statusHelp);
    });

    const updateOpenTooltips = () => {
        paymentsTableWrapper.querySelectorAll('.payment-status-help').forEach((statusHelp) => {
            if (statusHelp.matches(':hover')) {
                updateDirection(statusHelp);
            }
        });
    };

    paymentsTableWrapper.addEventListener('scroll', updateOpenTooltips, { passive: true });
    window.addEventListener('resize', updateOpenTooltips, { passive: true });
}

initPaymentStatusTooltips();

// ============================================
// MODALS
// ============================================

// Helper: Open modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        setHidden(modal, false);
        lockScroll();
    }
}

// Helper: Close modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        if (modal.classList.contains('is-processing')) {
            return;
        }
        setHidden(modal, true);
        unlockScroll();
    }
}

// Close modals only through explicit controls.
document.querySelectorAll('.modal').forEach(modal => {
    modal.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => closeModal(modal.id));
    });
});

// ============================================
// MANAGE SUBSCRIPTION MODAL
// ============================================

let selectedSubscription = null;
let selectedCancelItemIds = [];
let cancelProcessing = false;

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '-';
}

async function readJsonResponse(response) {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch (error) {
        throw new Error('We could not complete this request. Please try again.');
    }
}

function friendlyRequestError(error, fallback) {
    const message = error?.message?.trim();
    if (!message) {
        return fallback;
    }

    return message.length > 180 ? `${message.slice(0, 177)}...` : message;
}

function parseCancelItems(rawValue) {
    if (!rawValue) {
        return [];
    }

    try {
        const parsed = JSON.parse(rawValue);
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
}

function boolish(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
}

function normalizeCancelItem(item) {
    const hasAutoRenew = Object.prototype.hasOwnProperty.call(item || {}, 'auto_renew')
        || Object.prototype.hasOwnProperty.call(item || {}, 'autoRenew');

    return {
        id: item?.id ? String(item.id) : '',
        promotionId: item?.promotion_id ? String(item.promotion_id) : '',
        slotType: item?.slot_type || '',
        plan: item?.plan || 'Promotion',
        chatbot: item?.chatbot || '',
        price: item?.price || '',
        standalonePrice: item?.standalone_price || item?.price || '',
        period: item?.period || '',
        autoRenew: hasAutoRenew ? boolish(item?.auto_renew ?? item?.autoRenew) : true,
        cancelAtPeriodEnd: boolish(item?.cancel_at_period_end ?? item?.cancelAtPeriodEnd)
    };
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getSelectedCancelItems() {
    const items = selectedSubscription?.cancelItems?.length
        ? selectedSubscription.cancelItems
        : [{
            id: selectedSubscription?.id || '',
            plan: selectedSubscription?.plan || 'Subscription',
            chatbot: selectedSubscription?.chatbot || '',
            price: selectedSubscription?.price || '',
            standalonePrice: selectedSubscription?.price || '',
            period: selectedSubscription?.period || ''
        }];

    if (!selectedCancelItemIds.length) {
        return [];
    }

    return items.filter(item => selectedCancelItemIds.includes(String(item.id)));
}

function getRemainingCancelItems() {
    const items = selectedSubscription?.cancelItems?.length
        ? selectedSubscription.cancelItems
        : [];

    return items.filter(item => !selectedCancelItemIds.includes(String(item.id)));
}

function selectedCancelModeLabel() {
    const selectedItems = getSelectedCancelItems();
    if (!selectedItems.length) {
        return 'Choose at least one promotion to cancel:';
    }

    return selectedItems.length > 1
        ? 'These promotions will be cancelled:'
        : 'This promotion will be cancelled:';
}

function partialCancelPriceNotice() {
    const remainingItems = getRemainingCancelItems();
    if (!selectedSubscription?.cancelItems?.length || remainingItems.length !== 1 || !selectedCancelItemIds.length) {
        return '';
    }

    const remaining = remainingItems[0];
    if (remaining.cancelAtPeriodEnd || remaining.autoRenew === false) {
        return `
            <div class="cancel-confirm-summary__notice">
                The remaining ${escapeHtml(remaining.plan)} is already scheduled to end after this billing period.
                No promotion in this bundle will renew after that.
            </div>
        `;
    }

    return `
        <div class="cancel-confirm-summary__notice">
            The remaining ${escapeHtml(remaining.plan)} will continue without the bundle discount.
            New renewal price: <strong>${escapeHtml(remaining.standalonePrice || remaining.price || '')} / ${escapeHtml(remaining.period || '')}</strong>.
        </div>
    `;
}

function renderCancelItemsSummary(items, labelText) {
    return `
        <span class="cancel-confirm-summary__label">
            ${escapeHtml(labelText)}
        </span>
        <div class="cancel-confirm-summary__hint">
            Choose exactly what should stop renewing.
        </div>
        <ul class="cancel-confirm-summary__list cancel-confirm-summary__list--selectable">
            ${items.map(item => `
                <li>
                    <label class="cancel-item-toggle">
                        <span class="cancel-item-toggle__copy">
                            <strong>${escapeHtml(item.plan || 'Promotion')}</strong>
                            <span>${escapeHtml(item.chatbot || '')}${item.price ? ` - ${escapeHtml(item.price)} / ${escapeHtml(item.period || '')}` : ''}</span>
                        </span>
                        <span class="toggle-switch cancel-item-toggle__switch">
                            <input type="checkbox" class="cancel-item-checkbox" value="${escapeHtml(item.id || '')}" ${selectedCancelItemIds.includes(String(item.id)) ? 'checked' : ''}>
                            <span class="toggle-slider"></span>
                        </span>
                    </label>
                </li>
            `).join('')}
        </ul>
    `;
}

function updateCancelSubscriptionSummary() {
    const summaryEl = document.getElementById('cancel-subscription-items');
    if (!summaryEl) return;

    const allItems = selectedSubscription?.cancelItems?.length
        ? selectedSubscription.cancelItems
        : getSelectedCancelItems();
    summaryEl.innerHTML = renderCancelItemsSummary(
        allItems,
        selectedCancelModeLabel()
    );

    summaryEl.querySelectorAll('.cancel-item-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            const id = String(checkbox.value || '');
            if (!id) return;

            if (checkbox.checked) {
                if (!selectedCancelItemIds.includes(id)) {
                    selectedCancelItemIds.push(id);
                }
            } else {
                selectedCancelItemIds = selectedCancelItemIds.filter(itemId => itemId !== id);
            }

            updateCancelSubscriptionSummary();
        });
    });

    const confirmBtn = document.getElementById('confirm-cancel-btn');
    if (confirmBtn) {
        confirmBtn.disabled = cancelProcessing || selectedCancelItemIds.length === 0;
    }
}

function setCancelProcessing(isProcessing, message = 'Please wait while we update your subscription.') {
    cancelProcessing = isProcessing;

    const modal = document.getElementById('cancel-subscription-modal');
    const formState = document.getElementById('cancel-form-state');
    const processingState = document.getElementById('cancel-processing-state');
    const processingMessage = document.getElementById('cancel-processing-message');
    const confirmBtn = document.getElementById('confirm-cancel-btn');

    modal?.classList.toggle('is-processing', isProcessing);

    if (formState) {
        formState.hidden = isProcessing;
    }

    if (processingState) {
        processingState.hidden = !isProcessing;
    }

    if (processingMessage) {
        processingMessage.textContent = message;
    }

    if (confirmBtn) {
        confirmBtn.disabled = isProcessing || selectedCancelItemIds.length === 0;
        confirmBtn.textContent = isProcessing ? 'Updating...' : 'Cancel Subscription';
    }
}

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function defaultCancelItemIdsForSelectedSubscription() {
    const items = selectedSubscription?.cancelItems?.length ? selectedSubscription.cancelItems : [];
    if (!items.length) {
        return selectedSubscription?.id ? [String(selectedSubscription.id)] : [];
    }

    const isFullBundleEntry = /full bundle/i.test(selectedSubscription?.plan || '');
    if (isFullBundleEntry) {
        return items.map(item => String(item.id)).filter(Boolean);
    }

    const openedItem = items.find(item => String(item.id) === String(selectedSubscription?.id || ''));
    return openedItem?.id ? [String(openedItem.id)] : [String(items[0].id)];
}

function cancelConfirmationMessage(cancelType) {
    const selectedItems = getSelectedCancelItems();
    const itemNames = selectedItems.map(item => item.plan).join(', ');
    const cancelTimingDescription = cancelType === 'immediately'
        ? 'immediately. Access to the selected promotions will end now.'
        : 'at the end of your billing period.';
    const remainingItems = getRemainingCancelItems();
    let priceNotice = '';
    if (remainingItems.length === 1 && selectedItems.length > 0) {
        const remaining = remainingItems[0];
        priceNotice = remaining.cancelAtPeriodEnd || remaining.autoRenew === false
            ? ` The remaining ${remaining.plan} is already scheduled to end after this billing period.`
            : ` The remaining ${remaining.plan} will renew without the bundle discount at ${remaining.standalonePrice || remaining.price} / ${remaining.period}.`;
    }

    return `${itemNames || 'Selected promotions'} will be cancelled ${cancelTimingDescription}${priceNotice}`;
}

document.querySelectorAll('.manage-subscription-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        selectedSubscription = {
            id: btn.dataset.subscriptionId || '',
            plan: btn.dataset.plan || '-',
            chatbot: btn.dataset.chatbot || '-',
            price: btn.dataset.price || '-',
            period: btn.dataset.period || '',
            periodEnd: btn.dataset.periodEnd || '-',
            periodEndLabel: btn.dataset.periodEndLabel || 'Period end',
            autoRenew: btn.dataset.autoRenew || '-',
            status: btn.dataset.status || '-',
            canCancel: btn.dataset.canCancel === '1',
            cancelAtPeriodEnd: btn.dataset.cancelAtPeriodEnd === '1',
            invoiceUrl: btn.dataset.invoiceUrl || '',
            cancelItems: parseCancelItems(btn.dataset.cancelItems).map(normalizeCancelItem)
        };

        document.getElementById('selected-subscription-id').value = selectedSubscription.id;
        document.getElementById('selected-subscription-can-cancel').value = selectedSubscription.canCancel ? '1' : '0';
        document.getElementById('selected-subscription-invoice-url').value = selectedSubscription.invoiceUrl;

        setText('modal-subscription-plan', selectedSubscription.plan);
        setText('modal-subscription-chatbot', selectedSubscription.chatbot);
        setText('modal-subscription-price', `${selectedSubscription.price} / ${selectedSubscription.period}`);
        setText('modal-subscription-period-end-label', selectedSubscription.periodEndLabel);
        setText('modal-subscription-next-billing', selectedSubscription.periodEnd);
        setText('modal-subscription-auto-renew', selectedSubscription.autoRenew);
        setText('modal-subscription-status', selectedSubscription.status);

        const cancelBtn = document.getElementById('cancel-subscription-modal-btn');
        if (cancelBtn) {
            cancelBtn.disabled = !selectedSubscription.canCancel;
            cancelBtn.textContent = selectedSubscription.cancelAtPeriodEnd
                ? 'Cancel Immediately'
                : (selectedSubscription.canCancel ? 'Cancel Subscription' : 'Cancellation unavailable');
        }

        const renewBtn = document.getElementById('renew-subscription-btn');
        if (renewBtn) {
            renewBtn.hidden = !selectedSubscription.cancelAtPeriodEnd;
            renewBtn.disabled = !selectedSubscription.cancelAtPeriodEnd;
        }

        openModal('manage-subscription-modal');
    });
});

// Change Plan
document.querySelectorAll('#change-plan-btn').forEach(changePlanBtn => {
    changePlanBtn.addEventListener('click', () => {
        window.location.href = 'advertise#plans';
    });
});

// Update Payment Method (Stripe Customer Portal)
const updatePaymentBtn = document.getElementById('update-payment-btn');
if (updatePaymentBtn) {
    updatePaymentBtn.addEventListener('click', async () => {
        try {
            const response = await fetch('app/profileActions.php?action=stripe_customer_portal', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subscription_id: selectedSubscription?.id || '' })
            });
            
            const result = await readJsonResponse(response);
            
            if (result.success && result.url) {
                window.location.href = result.url;
            } else {
                showNotification(result.message || 'Failed to open payment portal', 'error');
            }
        } catch (error) {
            showNotification(friendlyRequestError(error, 'Error opening payment portal'), 'error');
            console.error('Portal error:', error);
        }
    });
}

// View Latest Invoice
const viewInvoiceBtn = document.getElementById('view-invoice-btn');
if (viewInvoiceBtn) {
    viewInvoiceBtn.addEventListener('click', () => {
        const invoiceUrl = selectedSubscription?.invoiceUrl || '';
        if (invoiceUrl) {
            window.open(invoiceUrl, '_blank', 'noopener');
            return;
        }

        document.querySelector('[data-tab="billing"]').click();
        closeModal('manage-subscription-modal');
    });
}

// Renew Promotion
const renewSubscriptionBtn = document.getElementById('renew-subscription-btn');
if (renewSubscriptionBtn) {
    renewSubscriptionBtn.addEventListener('click', async () => {
        if (!selectedSubscription?.cancelAtPeriodEnd) {
            showNotification('This subscription is already set to renew.', 'info');
            return;
        }

        const confirmed = await showConfirm(
            'Renew Promotion?',
            'This promotion will continue renewing automatically after the current period.'
        );
        if (!confirmed) return;

        renewSubscriptionBtn.disabled = true;

        try {
            const response = await fetch('app/profileActions.php?action=renew_subscription', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subscription_id: selectedSubscription.id })
            });
            const result = await readJsonResponse(response);

            if (result.success) {
                showNotification(result.message || 'Promotion renewed', 'success');
                closeModal('manage-subscription-modal');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(result.message || 'Renewal failed', 'error');
                renewSubscriptionBtn.disabled = false;
            }
        } catch (error) {
            showNotification(friendlyRequestError(error, 'Renewal failed. Please try again.'), 'error');
            renewSubscriptionBtn.disabled = false;
        }
    });
}

// Cancel Subscription (open cancel modal)
const cancelSubModalBtn = document.getElementById('cancel-subscription-modal-btn');
if (cancelSubModalBtn) {
    cancelSubModalBtn.addEventListener('click', () => {
        if (!selectedSubscription?.canCancel) {
            showNotification('This subscription cannot be cancelled from here right now.', 'error');
            return;
        }

        const periodEndRadio = document.querySelector('input[name="cancel-type"][value="at-period-end"]');
        const immediateRadio = document.querySelector('input[name="cancel-type"][value="immediately"]');
        const periodEndOption = periodEndRadio?.closest('.radio-option');

        if (periodEndRadio && immediateRadio) {
            periodEndRadio.disabled = selectedSubscription.cancelAtPeriodEnd;
            periodEndOption?.classList.toggle('disabled', selectedSubscription.cancelAtPeriodEnd);

            if (selectedSubscription.cancelAtPeriodEnd) {
                immediateRadio.checked = true;
            } else {
                periodEndRadio.checked = true;
            }
        }

        const periodEndText = selectedSubscription.cancelAtPeriodEnd
            ? `Already scheduled for ${selectedSubscription.periodEnd}.`
            : `(${selectedSubscription.periodEnd})`;
        setText('cancel-period-end-date', periodEndText);
        selectedCancelItemIds = defaultCancelItemIdsForSelectedSubscription();
        setCancelProcessing(false);
        updateCancelSubscriptionSummary();
        closeModal('manage-subscription-modal');
        openModal('cancel-subscription-modal');
    });
}

const KeepPlanBtn = document.getElementById('keep-plan-btn');
if (KeepPlanBtn) {
    KeepPlanBtn.addEventListener('click', () => {
        closeModal('cancel-subscription-modal');
        if (selectedSubscription) {
            openModal('manage-subscription-modal');
        }
    });
}

// ============================================
// CANCEL SUBSCRIPTION MODAL
// ============================================

const confirmCancelBtn = document.getElementById('confirm-cancel-btn');
if (confirmCancelBtn) {
    confirmCancelBtn.addEventListener('click', async () => {
        const cancelType = document.querySelector('input[name="cancel-type"]:checked').value;

        if (!selectedCancelItemIds.length) {
            showNotification('Choose at least one promotion to cancel.', 'error');
            return;
        }

        const confirmed = await showConfirm(
            'Confirm Cancellation?',
            cancelConfirmationMessage(cancelType),
            {
                cancelText: 'Go Back',
                confirmText: 'Cancel Subscription'
            }
        );

        if (!confirmed) return;

        setCancelProcessing(true);

        try {
            const response = await fetch('app/profileActions.php?action=cancel_subscription', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subscription_id: selectedSubscription?.id || '',
                    cancelType,
                    cancel_item_ids: selectedCancelItemIds
                })
            });
            const result = await readJsonResponse(response);
            if (result.success) {
                setCancelProcessing(true, 'Your subscription was updated. Opening feedback...');
                showNotification(result.message || 'Subscription cancelled', 'success');
                await wait(500);
                setCancelProcessing(false);
                closeModal('cancel-subscription-modal');

                const feedback = await showCancelFeedbackForm(
                    'Why are you cancelling?',
                    'Your cancellation is saved. Your feedback helps us improve the promotion experience.'
                );

                if (feedback.confirmed) {
                    await saveCancellationFeedback(feedback.reason);
                }

                setTimeout(() => window.location.reload(), 1000);
            } else {
                setCancelProcessing(false);
                showNotification(result.message || 'Cancellation failed', 'error');
            }
        } catch (error) {
            setCancelProcessing(false);
            showNotification(friendlyRequestError(error, 'Cancellation failed. Please try again.'), 'error');
        }
    });
}

function showCancelFeedbackForm(title, message) {
    return new Promise((resolve) => {
        const modal      = document.getElementById('confirm-modal');
        const titleEl    = document.getElementById('confirm-modal-title');
        const msgEl      = document.getElementById('confirm-modal-message');
        const extraEl    = document.getElementById('confirm-modal-extra');
        const confirmBtn = document.getElementById('confirm-modal-confirm');
        const cancelBtn  = document.getElementById('confirm-modal-cancel');
        const closeBtn   = document.getElementById('confirm-modal-close');

        titleEl.textContent = title;
        msgEl.textContent = message;
        cancelBtn.textContent = 'No thanks';
        confirmBtn.textContent = 'Submit feedback';
        confirmBtn.disabled = true;

        extraEl.innerHTML = `
            <div class="cancel-confirm-reason">
                <label class="info-field-static-label" for="confirm-cancel-reason">Why are you cancelling?</label>
                <select id="confirm-cancel-reason">
                    <option value="">Select a reason...</option>
                    <option value="too-expensive">Too expensive</option>
                    <option value="not-useful">Not getting enough value</option>
                    <option value="found-alternative">Found a better alternative</option>
                    <option value="technical-issues">Technical issues</option>
                    <option value="other">Other</option>
                </select>
                <textarea id="confirm-cancel-reason-other" placeholder="Please tell us more..."></textarea>
            </div>
        `;

        const reasonSelect = document.getElementById('confirm-cancel-reason');
        const reasonOther = document.getElementById('confirm-cancel-reason-other');
        setHidden(reasonOther, true);

        function updateSubmitState() {
            if (reasonSelect.value === 'other') {
                confirmBtn.disabled = reasonOther.value.trim().length === 0;
                return;
            }

            confirmBtn.disabled = reasonSelect.value === '';
        }

        function onReasonChange() {
            const showOther = reasonSelect.value === 'other';
            setHidden(reasonOther, !showOther);
            if (showOther) {
                reasonOther.focus();
            } else {
                reasonOther.value = '';
            }
            updateSubmitState();
        }

        function currentReason() {
            if (reasonSelect.value === 'other') {
                return reasonOther.value.trim();
            }
            return reasonSelect.value;
        }

        function cleanup(result) {
            setHidden(modal, true);
            unlockScroll();
            titleEl.textContent = 'Are you sure?';
            msgEl.textContent = '';
            cancelBtn.textContent = 'Go Back';
            confirmBtn.textContent = 'Yes, Cancel';
            confirmBtn.disabled = false;
            extraEl.innerHTML = '';
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            closeBtn?.removeEventListener('click', onCancel);
            reasonSelect.removeEventListener('change', onReasonChange);
            reasonOther.removeEventListener('input', updateSubmitState);
            resolve(result);
        }

        function onConfirm() {
            const reason = currentReason();
            if (!reason) return;
            cleanup({ confirmed: true, reason });
        }

        function onCancel() {
            cleanup({ confirmed: false, reason: '' });
        }

        reasonSelect.addEventListener('change', onReasonChange);
        reasonOther.addEventListener('input', updateSubmitState);
        confirmBtn.addEventListener('click', onConfirm);
        cancelBtn.addEventListener('click', onCancel);
        closeBtn?.addEventListener('click', onCancel);
        setHidden(modal, false);
        lockScroll();
        reasonSelect.focus();
    });
}

async function saveCancellationFeedback(reason) {
    try {
        const response = await fetch('app/profileActions.php?action=save_cancellation_feedback', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                subscription_id: selectedSubscription?.id || '',
                reason,
                cancel_item_ids: selectedCancelItemIds
            })
        });
        const result = await readJsonResponse(response);

        if (!result.success) {
            showNotification(result.message || 'Cancellation saved, but feedback was not saved.', 'error');
        }
    } catch (error) {
        showNotification(friendlyRequestError(error, 'Cancellation saved, but feedback was not saved.'), 'error');
    }
}

// ============================================
// DELETE ACCOUNT MODAL
// ============================================

const deleteAccountBtn = document.getElementById('delete-account-btn');
const deleteAccountCancelBtn = document.getElementById('delete-account-cancel-btn');
const deleteConfirmInput = document.getElementById('delete-confirmation-input');
const confirmDeleteBtn = document.getElementById('confirm-delete-btn');

if (deleteAccountBtn) {
    deleteAccountBtn.addEventListener('click', () => {
        if (deleteConfirmInput) {
            deleteConfirmInput.value = '';
        }
        if (confirmDeleteBtn) {
            confirmDeleteBtn.disabled = true;
        }
        openModal('delete-account-modal');
    });
}

if (deleteConfirmInput) {
    deleteConfirmInput.addEventListener('input', (e) => {
        if (confirmDeleteBtn?.dataset.blockedBySubscriptions === '1') {
            confirmDeleteBtn.disabled = true;
            return;
        }

        confirmDeleteBtn.disabled = e.target.value !== 'DELETE';
    });
}

if (deleteAccountCancelBtn) {
    deleteAccountCancelBtn.addEventListener('click', () => closeModal('delete-account-modal'));
}

if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', async () => {
        if (confirmDeleteBtn.dataset.blockedBySubscriptions === '1') {
            showNotification('Cancel active promotions before deleting your account.', 'error');
            return;
        }

        if (deleteConfirmInput.value !== 'DELETE') {
            showNotification('Please type DELETE to confirm', 'error');
            return;
        }
        
        try {
            const response = await fetch('app/profileActions.php?action=delete_account', {
                method: 'POST'
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Account deleted. Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = './';
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
// DRAG TO SCROLL — profile-tabs
// ============================================

(function () {
    const tabs = document.querySelector('.profile-tabs');
    if (!tabs) return;

    let isDown     = false;
    let startX     = 0;
    let scrollLeft = 0;
    let hasDragged = false;

    tabs.addEventListener('mousedown', (e) => {
        isDown     = true;
        hasDragged = false;
        startX     = e.pageX - tabs.offsetLeft;
        scrollLeft = tabs.scrollLeft;
        tabs.classList.add('is-dragging');
    });

    document.addEventListener('mouseup', () => {
        isDown = false;
        tabs.classList.remove('is-dragging');
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x    = e.pageX - tabs.offsetLeft;
        const walk = x - startX;
        if (Math.abs(walk) > 5) hasDragged = true;
        tabs.scrollLeft = scrollLeft - walk;
    });

    // Zabráni kliknutiu na tab ak používateľ ťahal
    tabs.addEventListener('click', (e) => {
        if (hasDragged) e.stopPropagation();
    }, true);
})();
