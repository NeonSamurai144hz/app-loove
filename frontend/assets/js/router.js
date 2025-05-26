// frontend/assets/js/router.js
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
            setTimeout(() => {
                const loginForm = document.querySelector('form[name="login"]');
                if (loginForm) {
                    new LoginManager('form[name="login"]');
                }
            }, 100);
        } else if (path === 'register') {
            setTimeout(() => {
                const registerForm = document.querySelector('form[name="register"]');
                if (registerForm) {
                    new RegisterManager('form[name="register"]');
                }
            }, 100);
        }
    }
}