class RegisterManager {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        if (!this.form) {
            console.error('Register form not found');
            return;
        }
        this.initEventListeners();
    }

    initEventListeners() {
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.validateRegistration();
        });
    }

    validateRegistration() {
        // Get form data
        const firstName = this.form.querySelector('input[name="firstName"]').value.trim();
        const lastName = this.form.querySelector('input[name="lastName"]').value.trim();
        const email = this.form.querySelector('input[name="email"]').value.trim();
        const password = this.form.querySelector('input[name="password"]').value;
        const confirmPassword = this.form.querySelector('input[name="confirmPassword"]').value;

        // Basic validation
        if (!firstName || !lastName || !email || !password || !confirmPassword) {
            this.showError('All fields are required');
            return;
        }

        if (!this.isValidEmail(email)) {
            this.showError('Please enter a valid email address');
            return;
        }

        if (password !== confirmPassword) {
            this.showError('Passwords do not match');
            return;
        }

        if (password.length < 6) {
            this.showError('Password must be at least 6 characters');
            return;
        }

        // Submit registration data
        this.submitRegistration({
            firstName,
            lastName,
            email,
            password,
            confirmPassword
        });
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    submitRegistration(userData) {
        fetch('/api/auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.handleSuccessfulRegistration(data);
                } else {
                    this.showError(data.message || 'Registration failed');
                }
            })
            .catch(error => {
                this.showError('Connection error. Please try again later.');
                console.error('Registration error:', error);
            });
    }

    handleSuccessfulRegistration(data) {
        alert('Registration successful! Please log in.');
        router.navigate('login');
    }

    showError(message) {
        let errorElement = document.querySelector('.register-error');

        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'register-error';
            this.form.prepend(errorElement);
        }

        errorElement.textContent = message;
        errorElement.style.color = 'red';
        errorElement.style.marginBottom = '10px';
    }
}