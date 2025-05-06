class LoginManager {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        this.attemptCount = 3;
        this.initEventListeners();
    }

    initEventListeners() {
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.validateLogin();
        });
    }

    validateLogin() {
        const email = this.form.querySelector('input[name="username"]').value.trim();
        const password = this.form.querySelector('input[name="password"]').value;

        if (!email || !password) {
            this.showError('Please enter both email and password');
            return;
        }

        if (!this.isValidEmail(email)) {
            this.showError('Please enter a valid email address');
            return;
        }

        this.authenticateUser(email, password);
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    authenticateUser(email, password) {
        fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.handleSuccessfulLogin(data);
                } else {
                    this.handleFailedLogin(data.message);
                }
            })
            .catch(error => {
                this.showError('Connection error. Please try again later.');
                console.error('Login error:', error);
            });
    }

    handleSuccessfulLogin(data) {
        if (data.token) {
            localStorage.setItem('authToken', data.token);
        }
        alert("Login was successful");
        window.location.href = '/dashboard';
    }

    handleFailedLogin(message) {
        this.attemptCount--;

        if (this.attemptCount <= 0) {
            this.showError('Too many failed attempts. Please try again later.');
            this.disableForm();
        } else {
            const tryText = this.attemptCount === 1 ? 'try' : 'tries';
            this.showError(`Wrong password or email. ${this.attemptCount} ${tryText} remaining.`);
        }
    }

    showError(message) {
        let errorElement = document.querySelector('.login-error');

        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'login-error';
            this.form.prepend(errorElement);
        }

        errorElement.textContent = message;
        errorElement.style.color = 'red';
        errorElement.style.marginBottom = '10px';
    }

    disableForm() {
        const inputs = this.form.querySelectorAll('input');
        const submitBtn = this.form.querySelector('input[type="submit"]');

        inputs.forEach(input => input.disabled = true);
        submitBtn.disabled = true;
    }
}

// Initialize login manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new LoginManager('form[name="login"]');
});