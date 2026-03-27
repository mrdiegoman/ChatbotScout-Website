/**
 * Auth Forms Live Validation
 * 
 * Validuje inputy na blur (keď user opustí pole) a pri submite
 * Zobrazuje error messages pod inputmi a pridáva červený border
 */

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.querySelector('form[action*="login.php"]');
    const signUpForm = document.querySelector('form[action*="signUp.php"]');
    
    if (loginForm) {
        initLoginValidation(loginForm);
    }
    
    if (signUpForm) {
        initSignUpValidation(signUpForm);
    }
});

// ============================================
// LOGIN FORM VALIDATION
// ============================================

function initLoginValidation(form) {
    const emailInput = form.querySelector('#email');
    const passwordInput = form.querySelector('#password');
    
    // Blur validation
    if (emailInput) {
        emailInput.addEventListener('blur', () => validateEmail(emailInput));
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('blur', () => validatePassword(passwordInput));
    }
    
    // Submit validation
    form.addEventListener('submit', (e) => {
        let isValid = true;
        
        if (emailInput && !validateEmail(emailInput)) {
            isValid = false;
        }
        
        if (passwordInput && !validatePassword(passwordInput)) {
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault(); // Zastav submit
        }
    });
}

// ============================================
// SIGN UP FORM VALIDATION
// ============================================

function initSignUpValidation(form) {
    const firstNameInput = form.querySelector('#first_name');
    const lastNameInput = form.querySelector('#last_name');
    const emailInput = form.querySelector('#email');
    const passwordInput = form.querySelector('#password');
    const termsCheckbox = form.querySelector('#terms_accepted');
    
    // Blur validation
    if (firstNameInput) {
        firstNameInput.addEventListener('blur', () => validateName(firstNameInput, 'First name'));
    }
    
    if (lastNameInput) {
        lastNameInput.addEventListener('blur', () => validateName(lastNameInput, 'Last name'));
    }
    
    if (emailInput) {
        emailInput.addEventListener('blur', () => validateEmail(emailInput));
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('blur', () => validatePassword(passwordInput));
    }
    
    // Submit validation
    form.addEventListener('submit', (e) => {
        let isValid = true;
        
        if (firstNameInput && !validateName(firstNameInput, 'First name')) {
            isValid = false;
        }
        
        if (lastNameInput && !validateName(lastNameInput, 'Last name')) {
            isValid = false;
        }
        
        if (emailInput && !validateEmail(emailInput)) {
            isValid = false;
        }
        
        if (passwordInput && !validatePassword(passwordInput)) {
            isValid = false;
        }
        
        if (termsCheckbox && !validateTerms(termsCheckbox)) {
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault(); // Zastav submit
        }
    });

    termsCheckbox.addEventListener('click', () => validateTerms(termsCheckbox));
}

// ============================================
// VALIDATION FUNCTIONS
// ============================================

function validateEmail(input) {
    const value = input.value.trim();
    
    if (value === '') {
        showError(input, 'Email is required.');
        return false;
    }
    
    // Simple email regex
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(value)) {
        showError(input, 'Please enter a valid email address.');
        return false;
    }
    
    clearError(input);
    return true;
}

function validatePassword(input) {
    const value = input.value;
    
    if (value === '') {
        showError(input, 'Password is required.');
        return false;
    }
    
    if (value.length < 8) {
        showError(input, 'Password must be at least 8 characters long.');
        return false;
    }
    
    clearError(input);
    return true;
}

function validateName(input, fieldName) {
    const value = input.value.trim();
    
    if (value === '') {
        showError(input, `${fieldName} is required.`);
        return false;
    }
    
    if (value.length > 50) {
        showError(input, `${fieldName} is too long (maximum 50 characters).`);
        return false;
    }
    
    clearError(input);
    return true;
}

function validateTerms(checkbox) {
    const container = checkbox.closest('.checkbox-container') || checkbox.closest('.checkbox-group');
    
    if (!checkbox.checked) {
        showErrorForCheckbox(container, 'This field is required.');
        return false;
    }
    
    clearErrorForCheckbox(container);
    return true;
}

// ============================================
// ERROR DISPLAY FUNCTIONS
// ============================================

function showError(input, message) {
    // Pridaj error class na input
    input.classList.add('error');
    
    // Nájdi alebo vytvor error message element
    const formGroup = input.closest('.form-group');
    let errorElement = formGroup.querySelector('.error-message.js-error');
    
    if (!errorElement) {
        errorElement = document.createElement('small');
        errorElement.className = 'error-message js-error';
        
        // Vlož za input alebo password-wrapper
        const wrapper = formGroup.querySelector('.password-wrapper');
        if (wrapper) {
            wrapper.insertAdjacentElement('afterend', errorElement);
        } else {
            input.insertAdjacentElement('afterend', errorElement);
        }
    }
    
    errorElement.textContent = message;
}

function clearError(input) {
    // Odstráň error class
    input.classList.remove('error');
    
    // Odstráň JS-generated error message (nechaj PHP errory)
    const formGroup = input.closest('.form-group');
    const jsError = formGroup.querySelector('.error-message.js-error');
    
    if (jsError) {
        jsError.remove();
    }
}

function showErrorForCheckbox(container, message) {
    // Nájdi alebo vytvor error message
    let errorElement = container.querySelector('.error-message.js-error');
    
    if (!errorElement) {
        errorElement = document.createElement('small');
        errorElement.className = 'error-message js-error';
        container.appendChild(errorElement);
    }
    
    errorElement.textContent = message;
}

function clearErrorForCheckbox(container) {
    const jsError = container.querySelector('.error-message.js-error');
    if (jsError) {
        jsError.remove();
    }
}