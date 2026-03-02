document.addEventListener('DOMContentLoaded', function() {
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Nájdi wrapper a v ňom input
            const wrapper = this.closest('.password-wrapper');
            const passwordInput = wrapper.querySelector('input');
            
            // Toggle password visibility
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.classList.add('active');
            } else {
                passwordInput.type = 'password';
                this.classList.remove('active');
            }
        });
    });
});