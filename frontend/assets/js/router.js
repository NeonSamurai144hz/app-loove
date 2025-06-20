
function loadScript(src, callback) {
    if (document.querySelector(`script[src="${src}"]`)) {
        if (callback) callback();
        return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.onload = () => { if (callback) callback(); };
    document.body.appendChild(script);
}

class Router {
    constructor() {
        this.routes = {};
        this.init();
    }

    init() {
        window.addEventListener('popstate', () => {
            this.navigate(window.location.pathname.substring(1), false);
        });

        const initialPath = window.location.pathname.substring(1);
        this.navigate(initialPath, false);
    }

    add(route, template) {
        this.routes[route] = template;
    }

    navigate(path, addToHistory = true) {
        const content = document.getElementById('app');

        if (path === '' || path === 'home') {
            document.body.className = 'landing-page';
        } else {
            document.body.className = path + '-page';
        }

        if (addToHistory) {
            history.pushState({}, '', '/' + path);
        }

        // Protect these routes
        const protectedRoutes = ['home', 'profile', 'matches'];
        if (protectedRoutes.includes(path)) {
            this.checkAuthAndNavigate(path, () => {
                this.loadView(path, content);
            });
            return;
        }

        if (this.routes[path]) {
            this.loadView(path, content);
        } else {
            content.innerHTML = "<h1>Page Not Found</h1>";
        }
    }

    loadView(path, content) {
        fetch(`/views/${this.routes[path]}`)
            .then(res => res.text())
            .then(html => {
                content.innerHTML = html;
                this.initViewScripts(path);
                const scripts = content.querySelectorAll('script');
                scripts.forEach(script => {
                    eval(script.textContent);
                });
            })
            .catch(err => {
                console.error("Failed to load view:", err);
                content.innerHTML = "<h1>Page Not Found</h1>";
            });
    }

    checkAuthAndNavigate(path, callback) {
        fetch('/api/auth/me')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    callback();
                } else {
                    this.navigate('login');
                }
            })
            .catch(() => {
                this.navigate('login');
            });
    }

    initViewScripts(path) {
        if (path === 'login') {
            loadScript('/assets/js/login.js', () => {
                setTimeout(() => {
                    const loginForm = document.querySelector('form[name="login"]');
                    if (loginForm) new LoginManager('form[name="login"]');
                }, 100);
            });
        } else if (path === 'register') {
            loadScript('/assets/js/register.js', () => {
                setTimeout(() => {
                    const registerForm = document.querySelector('form[name="register"]');
                    if (registerForm) new RegisterManager('form[name="register"]');
                }, 100);
            });
        } else if (path === 'home') {
            loadScript('/assets/js/home.js', () => {
                setTimeout(() => {
                    if (document.getElementById('navbar-placeholder')) {
                        new HomeManager();
                    }
                }, 50);
            });
        } else if (path === 'profile') {
            loadScript('/assets/js/profile.js', () => {
                setTimeout(() => {
                    const form = document.getElementById('edit-profile-form');
                    if (form && typeof window.initProfileEdit === 'function') {
                        window.initProfileEdit();
                    }
                }, 100);
            });
        } else if (path === 'messages') {
            loadScript('/assets/js/chat.js', () => {
                loadScript('/assets/js/recommendedMatches.js', () => {
                    setTimeout(() => {
                        if (
                            typeof window.RecommendedMatches === 'function' &&
                            document.getElementById('recommended-matches')
                        ) {
                            window.recommendedMatchesInstance = new RecommendedMatches();
                            console.log('RecommendedMatches instance created');
                        } else {
                            console.log('RecommendedMatches class or container not found');
                        }
                    }, 600); // Increased delay
                });
            });
        } else if (path === 'chats') {
            loadScript('/assets/js/chat.js', () => {
                loadScript('/assets/js/recommendedMatches.js', () => {
                    setTimeout(() => {
                        if (
                            typeof window.RecommendedMatches === 'function' &&
                            document.getElementById('recommended-matches')
                        ) {
                            window.recommendedMatchesInstance = new RecommendedMatches();
                            console.log('RecommendedMatches instance created');
                        } else {
                            console.log('RecommendedMatches class or container not found');
                        }
                    }, 600);
                });
            });
        } else if (path === 'matches') {
            loadScript('/assets/js/matches.js', () => {
                setTimeout(() => {
                    if (typeof window.loadMatches === 'function') window.loadMatches();
                }, 100);
            });
        } else if (path === 'video-chat') {
            loadScript('/assets/js/video-chat.js', () => {
                setTimeout(() => {
                    if (typeof window.VideoChatManager === 'function') {
                        new VideoChatManager();
                    }
                }, 100);
            });
        }
    }
}