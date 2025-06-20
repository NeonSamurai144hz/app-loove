# Sweep Dating App

![PHP Version](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![MySQL Version](https://img.shields.io/badge/mysql-5.7%2B-orange.svg)
![Apache Version](https://img.shields.io/badge/apache-2.4%2B-red.svg)
![Javascript Version](https://img.shields.io/badge/javascript-ES6%2B-yellow.svg)
![HTML Version](https://img.shields.io/badge/html-5-orange.svg)
![CSS Version](https://img.shields.io/badge/css-3-blue.svg)

A modern, real-time video dating web application built with PHP, JavaScript, MySQL, and WebSockets.

---

## Table of Contents

- [Features](#features)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Development](#development)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)

---

## Features

### User Features

- **Registration & Login**
    - Email/password registration and login
    - Secure password hashing
    - Social login placeholders (Facebook, Google, Apple)
- **Profile Management**
    - Edit profile details, upload/change profile photo
    - Set gender, preferences, and privacy options
- **Matching System**
    - Smart recommended matches based on preferences
    - Like/pass (swipe) system
    - Random chat and live video meet
    - Match creation when both users like each other
- **Chat & Messaging**
    - Real-time chat with message history
    - Typing indicators, unread message badges
    - Message timestamps and delivery status
- **Video Chat**
    - One-click video call with matches
    - Video chat status (available/busy/offline)
    - WebRTC signaling via WebSocket server
- **Settings**
    - Change password, email, notification preferences
    - Privacy controls and account deletion

### Admin Features

- **Dashboard Overview**
    - Live stats: total users, active users, matches, pending reports
- **User Management**
    - List, search, filter, view, edit, ban/unban, delete, and create users
- **Reports Management**
    - View, filter, resolve user reports, ban from report
- **Matches Management**
    - List, view, and delete matches
- **System Settings**
    - Toggle registration, maintenance mode, set match/video settings
- **Security**
    - Role-based access (admin/superadmin)
    - All admin APIs require valid session and role

### Technical Features

- **RESTful PHP Backend** with modular controllers
- **Composer** for PHP dependency management
- **WebSocket Server** (Ratchet) for real-time chat and video signaling
- **Pusher** integration for scalable real-time events (optional)
- **SPA Frontend** with vanilla JS, dynamic routing, and modular CSS
- **Responsive Design** for desktop and mobile
- **MySQL Database** with normalized schema
- **Error Logging** for backend and WebSocket server
- **API Security**: CORS, input validation, session checks

---

## Project Structure

app-loove/ ├── backend/ │ ├── api.php # Main API router │ ├── config/ # Config files (e.g. pusher.php) │ ├── controllers/ # API controllers (Auth, Match, Admin, Chat, User, Pusher) │ ├── models/ # Database models (Message, etc.) │ ├── websocketServer.php # WebSocket server (Ratchet) │ ├── test-messages.php # Test script for messages │ └── debug.log # Backend debug log ├── frontend/ │ ├── assets/ │ │ ├── css/ # Stylesheets (home, chat, admin, etc.) │ │ └── js/ # JS modules (app, home, chat, admin, etc.) │ ├── views/ # HTML templates (landing, home, chat, admin, etc.) │ ├── index.php # Main entry point (includes app-container.html) │ └── websocket-test.html # WebSocket test client ├── sweep_db.sql # Example database schema └── httpd-vhosts.conf.example # Apache virtual host example
---

## Installation

### Prerequisites

- **PHP 7.4+** (with PDO, mbstring, openssl, sockets extensions)
- **MySQL 5.7+** or MariaDB
- **Apache 2.4+** (mod_rewrite enabled)
- **Composer** (PHP dependency manager)
- **Node.js** (for frontend build, optional)
- **Git**

### Required PHP Packages

Install via Composer:

```bash
cd app-loove/backend
composer install


Required packages (see composer.json):


vlucas/phpdotenv (env config)
pusher/pusher-php-server (Pusher integration)
cboden/ratchet (WebSocket server)
monolog/monolog (logging)
Pusher Setup (Optional)
If using Pusher for real-time events:
Create a Pusher account at https://pusher.com/
Copy your app credentials into backend/config/pusher.php:


return [
    'app_id' => 'YOUR_APP_ID',
    'key' => 'YOUR_APP_KEY',
    'secret' => 'YOUR_APP_SECRET',
    'cluster' => 'YOUR_APP_CLUSTER',
    'useTLS' => true
];


Create the database and user:
CREATE DATABASE sweep_db;
CREATE USER 'sweep_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON sweep_db.* TO 'sweep_user'@'localhost';
FLUSH PRIVILEGES;

Import the schema:
mysql -u sweep_user -p sweep_db < sweep_db.sql

Apache Virtual Host
Add to your Apache config (see httpd-vhosts.conf.example):

<VirtualHost *:80>
    ServerName sweep.local
    DocumentRoot "C:/Coding/Dating-app/app-loove/frontend"
    <Directory "C:/Coding/Dating-app/app-loove/frontend">
        Options FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>


127.0.0.1 sweep.local

<hr></hr>
Configuration
Database connection: Set up in your backend config (e.g. .env or Database.php)
Pusher: Configure in backend/config/pusher.php
WebSocket: Start with php backend/websocketServer.php (default port 8080)
<hr></hr>
Usage
Start Apache and MySQL.
(Optional) Start WebSocket server:

php backend/websocketServer.php

Visit http://sweep.local in your browser.
<hr></hr>
File Overview
backend/api.php: Main API router for all endpoints (/api/*)
backend/controllers/: All business logic (Auth, Match, Admin, Chat, User, Pusher)
backend/models/: Database models (e.g. Message.php)
backend/websocketServer.php: Real-time WebSocket server for chat/video
frontend/assets/css/: Modular CSS for each page/feature
frontend/assets/js/: Modular JS for SPA navigation, chat, admin, etc.
frontend/views/: HTML templates for all app pages
frontend/index.php: Main entry, includes SPA container
frontend/websocket-test.html: Test client for WebSocket server
<hr></hr>
Development
Branching: Use develop for new features, main for production
Frontend: Edit HTML in views/, JS in assets/js/, CSS in assets/css/
Backend: Edit PHP in backend/controllers/, backend/models/
API: All endpoints under /api/ (see api.php for routes)
WebSocket: All real-time features via websocketServer.php (Ratchet)
<hr></hr>
Troubleshooting
Problem
Solution
API 404
Check .htaccess and Apache config for mod_rewrite
WebSocket not connecting
Ensure server is running on port 8080
Pusher errors
Check credentials in pusher.php
Database errors
Verify credentials and schema
"Loading..." in admin
Check /api/admin/* endpoints and session/role
<hr></hr>
Contributing
Pull requests are welcome! Please fork the repo and submit a PR to develop.