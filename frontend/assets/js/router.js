// frontend/assets/js/router.js
class Router {
    constructor() {
        this.routes = {};
        this.init();
    }

    init() {
        // Handle browser navigation events
        window.addEventListener('popstate', () => {
            this.navigate(window.location.pathname.substring(1), false);
        });

        // Handle initial route
        this.navigate(window.location.pathname.substring(1) || 'home', false);
    }

    add(route, template) {
        this.routes[route] = template;
    }

    navigate(path, addToHistory = true) {
        const content = document.getElementById('app');

        // Handle empty path as home/landing page
        if (path === '') {
            path = 'home';
        }

        // Set page-specific body class
        if (path === 'home') {
            document.body.className = 'landing-page';
        } else {
            document.body.className = path + '-page';
        }

        if (addToHistory) {
            history.pushState({}, '', '/' + path);
        }

        // Check if route exists in our defined routes
        if (this.routes[path]) {
            // Use absolute path with leading slash
            fetch(`/frontend/views/${this.routes[path]}`)
                .then(res => res.text())
                .then(html => {
                    content.innerHTML = html;
                    this.initViewScripts(path);

                    // Execute any scripts in the loaded content
                    const scripts = content.querySelectorAll('script');
                    scripts.forEach(script => {
                        eval(script.textContent);
                    });
                })
                .catch(err => {
                    console.error("Failed to load view:", err);
                    content.innerHTML = "<h1>Page Not Found</h1>";
                });
        } else {
            content.innerHTML = "<h1>Page Not Found</h1>";
        }
    }

    initViewScripts(path) {
        // Initialize scripts specific to each view
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