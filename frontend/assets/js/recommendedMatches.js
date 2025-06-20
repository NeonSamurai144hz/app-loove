
class RecommendedMatches {
    constructor() {
        this.container = document.getElementById('recommended-matches-list');
        this.loadingElement = document.getElementById('recommended-loading');
        this.emptyElement = document.getElementById('recommended-empty');
        this.currentPage = 1;
        this.hasMorePages = true;
        this.chatManager = null;

        this.init();
    }

    init() {
        this.loadRecommendedMatches();

        const refreshBtn = document.getElementById('refresh-recommended-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadRecommendedMatches(false);
            });
        }
    }

    setChatManager(chatManager) {
        this.chatManager = chatManager;
    }

    loadRecommendedMatches(append = false) {
        console.log('loadRecommendedMatches called', append);
        if (!append) {
            this.currentPage = 1;
        }
        if (this.loadingElement) this.loadingElement.style.display = 'block';
        if (this.emptyElement) this.emptyElement.style.display = 'none';

        fetch(`/api/matches/recommended?page=${this.currentPage}`)
            .then(response => response.json())
            .then(data => {
                if (this.loadingElement) this.loadingElement.style.display = 'none';

                if (data.success) {
                    this.hasMorePages = data.hasMore;

                    if (data.matches.length === 0 && !append) {
                        if (this.emptyElement) this.emptyElement.style.display = 'block';
                    } else {
                        this.renderMatches(data.matches, append);
                    }
                }
            })
            .catch(error => {
                console.error('Error loading recommended matches:', error);
                if (this.loadingElement) this.loadingElement.style.display = 'none';
                if (this.emptyElement) {
                    this.emptyElement.style.display = 'block';
                    this.emptyElement.textContent = 'Could not load matches. Please try again later.';
                }
            });
    }

    renderMatches(matches, append = false) {
        if (!this.container) return;

        if (!append) {
            this.container.innerHTML = '';
        }

        matches.forEach(match => {
            const chatItem = document.createElement('div');
            chatItem.className = 'chat-item recommended-match';
            chatItem.setAttribute('data-user-id', match.id);

            // Fix profile photo path
            let profilePhoto = '/assets/img/default-profile.jpg';
            if (match.pfp_path) {
                if (match.pfp_path.startsWith('/uploads/')) {
                    profilePhoto = match.pfp_path;
                } else if (match.pfp_path.includes('uploads')) {
                    const filename = match.pfp_path.split('\\').pop().split('/').pop();
                    profilePhoto = `/uploads/${filename}`;
                }
            }

            const name = `${match.first_name || ''} ${match.last_name || ''}`.trim();
            const age = match.age > 0 ? `, ${match.age}` : '';

            chatItem.innerHTML = `
                <div class="chat-avatar">
                    <img src="${profilePhoto}" alt="${name}" onerror="this.src='/assets/img/default-profile.jpg'">
                </div>
                <div class="chat-info">
                    <div class="chat-name">${name}${age}</div>
                    <div class="chat-preview">This is ${name}! Say hello</div>
                </div>
                <div class="chat-meta">
                    <div class="chat-actions">
                    </div>
                </div>
            `;

            // Add click event to start chat
            chatItem.addEventListener('click', (e) => {
                if (e.target.closest('.chat-actions')) return;

                this.startChatWithUser(match.id, name, profilePhoto);
            });

            this.container.appendChild(chatItem);
        });

        this.addEventListeners();
    }

    addEventListeners() {
        document.querySelectorAll('.like-btn:not(.has-listener)').forEach(btn => {
            btn.classList.add('has-listener');
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const userId = btn.getAttribute('data-user-id');
                this.likeUser(userId, btn);
            });
        });
    }

    likeUser(userId, button) {
        fetch(`/api/likes/add`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ userId: parseInt(userId) })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = '<i class="fas fa-heart" style="color: #e91e63;"></i>';
                    button.disabled = true;
                    button.classList.add('liked');

                    if (data.isMatch) {
                        this.showMatchNotification(data.matchData);
                    }
                } else {
                    console.error('Failed to like user:', data.message);
                }
            })
            .catch(error => {
                console.error('Error liking user:', error);
            });
    }

    startChatWithUser(userId, name, profilePhoto) {
        fetch('/api/matches/create-or-get', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ userId: parseInt(userId) })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Use ChatManager to open the chat
                    if (this.chatManager) {
                        this.chatManager.openChatWithUser(data.matchId, userId, name, profilePhoto);
                    }
                } else {
                    console.error('Failed to create match:', data.message);
                }
            })
            .catch(error => {
                console.error('Error creating match:', error);
            });
    }

    showMatchNotification(matchData) {
        const notification = document.createElement('div');
        notification.className = 'match-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-heart"></i>
                <span>It's a Match with ${matchData.name}!</span>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}