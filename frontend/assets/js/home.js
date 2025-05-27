document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            fetch('/api/auth/logout', { method: 'POST' })
                .then(() => {
                    router.navigate('login');
                });
        });
    }

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

    window.loadUserInfo = function() {
        fetch('/api/auth/me')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.user) {
                    document.querySelector('.home-dashboard h2').textContent = `Welcome back, ${data.user.first_name}!`;
                    document.getElementById('profile-name-age').textContent = `${data.user.first_name}, ${getAge(data.user.date_birth)}`;
                    document.getElementById('profile-description').textContent = data.user.description || '';
                    document.getElementById('profile-photo').src = data.user.pfp_path || '/assets/img/sample-profile.jpg';
                }
            });
    };

    if (typeof window.loadUserInfo === 'function') window.loadUserInfo();
});