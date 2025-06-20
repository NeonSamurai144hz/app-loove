function renderNavbar(activePage = 'chat') {
    const items = [
        { page: 'chat', icon: 'fa fa-comments', label: 'Chats' },
        { page: 'matches', icon: 'fa-solid fa-video', label: 'Match' },
        { page: 'profile', icon: 'fa-solid fa-user', label: 'Profile' },
        { page: 'admin', icon: 'fa-solid fa-lock', label: 'Admin' }
    ];
    return `
        <div class="bottom-nav">
            ${items.map(item => `
                <div class="nav-item${activePage === item.page ? ' active' : ''}" data-page="${item.page}">
                    <span class="nav-icon"><i class="${item.icon}"></i></span>
                    <span class="nav-label">${item.label}</span>
                </div>
            `).join('')}
        </div>
    `;
}

class HomeManager {
    constructor() {
        this.miniAppContainer = document.getElementById('mini-app-container');
        this.currentPage = null;
        this.loadedScripts = new Set();
        this.chatManagerInstance = null;
        this.recommendedMatchesInstance = null;
        this.initNavbar();
    }

    initNavbar() {
        fetch('/api/auth/me')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('navbar-placeholder').innerHTML = renderNavbar('chat');
                    this.navItems = document.querySelectorAll('.bottom-nav .nav-item');
                    this.initNavEvents();
                    const defaultNav = document.querySelector('.nav-item.active');
                    if (defaultNav) {
                        this.loadPage(defaultNav.getAttribute('data-page'));
                    }
                } else {
                    // Not authenticated, redirect to login
                    if (window.router) router.navigate('login');
                }
            });
    }

    initNavEvents() {
        this.navItems.forEach(item => {
            item.addEventListener('click', () => {
                this.navItems.forEach(nav => nav.classList.remove('active'));
                item.classList.add('active');
                const page = item.getAttribute('data-page');
                this.loadPage(page);
            });
        });
    }

    loadPage(page) {
        if (!page) return;
        this.currentPage = page;
        fetch(`/views/${page}.html`)
            .then(response => {
                if (!response.ok) throw new Error('Failed to load page');
                return response.text();
            })
            .then(html => {
                this.miniAppContainer.innerHTML = html;
                if (page === 'profile') {
                    this.miniAppContainer.classList.add('profile-scroll');
                } else {
                    this.miniAppContainer.classList.remove('profile-scroll');
                }
                this.initPageManager(page);
            })
            .catch(error => {
                this.miniAppContainer.innerHTML = `<p>Error loading page. Please try again later.</p>`;
                console.error('Error loading page:', error);
            });
    }

    // Helper method to load scripts dynamically
    loadScript(src) {
        return new Promise((resolve, reject) => {
            // Check if script is already loaded
            if (this.loadedScripts.has(src)) {
                resolve();
                return;
            }

            // Check if script element already exists
            const existingScript = document.querySelector(`script[src="${src}"]`);
            if (existingScript) {
                this.loadedScripts.add(src);
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.onload = () => {
                this.loadedScripts.add(src);
                console.log(`Script loaded: ${src}`);
                resolve();
            };
            script.onerror = () => {
                console.error(`Failed to load script: ${src}`);
                reject(new Error(`Failed to load script: ${src}`));
            };
            document.head.appendChild(script);
        });
    }

    // Method to clean up previous instances
    cleanupInstances() {
        if (this.chatManagerInstance) {
            // Add any cleanup logic if needed
            this.chatManagerInstance = null;
        }
        if (this.recommendedMatchesInstance) {
            // Add any cleanup logic if needed
            this.recommendedMatchesInstance = null;
        }
    }

    async initChatPage() {
        console.log('Initializing chat page...');

        // Clean up existing instances
        this.cleanupInstances();

        try {
            // Load chat.js first
            await this.loadScript('/assets/js/chat.js');
            console.log('chat.js loaded');

            // Then load recommendedMatches.js
            await this.loadScript('/assets/js/recommendedMatches.js');
            console.log('recommendedMatches.js loaded');

            // Wait a moment for DOM to be ready
            await new Promise(resolve => setTimeout(resolve, 100));

            // Initialize ChatManager
            if (typeof ChatManager !== 'undefined') {
                console.log('Creating ChatManager instance...');
                this.chatManagerInstance = new ChatManager();
            } else {
                console.error('ChatManager class not found');
            }

            // Initialize RecommendedMatches if the container exists
            const recommendedContainer = document.getElementById('recommended-matches-list');
            if (typeof RecommendedMatches !== 'undefined' && recommendedContainer) {
                console.log('Creating RecommendedMatches instance...');
                this.recommendedMatchesInstance = new RecommendedMatches();

                // Pass ChatManager reference to RecommendedMatches
                if (this.chatManagerInstance) {
                    this.recommendedMatchesInstance.setChatManager(this.chatManagerInstance);
                }
            } else {
                console.warn('RecommendedMatches class not found or container missing');
            }

        } catch (error) {
            console.error('Error initializing chat page:', error);
        }
    }

    initPageManager(page) {
        console.log('Initializing page manager for:', page);

        if (page === 'chat') {
            this.initChatPage();
        } else if (page === 'profile') {
            this.initProfilePage();
        } else if (page === 'matches') {
            this.initMatchesPage();
        } else if (page === 'admin') {
            this.initAdminPage();
        }
    }

    async initProfilePage() {
        try {
            await this.loadScript('/assets/js/profile.js');
            if (typeof ProfileManager !== 'undefined') {
                new ProfileManager('#edit-profile-form');
            }
        } catch (error) {
            console.error('Error loading profile page:', error);
        }
    }

    async initMatchesPage() {
        try {
            await this.loadScript('/assets/js/matches.js');
            if (typeof MatchesManager !== 'undefined') {
                new MatchesManager();
            }
        } catch (error) {
            console.error('Error loading matches page:', error);
        }
    }

    async initAdminPage() {
        try {
            // Check user role
            const res = await fetch('/api/auth/me');
            const data = await res.json();

            if (data.success && (data.user.role === 'admin' || data.user.role === 'superadmin')) {
                // Load admin dashboard for admins
                const adminHtml = await fetch('/views/admin.html').then(r => r.text());
                this.miniAppContainer.innerHTML = adminHtml;
                await this.loadScript('/assets/js/admin.js');
                if (typeof window.initAdmin === 'function') {
                    window.initAdmin();
                }
            } else {
                // Load regular settings page for non-admins
                await this.loadScript('/assets/js/settings.js');
                if (typeof SettingsManager !== 'undefined') {
                    new SettingsManager();
                }
            }
        } catch (error) {
            console.error('Error loading settings/admin page:', error);
            this.miniAppContainer.innerHTML = `<p>Error loading page. Please try again later.</p>`;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.homeManager = new HomeManager();
});