// app-loove/frontend/assets/js/home.js

function getAge(dateString) {
    if (!dateString) return '';
    const today = new Date();
    const birthDate = new Date(dateString);
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

function setupLogoutButton() {
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn && !logoutBtn.dataset.listener) {
        logoutBtn.addEventListener('click', () => {
            fetch('/api/auth/logout', {
                method: 'POST',
                credentials: 'same-origin'
            })
                .then(res => res.json())
                .then(() => {
                    localStorage.removeItem('authToken');
                    router.navigate('login');
                })
                .catch(() => {
                    localStorage.removeItem('authToken');
                    router.navigate('login');
                });
        });
        logoutBtn.dataset.listener = 'true'; // Prevent duplicate listeners
    }
}

window.loadUserInfo = function() {
    fetch('/api/auth/me')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.user) {
                const h2 = document.querySelector('.home-dashboard h2');
                if (h2) h2.textContent = `Welcome back, ${data.user.first_name}!`;

                const nameAge = document.getElementById('profile-name-age');
                if (nameAge) nameAge.textContent = `${data.user.first_name}, ${getAge(data.user.date_birth)}`;

                const desc = document.getElementById('profile-description');
                if (desc) desc.textContent = data.user.description || '';

                const photo = document.getElementById('profile-photo');
                if (photo) photo.src = data.user.pfp_path || '/assets/img/sample-profile.jpg';
            }
            setupLogoutButton();
        });
};

document.addEventListener('DOMContentLoaded', () => {
    setupLogoutButton();
    if (typeof window.loadUserInfo === 'function') window.loadUserInfo();
});