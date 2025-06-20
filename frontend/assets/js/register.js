class RegisterManager {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        if (!this.form) {
            console.error('Register form not found');
            return;
        }
        this.step1 = this.form.querySelector('#register-step-1');
        this.step2 = this.form.querySelector('#register-step-2');
        this.step3 = this.form.querySelector('#register-step-3');
        this.nextBtn = this.form.querySelector('#next-btn');
        this.beforeBtn = this.form.querySelector('#before-btn');
        this.nextPhotoBtn = this.form.querySelector('#next-photo-btn');
        this.registerBtn = this.form.querySelector('#register-btn');
        this.photoInput = this.form.querySelector('#profile-photo-input');
        this.photoCircle = this.form.querySelector('#photo-upload-circle');
        this.photoIcon = this.form.querySelector('#profile-photo-icon');
        this.photoPreview = this.form.querySelector('#profile-photo-preview');
        this.initEventListeners();
        // Set required attributes for step 1 only
        this.setStepRequired(this.step1, true);
        this.setStepRequired(this.step2, false);
        this.setStepRequired(this.step3, false);
    }

    setStepRequired(step, required) {
        if (!step) return;
        step.querySelectorAll('input, select, textarea').forEach(el => {
            if (required) {
                el.setAttribute('required', 'required');
            } else {
                el.removeAttribute('required');
            }
        });
    }

    initEventListeners() {
        this.nextBtn.addEventListener('click', () => this.handleNext());
        this.beforeBtn.addEventListener('click', () => this.handleBefore());
        this.nextPhotoBtn.addEventListener('click', () => this.handleNextPhoto());
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.validateAndSubmit();
        });
        if (this.photoCircle) {
            this.photoCircle.addEventListener('click', () => this.photoInput.click());
        }
        if (this.photoInput) {
            this.photoInput.addEventListener('change', (e) => this.handlePhotoChange(e));
        }
    }

    handleNext() {
        // Validate step 1
        const firstName = this.form.querySelector('input[name="firstName"]').value.trim();
        const lastName = this.form.querySelector('input[name="lastName"]').value.trim();
        const email = this.form.querySelector('input[name="email"]').value.trim();
        const password = this.form.querySelector('input[name="password"]').value;
        const confirmPassword = this.form.querySelector('input[name="confirmPassword"]').value;

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

        // Toggle required attributes
        this.setStepRequired(this.step1, false);
        this.setStepRequired(this.step2, true);

        // Show step 2
        this.step1.style.display = 'none';
        this.nextBtn.style.display = 'none';
        this.step2.style.display = '';
        this.beforeBtn.style.display = '';
        this.nextPhotoBtn.style.display = '';
        this.registerBtn.style.display = 'none';
        this.clearError();
    }

    handleNextPhoto() {
        // Validate step 2
        const birthDate = this.form.querySelector('input[name="birthDate"]').value;
        const gender = this.form.querySelector('select[name="gender"]').value;
        const genderAttraction = this.form.querySelector('select[name="genderAttraction"]').value;
        const ageMin = this.form.querySelector('input[name="ageMin"]').value;
        const ageMax = this.form.querySelector('input[name="ageMax"]').value;

        if (!birthDate || !gender || !genderAttraction || !ageMin || !ageMax) {
            this.showError('All fields are required');
            return;
        }
        if (parseInt(ageMin) > parseInt(ageMax)) {
            this.showError('Minimum age cannot be greater than maximum age');
            return;
        }

        // Toggle required attributes
        this.setStepRequired(this.step2, false);
        this.setStepRequired(this.step3, true);

        // Show step 3
        this.step2.style.display = 'none';
        this.nextPhotoBtn.style.display = 'none';
        this.step3.style.display = '';
        this.registerBtn.style.display = '';
        this.beforeBtn.style.display = '';
        this.registerBtn.style.display = '';
        this.clearError();
    }

    handleBefore() {
        if (this.step3 && this.step3.style.display !== 'none') {
            // Go back to step 2
            this.setStepRequired(this.step3, false);
            this.setStepRequired(this.step2, true);
            this.step3.style.display = 'none';
            this.registerBtn.style.display = 'none';
            this.step2.style.display = '';
            this.nextPhotoBtn.style.display = '';
            this.beforeBtn.style.display = '';
        } else if (this.step2 && this.step2.style.display !== 'none') {
            // Go back to step 1
            this.setStepRequired(this.step2, false);
            this.setStepRequired(this.step1, true);
            this.step2.style.display = 'none';
            this.beforeBtn.style.display = 'none';
            this.nextPhotoBtn.style.display = 'none';
            this.step1.style.display = '';
            this.nextBtn.style.display = '';
        }
        this.clearError();
    }

    handlePhotoChange(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => {
                this.photoPreview.src = ev.target.result;
                this.photoPreview.style.display = '';
                this.photoIcon.style.display = 'none';
            };
            reader.readAsDataURL(file);
        } else {
            this.photoPreview.style.display = 'none';
            this.photoIcon.style.display = '';
        }
    }

    validateAndSubmit() {
        // Validate step 2 again for safety
        const birthDate = this.form.querySelector('input[name="birthDate"]').value;
        const gender = this.form.querySelector('select[name="gender"]').value;
        const genderAttraction = this.form.querySelector('select[name="genderAttraction"]').value;
        const ageMin = this.form.querySelector('input[name="ageMin"]').value;
        const ageMax = this.form.querySelector('input[name="ageMax"]').value;

        if (!birthDate || !gender || !genderAttraction || !ageMin || !ageMax) {
            this.showError('All fields are required');
            return;
        }
        if (parseInt(ageMin) > parseInt(ageMax)) {
            this.showError('Minimum age cannot be greater than maximum age');
            return;
        }

        // Gather all data
        const userData = {
            firstName: this.form.querySelector('input[name="firstName"]').value.trim(),
            lastName: this.form.querySelector('input[name="lastName"]').value.trim(),
            email: this.form.querySelector('input[name="email"]').value.trim(),
            password: this.form.querySelector('input[name="password"]').value,
            confirmPassword: this.form.querySelector('input[name="confirmPassword"]').value,
            birthDate,
            gender,
            genderAttraction,
            ageMin,
            ageMax
        };
        const formData = new FormData();
        for (const key in userData) {
            formData.append(key, userData[key]);
        }
        if (this.photoInput && this.photoInput.files[0]) {
            formData.append('profilePhoto', this.photoInput.files[0]);
        }
        this.submitRegistration(formData);
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    submitRegistration(formData) {
        fetch('/api/auth/register', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.handleSuccessfulRegistration(data);
                } else {
                    this.showError(data.message || 'Registration failed');
                }
            })
            .catch(() => {
                this.showError('Connection error. Please try again later.');
            });
    }

    handleSuccessfulRegistration(data) {
        alert('Registration successful! Welcome!');
        router.navigate('home');
    }

    showError(message) {
        let errorElement = this.form.querySelector('.register-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'register-error';
            this.form.prepend(errorElement);
        }
        errorElement.textContent = message;
        errorElement.style.color = 'red';
        errorElement.style.marginBottom = '10px';
    }

    clearError() {
        let errorElement = this.form.querySelector('.register-error');
        if (errorElement) errorElement.remove();
    }
}

// Initialize when view is loaded
document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.querySelector('form[name="register"]');
    if (registerForm) {
        new RegisterManager('form[name="register"]');
    }
});