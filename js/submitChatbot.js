/**
 * submitChatbot.js
 * Handles: logo drag & drop preview, form validation, AJAX submit
 */

document.addEventListener('DOMContentLoaded', () => {
    setupLogoUpload();
    setupCharCounter();
    setupFormSubmit();
});

// ── Logo upload & preview ─────────────────────────────────────────────────────
function setupLogoUpload() {
    const area        = document.getElementById('logoUploadArea');
    const input       = document.getElementById('logoFile');
    const placeholder = document.getElementById('logoPlaceholder');
    const preview     = document.getElementById('logoPreview');
    const previewImg  = document.getElementById('logoPreviewImg');
    const removeBtn   = document.getElementById('logoRemoveBtn');

    if (!area) return;

    // Click on area triggers file input (input already covers area via z-index)
    // Drag & drop
    area.addEventListener('dragover', e => {
        e.preventDefault();
        area.classList.add('sc-logo-upload--dragover');
    });

    area.addEventListener('dragleave', () => {
        area.classList.remove('sc-logo-upload--dragover');
    });

    area.addEventListener('drop', e => {
        e.preventDefault();
        area.classList.remove('sc-logo-upload--dragover');
        const file = e.dataTransfer.files[0];
        if (file) handleLogoFile(file, input, placeholder, preview, previewImg);
    });

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (file) handleLogoFile(file, input, placeholder, preview, previewImg);
    });

    removeBtn.addEventListener('click', e => {
        e.stopPropagation();
        input.value      = '';
        previewImg.src   = '';
        preview.style.display      = 'none';
        placeholder.style.display  = 'flex';
        clearError('logo');
    });
}

function handleLogoFile(file, input, placeholder, preview, previewImg) {
    const allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    const maxBytes = 5 * 1024 * 1024;

    if (!allowed.includes(file.type)) {
        showError('logo', 'Invalid file type. Please use JPG, PNG, WebP or GIF.');
        return;
    }

    if (file.size > maxBytes) {
        showError('logo', 'File is too large. Maximum size is 5MB.');
        return;
    }

    clearError('logo');

    // Show preview
    const reader = new FileReader();
    reader.onload = e => {
        previewImg.src            = e.target.result;
        placeholder.style.display = 'none';
        preview.style.display     = 'flex';
    };
    reader.readAsDataURL(file);
}

// ── Character counter ─────────────────────────────────────────────────────────
function setupCharCounter() {
    const textarea = document.getElementById('accessNote');
    const counter  = document.getElementById('accessNoteCount');
    if (!textarea || !counter) return;

    textarea.addEventListener('input', () => {
        counter.textContent = textarea.value.length;
    });
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
        submitText.style.display   = 'none';
        submitLoader.style.display = 'inline';

        try {
            const formData = buildFormData();
            const res      = await fetch('../api/submitChatbot.php', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (data.success) {
                document.getElementById('scForm').style.display    = 'none';
                document.getElementById('scSuccess').style.display = 'block';
                document.getElementById('scSuccess').scrollIntoView({ behavior: 'smooth' });
            } else {
                document.getElementById('scGlobalError').textContent =
                    data.message || 'Something went wrong. Please try again.';
                submitBtn.disabled         = false;
                submitText.style.display   = 'inline';
                submitLoader.style.display = 'none';
            }

        } catch (err) {
            document.getElementById('scGlobalError').textContent =
                'Network error. Please check your connection and try again.';
            submitBtn.disabled         = false;
            submitText.style.display   = 'inline';
            submitLoader.style.display = 'none';
            console.error('Submit error:', err);
        }
    });
}

function buildFormData() {
    const fd = new FormData();
    fd.append('name',             document.getElementById('chatbotName').value.trim());
    fd.append('website_url',      document.getElementById('websiteUrl').value.trim());
    fd.append('access_note',      document.getElementById('accessNote').value.trim());
    fd.append('access_agreement', document.getElementById('accessAgreement').checked ? '1' : '0');

    const logoInput = document.getElementById('logoFile');
    if (logoInput.files[0]) {
        fd.append('logo', logoInput.files[0]);
    }

    return fd;
}

// ── Validation ────────────────────────────────────────────────────────────────
function validateForm() {
    let valid = true;

    // Name
    const name = document.getElementById('chatbotName').value.trim();
    if (!name) {
        showError('name', 'Chatbot name is required.');
        valid = false;
    } else if (name.length > 200) {
        showError('name', 'Chatbot name cannot exceed 200 characters.');
        valid = false;
    }

    // URL
    const url = document.getElementById('websiteUrl').value.trim();
    if (!url) {
        showError('website', 'Website URL is required.');
        valid = false;
    } else if (!isValidUrl(url)) {
        showError('website', 'Please enter a valid URL (e.g. https://example.com).');
        valid = false;
    }

    // Access note
    const note = document.getElementById('accessNote').value.trim();
    if (!note) {
        showError('access', 'Please describe how we can access your chatbot.');
        valid = false;
    } else if (note.length > 1000) {
        showError('access', 'Access note cannot exceed 1000 characters.');
        valid = false;
    }

    // Agreement
    if (!document.getElementById('accessAgreement').checked) {
        showError('agreement', 'You must agree to provide access for testing.');
        valid = false;
    }

    return valid;
}

function isValidUrl(str) {
    try {
        const u = new URL(str);
        return u.protocol === 'http:' || u.protocol === 'https:';
    } catch {
        return false;
    }
}

// ── Error helpers ─────────────────────────────────────────────────────────────
function showError(field, message) {
    const errorEl = document.getElementById('error-' + field);
    const inputEl = document.getElementById(
        field === 'name'      ? 'chatbotName'      :
        field === 'website'   ? 'websiteUrl'        :
        field === 'access'    ? 'accessNote'        :
        field === 'logo'      ? 'logoFile'          : null
    );

    if (errorEl) errorEl.textContent = message;
    if (inputEl) inputEl.classList.add('sc-input--error', 'sc-textarea--error');
}

function clearError(field) {
    const errorEl = document.getElementById('error-' + field);
    if (errorEl) errorEl.textContent = '';

    const inputEl = document.getElementById(
        field === 'name'    ? 'chatbotName' :
        field === 'website' ? 'websiteUrl'  :
        field === 'access'  ? 'accessNote'  : null
    );
    if (inputEl) inputEl.classList.remove('sc-input--error', 'sc-textarea--error');
}

function clearAllErrors() {
    ['name', 'website', 'access', 'logo', 'agreement'].forEach(clearError);
    document.getElementById('scGlobalError').textContent = '';
}

// ── Live validation on blur ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const chatbotName = document.getElementById('chatbotName');
    const websiteUrl  = document.getElementById('websiteUrl');
    const accessNote  = document.getElementById('accessNote');

    if (chatbotName) {
        chatbotName.addEventListener('blur', () => {
            if (chatbotName.value.trim()) clearError('name');
        });
    }

    if (websiteUrl) {
        websiteUrl.addEventListener('blur', () => {
            const val = websiteUrl.value.trim();
            if (!val) return;
            if (!isValidUrl(val)) {
                showError('website', 'Please enter a valid URL (e.g. https://example.com).');
            } else {
                clearError('website');
            }
        });
    }

    if (accessNote) {
        accessNote.addEventListener('blur', () => {
            if (accessNote.value.trim()) clearError('access');
        });
    }
});