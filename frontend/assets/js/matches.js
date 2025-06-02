window.loadMatches = function() {
    const list = document.getElementById('matches-list');
    if (!list) return;

    // fetch real matches from API
    fetch('/api/matches')
        .then(res => res.ok ? res.json() : Promise.reject())
        .then(data => {
            if (data.success && Array.isArray(data.matches)) {
                renderMatches(data.matches);
            } else {
                renderMatches([]);
            }
        })
        .catch(() => {
            // Fallback: dummy data
            const dummy = [
                { name: "Alex", age: 25, description: "Loves hiking", photo: "/assets/img/sample-profile.jpg" },
                { name: "Jamie", age: 28, description: "Coffee enthusiast", photo: "/assets/img/sample-profile.jpg" }
            ];
            renderMatches(dummy);
        });

    function renderMatches(matches) {
        if (!matches.length) {
            list.innerHTML = "<p>No matches found yet.</p>";
            return;
        }
        list.innerHTML = matches.map(m =>
            `<div class="match-card">
                <img src="${m.photo || '/assets/img/sample-profile.jpg'}" class="profile-photo" alt="Profile photo">
                <div class="profile-info">
                    <h3>${m.name || 'Unknown'}, ${m.age || ''}</h3>
                    <p>${m.description || ''}</p>
                </div>
            </div>`
        ).join('');
        if (typeof setupLogoutButton === 'function') setupLogoutButton();
    }
};