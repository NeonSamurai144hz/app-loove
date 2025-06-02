// frontend/assets/js/profile.js
window.initProfileEdit = function() {
    fetch('/api/auth/me')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.user) {
                const u = data.user;
                document.querySelector('input[name="first_name"]').value = u.first_name || '';
                document.querySelector('input[name="last_name"]').value = u.last_name || '';
                document.querySelector('input[name="email"]').value = u.email || '';
                document.querySelector('input[name="date_birth"]').value = u.date_birth || '';
                document.querySelector('select[name="gender"]').value = u.gender || '';
                document.querySelector('select[name="gender_attraction"]').value = u.gender_attraction || '';
                document.querySelector('input[name="age_attraction_min"]').value = u.age_attraction_min || 18;
                document.querySelector('input[name="age_attraction_max"]').value = u.age_attraction_max || 99;
                document.querySelector('textarea[name="description"]').value = u.description || '';
                if (u.pfp_path) {
                    const img = document.getElementById('profile-photo-preview');
                    img.src = u.pfp_path;
                    img.style.display = '';
                }
            }
        });

    document.querySelector('input[name="profilePhoto"]').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = ev => {
                const img = document.getElementById('profile-photo-preview');
                img.src = ev.target.result;
                img.style.display = '';
            };
            reader.readAsDataURL(file);
        }
    });

    document.getElementById('edit-profile-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        fetch('/api/auth/updateProfile', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                const err = form.querySelector('.profile-edit-error');
                if (data.success) {
                    err.textContent = 'Profile updated!';
                    err.style.color = 'green';
                } else {
                    err.textContent = data.message || 'Update failed';
                    err.style.color = 'red';
                }
            })
            .catch(() => {
                const err = form.querySelector('.profile-edit-error');
                err.textContent = 'Connection error';
                err.style.color = 'red';
            });
    });

    // Add this inside window.initProfileEdit = function() { ... }
    const cancelBtn = document.getElementById('cancel-profile-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            router.navigate('home');
        });
    }
};