class ProfileManager {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        this.photoInput = this.form.querySelector('input[name="profilePhoto"]');
        this.photoPreview = document.getElementById('profile-photo-preview');
        this.cancelBtn = document.getElementById('cancel-profile-btn');
        this.init();
    }

    init() {
        this.loadProfile();
        this.photoInput.addEventListener('change', (e) => this.handlePhotoChange(e));
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        if (this.cancelBtn) {
            this.cancelBtn.addEventListener('click', () => router.navigate('home'));
        }
    }

    loadProfile() {
        fetch('/api/auth/me')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.user) {
                    const u = data.user;
                    this.form.querySelector('input[name="first_name"]').value = u.first_name || '';
                    this.form.querySelector('input[name="last_name"]').value = u.last_name || '';
                    this.form.querySelector('input[name="email"]').value = u.email || '';
                    this.form.querySelector('input[name="date_birth"]').value = u.date_birth || '';
                    this.form.querySelector('select[name="gender"]').value = u.gender || '';
                    this.form.querySelector('select[name="gender_attraction"]').value = u.gender_attraction || '';
                    this.form.querySelector('input[name="age_attraction_min"]').value = u.age_attraction_min || 18;
                    this.form.querySelector('input[name="age_attraction_max"]').value = u.age_attraction_max || 99;
                    this.form.querySelector('textarea[name="description"]').value = u.description || '';
                    if (u.pfp_path) {
                        this.photoPreview.src = u.pfp_path;
                        this.photoPreview.style.display = '';
                    }
                }
            });
    }

    handlePhotoChange(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = ev => {
                this.photoPreview.src = ev.target.result;
                this.photoPreview.style.display = '';
            };
            reader.readAsDataURL(file);
        }
    }

    handleSubmit(e) {
        e.preventDefault();
        const formData = new FormData(this.form);
        fetch('/api/auth/updateProfile', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                const err = this.form.querySelector('.profile-edit-error');
                if (data.success) {
                    err.textContent = 'Profile updated!';
                    err.style.color = 'green';
                } else {
                    err.textContent = data.message || 'Update failed';
                    err.style.color = 'red';
                }
            })
            .catch(() => {
                const err = this.form.querySelector('.profile-edit-error');
                err.textContent = 'Connection error';
                err.style.color = 'red';
            });
    }
}

// Usage example (replace window.initProfileEdit):
window.initProfileEdit = function() {
    new ProfileManager('#edit-profile-form');
};