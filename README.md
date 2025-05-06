# Sweep

![PHP Version](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![MySQL Version](https://img.shields.io/badge/mysql-5.7%2B-orange.svg)
![Apache Version](https://img.shields.io/badge/apache-2.4%2B-red.svg)
![Javascript Version](https://img.shields.io/badge/javascript-ES6%2B-yellow.svg)
![HTML Version](https://img.shields.io/badge/html-5-orange.svg)
![CSS Version](https://img.shields.io/badge/css-3-blue.svg)

A responsive web application with user authentication built with PHP and JavaScript.

##  Overview

Sweep provides a clean, responsive interface featuring:
- Modern landing page
- Secure user authentication system
- RESTful API architecture
- Mobile-friendly design

##  Installation

### Prerequisites

- PHP 7.4+
- MySQL/MariaDB
- Apache web server
- Git

### Quick Start

1. **Clone the repository**

```bash
git clone https://github.com/YourUsername/sweep.git
cd sweep
```

2. **Configure Apache Virtual Host**

Add the following to your Apache configuration:

```apache
<VirtualHost *:80>
    ServerName sweep.local
    DocumentRoot "/path/to/sweep/frontend"
    <Directory "/path/to/sweep/frontend">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog "logs/sweep-error.log"
    CustomLog "logs/sweep-access.log" common
</VirtualHost>
```

3. **Update Host File**

Add this line to your hosts file:
```
127.0.0.1 sweep.local
```

Location:
- Windows: `C:\Windows\System32\drivers\etc\hosts`
- Mac/Linux: `/etc/hosts`

4. **Set Up Database**

```sql
CREATE DATABASE sweep_db;
CREATE USER 'sweep_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON sweep_db.* TO 'sweep_user'@'localhost';
FLUSH PRIVILEGES;
```

5. **Configure Database Connection**

Create a configuration file based on the examples provided in the backend directory.

##  Project Structure

```
sweep/
├── backend/            # PHP backend
│   ├── controllers/    # API controllers
│   ├── models/         # Database models
│   ├── Database.php    # Database connection
│   └── Router.php      # API routing
├── frontend/           # Frontend code
│   ├── assets/         # CSS, JS, images
│   │   ├── css/        # Stylesheets
│   │   └── js/         # JavaScript files
│   ├── views/          # HTML templates
│   └── index.php       # Main entry point
└── docs/               # Documentation
```

##  Development

### Git Workflow

- Work on the `develop` branch for new features
- Create feature branches as needed
- Make pull requests to `develop` for code review
- Merge to `main` for production releases

### Running Locally

1. Ensure Apache is running
2. Navigate to http://sweep.local in your browser

##  Design

The application uses a modern design with the following color scheme:

- Primary color: `#0F1BFF` (blue)
- Background: `#FFFFFF` (white)
- Text: `#000000` (black) / `#FFFFFF` (white)

##  Troubleshooting

### Common Issues

| Problem | Solution |
|---------|----------|
| Page not loading | Check Apache configuration and restart the service |
| Database connection error | Verify credentials and database existence |
| Cached content | Hard refresh your browser with Ctrl+F5 (Windows) or Cmd+Shift+R (Mac) |

##  Contributing

Contributions are welcome! Please feel free to submit a Pull Request.