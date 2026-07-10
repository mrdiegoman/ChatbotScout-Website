/**
 * submitChatbot.js — Affiliate Partnership Request Form
 */

document.addEventListener('DOMContentLoaded', () => {
    setupCharCounter();
    setupFormSubmit();
});

function setHidden(element, hidden) {
    if (element) element.hidden = hidden;
}

// ── Character counter ────────────────────────────────────────────────────────
function setupCharCounter() {
    const textarea = document.getElementById('message');
    const counter  = document.getElementById('messageCount');
    if (!textarea || !counter) return;

    textarea.addEventListener('input', () => {
        counter.textContent = textarea.value.length;
        counter.classList.toggle('sc-char-count__value--warning', textarea.value.length > 1800);
    });
}

// ── Validation ───────────────────────────────────────────────────────────────
function showError(fieldId, msg) {
    const errEl = document.getElementById('error-' + fieldId);
    const input = document.getElementById(
        fieldId === 'contact-name' ? 'contactName'  :
        fieldId === 'company'      ? 'companyName'  :
        fieldId === 'email'        ? 'contactEmail' :
        fieldId === 'name'         ? 'chatbotName'  :
        fieldId === 'url'          ? 'websiteUrl'   :
        fieldId === 'message'      ? 'message'      : fieldId
    );
    if (errEl) errEl.textContent = msg;
    if (input) input.classList.add('sc-input--error', 'sc-textarea--error');
}

function clearError(fieldId) {
    const errEl = document.getElementById('error-' + fieldId);
    const input = document.getElementById(
        fieldId === 'contact-name' ? 'contactName'  :
        fieldId === 'company'      ? 'companyName'  :
        fieldId === 'email'        ? 'contactEmail' :
        fieldId === 'name'         ? 'chatbotName'  :
        fieldId === 'url'          ? 'websiteUrl'   :
        fieldId === 'message'      ? 'message'      : fieldId
    );
    if (errEl) errEl.textContent = '';
    if (input) input.classList.remove('sc-input--error', 'sc-textarea--error');
}

function clearAllErrors() {
    ['contact-name', 'company', 'email', 'name', 'url', 'message'].forEach(clearError);
    const global = document.getElementById('scGlobalError');
    if (global) global.textContent = '';
}

function validateForm() {
    let valid = true;

    const contactName  = document.getElementById('contactName')?.value.trim()  || '';
    const companyName  = document.getElementById('companyName')?.value.trim()  || '';
    const contactEmail = document.getElementById('contactEmail')?.value.trim() || '';
    const chatbotName  = document.getElementById('chatbotName')?.value.trim()  || '';
    const websiteUrl   = document.getElementById('websiteUrl')?.value.trim()   || '';
    const message      = document.getElementById('message')?.value.trim()      || '';

    if (!contactName) {
        showError('contact-name', 'Your name is required.');
        valid = false;
    }

    if (!companyName) {
        showError('company', 'Company / product name is required.');
        valid = false;
    }

    if (!contactEmail) {
        showError('email', 'Contact email is required.');
        valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactEmail)) {
        showError('email', 'Please enter a valid email address.');
        valid = false;
    }

    if (!chatbotName) {
        showError('name', 'Chatbot name is required.');
        valid = false;
    }

    if (!websiteUrl) {
        showError('url', 'Chatbot website URL is required.');
        valid = false;
    } else {
        try { new URL(websiteUrl); } catch {
            showError('url', 'Please enter a valid URL (e.g. https://example.com).');
            valid = false;
        }
    }

    if (!message) {
        showError('message', 'Please tell us about your chatbot.');
        valid = false;
    }

    return valid;
}

// ── Form submit ───────────────────────────────────────────────────────────────
function setupFormSubmit() {
    const form = document.getElementById('scForm');
    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();
        clearAllErrors();
        if (!validateForm()) return;

        const submitBtn    = document.getElementById('scSubmitBtn');
        const submitText   = document.getElementById('scSubmitText');
        const submitLoader = document.getElementById('scSubmitLoader');

        submitBtn.disabled         = true;
        setHidden(submitText, true);
        setHidden(submitLoader, false);

        try {
            const payload = {
                contact_name:  document.getElementById('contactName').value.trim(),
                company_name:  document.getElementById('companyName').value.trim(),
                contact_email: document.getElementById('contactEmail').value.trim(),
                name:          document.getElementById('chatbotName').value.trim(),
                website_url:   document.getElementById('websiteUrl').value.trim(),
                message:       document.getElementById('message').value.trim(),
            };

            const res  = await fetch('api/submitChatbot.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload)
            });

            const data = await res.json();

            if (data.success) {
                setHidden(document.getElementById('scForm'), true);
                setHidden(document.getElementById('scSuccess'), false);
                document.getElementById('scSuccess').scrollIntoView({ behavior: 'smooth' });
            } else {
                document.getElementById('scGlobalError').textContent =
                    data.message || 'Something went wrong. Please try again.';
                submitBtn.disabled         = false;
                setHidden(submitText, false);
                setHidden(submitLoader, true);
            }

        } catch (err) {
            document.getElementById('scGlobalError').textContent =
                'Network error. Please check your connection and try again.';
            submitBtn.disabled         = false;
            setHidden(submitText, false);
            setHidden(submitLoader, true);
        }
    });
}
