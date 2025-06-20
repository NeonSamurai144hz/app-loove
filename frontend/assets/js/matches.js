class MatchesManager {
    constructor() {
        this.matchesContainer = document.getElementById('matches-container');
        this.findMatchBtn = document.getElementById('find-match-btn');
        this.init();
    }

    init() {
        this.loadMatches();

        // Setup find match button
        if (this.findMatchBtn) {
            this.findMatchBtn.addEventListener('click', async () => {
                // Check permissions before navigating
                const hasPermissions = await this.checkMediaPermissions();

                if (hasPermissions) {
                    router.navigate('video-chat');
                } else {
                    alert('Camera and microphone access is required for video chat. Please allow access in your browser settings and try again.');
                }
            });
        }
    }

    async checkMediaPermissions() {
        try {
            // Request camera and microphone access
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: true
            });

            // If successful, stop all tracks immediately (we just needed to check permissions)
            stream.getTracks().forEach(track => track.stop());
            return true;
        } catch (error) {
            console.error('Media permission error:', error);
            return false;
        }
    }

    async loadMatches() {
        try {
            const response = await fetch('/api/matches');
            const data = await response.json();

            if (!data.success) {
                this.showError('Failed to load matches');
                return;
            }

            this.renderMatches(data.matches || []);
        } catch (error) {
            console.error('Error loading matches:', error);
            this.showError('Connection error. Please try again.');
        }
    }

    renderMatches(matches) {
        if (!this.matchesContainer) return;

        if (matches.length === 0) {
            this.matchesContainer.innerHTML = `
                <div class="no-matches">
                    <p>You don't have any matches yet.</p>
                    <p>Use the "Find Video Match" button to start matching!</p>
                </div>
            `;
            return;
        }

        this.matchesContainer.innerHTML = matches.map(match => `
            <div class="match-card" data-match-id="${match.match_id}">
                <div class="match-photo">
                    <img src="${match.pfp_path || '/assets/img/sample-profile.jpg'}" alt="${match.first_name}">
                </div>
                <div class="match-info">
                    <h3>${match.first_name} ${match.last_name}</h3>
                    <p class="match-age">${this.calculateAge(match.date_birth)} years old</p>
                    <p class="match-description">${match.description || 'No description available'}</p>
                </div>
                <div class="match-actions">
                    <button class="chat-btn" data-match-id="${match.match_id}">
                        <i class="fas fa-comment"></i> Chat
                    </button>
                </div>
            </div>
        `).join('');

        // Add event listeners to chat buttons
        document.querySelectorAll('.chat-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const matchId = button.getAttribute('data-match-id');
                this.startChat(matchId);
            });
        });
    }

    calculateAge(dateOfBirth) {
        if (!dateOfBirth) return 'Unknown';
        const birthDate = new Date(dateOfBirth);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }

        return age;
    }

    startChat(matchId) {
        fetch(`/api/chats/create/${matchId}`, {
            method: 'POST'
        })
            .then(res => res.json())
            .then(() => {
                router.navigate('chat');
            })
            .catch(err => {
                console.error('Error starting chat:', err);
                this.showError('Failed to start chat. Please try again.');
            });
    }

    showError(message) {
        if (!this.matchesContainer) return;

        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.textContent = message;

        this.matchesContainer.innerHTML = '';
        this.matchesContainer.appendChild(errorElement);
    }
}

// Initialize matches functionality when DOM is loaded
window.loadMatches = function() {
    new MatchesManager();
};