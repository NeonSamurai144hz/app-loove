class AdminDashboard {
    constructor() {
        // DOM elements for navigation
        this.navLinks = document.querySelectorAll('.admin-nav a[data-section]');
        this.sections = document.querySelectorAll('.admin-section');
        this.logoutBtn = document.getElementById('admin-logout');

        // Search and filter elements
        this.userSearch = document.getElementById('user-search');
        this.userFilter = document.getElementById('user-filter');
        this.reportFilter = document.getElementById('report-filter');

        // Settings form
        this.settingsForm = document.getElementById('settings-form');

        // Pagination state
        this.pagination = {
            users: { page: 1, limit: 15, total: 0 },
            reports: { page: 1, limit: 15, total: 0 },
            matches: { page: 1, limit: 15, total: 0 }
        };

        this.initEventListeners();
        this.loadDashboardData();
    }

    // --- Event Listeners and Navigation ---
    initEventListeners() {
        // Navigation
        this.navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.showSection(link.getAttribute('data-section'));
            });
        });

        // Logout
        if (this.logoutBtn) {
            this.logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.logout();
            });
        }

        // Search and filters
        if (this.userSearch) {
            this.userSearch.addEventListener('input', this.debounce(() => {
                this.loadUsers();
            }, 500));
        }
        if (this.userFilter) {
            this.userFilter.addEventListener('change', () => {
                this.loadUsers();
            });
        }
        if (this.reportFilter) {
            this.reportFilter.addEventListener('change', () => {
                this.loadReports();
            });
        }

        // Settings form
        if (this.settingsForm) {
            this.settingsForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveSettings();
            });
        }

        // Add User button in controls
        const controls = document.querySelector('#users-section .admin-controls');
        if (controls && !document.getElementById('add-user-btn')) {
            const btn = document.createElement('button');
            btn.id = 'add-user-btn';
            btn.className = 'admin-btn add-user-btn';
            btn.textContent = 'Add User';
            btn.style.marginLeft = '10px';
            btn.onclick = () => this.showCreateUserModal();
            controls.appendChild(btn);
        }
    }

    showSection(sectionId) {
        this.navLinks.forEach(link => {
            if (link.getAttribute('data-section') === sectionId) link.classList.add('active');
            else link.classList.remove('active');
        });
        this.sections.forEach(section => {
            if (section.id === `${sectionId}-section`) {
                section.classList.add('active');
                if (sectionId === 'users') this.loadUsers();
                if (sectionId === 'reports') this.loadReports();
                if (sectionId === 'matches') this.loadMatches();
                if (sectionId === 'settings') this.loadSettings();
            } else {
                section.classList.remove('active');
            }
        });
    }

    // --- Dashboard Overview ---
    loadDashboardData() {
        fetch('/api/admin/dashboard')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('total-users').textContent = data.stats.totalUsers;
                    document.getElementById('active-users').textContent = data.stats.activeUsers;
                    document.getElementById('total-matches').textContent = data.stats.totalMatches;
                    document.getElementById('pending-reports').textContent = data.stats.pendingReports;
                }
            })
            .catch(err => console.error('Error loading dashboard data:', err));
    }

    // --- Users CRUD ---
    loadUsers(page = 1) {
        const searchTerm = this.userSearch ? this.userSearch.value : '';
        const filter = this.userFilter ? this.userFilter.value : 'all';
        fetch(`/api/admin/users?page=${page}&limit=${this.pagination.users.limit}&search=${encodeURIComponent(searchTerm)}&filter=${filter}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.renderUsers(data.users);
                    this.pagination.users.total = data.total;
                    this.renderPagination('users', page);
                }
            })
            .catch(err => console.error('Error loading users:', err));
    }

    renderUsers(users) {
        const tbody = document.querySelector('#users-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (users.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="6" class="no-data">No users found</td>';
            tbody.appendChild(row);
            return;
        }
        users.forEach(user => {
            const row = document.createElement('tr');
            if (user.is_banned) row.classList.add('banned');
            else if (user.is_suspended) row.classList.add('suspended');
            row.innerHTML = `
                <td>${user.id}</td>
                <td>${user.first_name} ${user.last_name}</td>
                <td>${user.email}</td>
                <td>${this.formatDate(user.created_at)}</td>
                <td>${this.getUserStatus(user)}</td>
                <td class="actions">
                    <button class="view-btn" data-id="${user.id}"><i class="fas fa-eye"></i></button>
                    <button class="edit-btn" data-id="${user.id}"><i class="fas fa-edit"></i></button>
                    <button class="delete-btn" data-id="${user.id}"><i class="fas fa-trash"></i></button>
                    ${user.is_banned
                ? `<button class="unban-btn" data-id="${user.id}"><i class="fas fa-unlock"></i></button>`
                : `<button class="ban-btn" data-id="${user.id}"><i class="fas fa-ban"></i></button>`
            }
                </td>
            `;
            tbody.appendChild(row);
        });
        this.addUserActionListeners();
    }

    getUserStatus(user) {
        if (user.is_banned) return 'Banned';
        if (user.is_suspended) {
            const endDate = user.suspend_ends_at ? new Date(user.suspend_ends_at) : '';
            return `Suspended${endDate ? ' until ' + this.formatDate(endDate) : ''}`;
        }
        if (!user.is_active) return 'Inactive';
        return 'Active';
    }

    addUserActionListeners() {
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.onclick = () => this.viewUserDetails(btn.getAttribute('data-id'));
        });
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.onclick = () => this.editUser(btn.getAttribute('data-id'));
        });
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.onclick = () => this.deleteUser(btn.getAttribute('data-id'));
        });
        document.querySelectorAll('.ban-btn').forEach(btn => {
            btn.onclick = () => this.banUser(btn.getAttribute('data-id'));
        });
        document.querySelectorAll('.unban-btn').forEach(btn => {
            btn.onclick = () => this.unbanUser(btn.getAttribute('data-id'));
        });
    }

    showCreateUserModal() {
        this.closeModal();
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <h2>Create User</h2>
                <form id="create-user-form">
                    <label>Name: <input type="text" name="name" required></label>
                    <label>Email: <input type="email" name="email" required></label>
                    <label>Password: <input type="password" name="password" required></label>
                    <label>Role:
                        <select name="role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </label>
                    <div style="margin-top:10px;">
                        <button type="submit" class="admin-btn">Create</button>
                        <button type="button" id="close-create-user-modal" class="admin-btn">Cancel</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
        modal.querySelector('.modal-overlay').onclick = () => this.closeModal();
        document.getElementById('close-create-user-modal').onclick = () => this.closeModal();
        document.getElementById('create-user-form').onsubmit = (e) => {
            e.preventDefault();
            const form = e.target;
            const [first_name, ...rest] = form.name.value.trim().split(' ');
            const last_name = rest.join(' ');
            const payload = {
                first_name,
                last_name,
                email: form.email.value,
                password: form.password.value,
                role: form.role.value
            };
            fetch('/api/admin/users', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    this.closeModal();
                    this.loadUsers();
                });
        };
    }

    viewUserDetails(userId) {
        fetch(`/api/admin/users/${userId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) this.showUserModal(data.user);
            });
    }

    editUser(userId) {
        this.viewUserDetails(userId);
    }

    showUserModal(user) {
        this.closeModal();
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <h2>Edit User</h2>
                <form id="edit-user-form">
                    <label>Name: <input type="text" name="name" value="${user.first_name} ${user.last_name}" required></label>
                    <label>Email: <input type="email" name="email" value="${user.email}" required></label>
                    <label>Password: <input type="password" name="password" placeholder="Leave blank to keep current"></label>
                    <label>Role:
                        <select name="role">
                            <option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                        </select>
                    </label>
                    <div style="margin-top:10px;">
                        <button type="submit" class="admin-btn">Save</button>
                        <button type="button" id="close-user-modal" class="admin-btn">Cancel</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
        modal.querySelector('.modal-overlay').onclick = () => this.closeModal();
        document.getElementById('close-user-modal').onclick = () => this.closeModal();
        document.getElementById('edit-user-form').onsubmit = (e) => {
            e.preventDefault();
            const form = e.target;
            const [first_name, ...rest] = form.name.value.trim().split(' ');
            const last_name = rest.join(' ');
            const payload = {
                first_name,
                last_name,
                email: form.email.value,
                role: form.role.value
            };
            if (form.password.value) payload.password = form.password.value;
            fetch(`/api/admin/users/${user.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    this.closeModal();
                    this.loadUsers();
                });
        };
    }

    deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user?')) {
            fetch(`/api/admin/users/${userId}`, { method: 'DELETE' })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    this.loadUsers();
                });
        }
    }

    banUser(userId) {
        if (confirm('Are you sure you want to ban this user?')) {
            fetch(`/api/admin/users/${userId}/ban`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('User has been banned');
                        this.loadUsers(this.pagination.users.page);
                    } else {
                        alert('Failed to ban user: ' + data.message);
                    }
                });
        }
    }

    unbanUser(userId) {
        if (confirm('Are you sure you want to unban this user?')) {
            fetch(`/api/admin/users/${userId}/unban`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('User has been unbanned');
                        this.loadUsers(this.pagination.users.page);
                    } else {
                        alert('Failed to unban user: ' + data.message);
                    }
                });
        }
    }

    // --- Reports CRUD ---
    loadReports(page = 1) {
        const filter = this.reportFilter ? this.reportFilter.value : 'all';
        fetch(`/api/admin/reports?page=${page}&limit=${this.pagination.reports.limit}&filter=${filter}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.renderReports(data.reports);
                    this.pagination.reports.total = data.total;
                    this.renderPagination('reports', page);
                }
            })
            .catch(err => console.error('Error loading reports:', err));
    }

    renderReports(reports) {
        const tbody = document.querySelector('#reports-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (reports.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="7" class="no-data">No reports found</td>';
            tbody.appendChild(row);
            return;
        }
        reports.forEach(report => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${report.id}</td>
                <td>${report.reporter_name || ''}</td>
                <td>${report.reported_user_name || ''}</td>
                <td>${this.formatReportReason(report.reason)}</td>
                <td>${this.formatDate(report.reported_at)}</td>
                <td>${report.status || 'Pending'}</td>
                <td class="actions">
                    <button class="view-report-btn" data-id="${report.id}"><i class="fas fa-eye"></i></button>
                    <button class="resolve-btn" data-id="${report.id}"><i class="fas fa-check"></i></button>
                    <button class="ban-user-btn" data-id="${report.reported_user_id}"><i class="fas fa-ban"></i></button>
                </td>
            `;
            tbody.appendChild(row);
        });
        this.addReportActionListeners();
    }

    formatReportReason(reason) {
        const reasons = {
            'spam': 'Spam',
            'abuse': 'Abusive Behavior',
            'fake_profile': 'Fake Profile',
            'other': 'Other'
        };
        return reasons[reason] || reason;
    }

    addReportActionListeners() {
        document.querySelectorAll('.view-report-btn').forEach(btn => {
            btn.onclick = () => this.viewReportDetails(btn.getAttribute('data-id'));
        });
        document.querySelectorAll('.resolve-btn').forEach(btn => {
            btn.onclick = () => this.resolveReport(btn.getAttribute('data-id'));
        });
        document.querySelectorAll('.ban-user-btn').forEach(btn => {
            btn.onclick = () => this.banUser(btn.getAttribute('data-id'));
        });
    }

    viewReportDetails(reportId) {
        fetch(`/api/admin/reports/${reportId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) this.showReportModal(data.report);
            });
    }

    showReportModal(report) {
        this.closeModal();
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <h2>Report Details</h2>
                <p><strong>ID:</strong> ${report.id}</p>
                <p><strong>Reporter:</strong> ${report.reporter_name || ''}</p>
                <p><strong>Reported User:</strong> ${report.reported_user_name || ''}</p>
                <p><strong>Reason:</strong> ${this.formatReportReason(report.reason)}</p>
                <p><strong>Date:</strong> ${this.formatDate(report.reported_at)}</p>
                <p><strong>Status:</strong> ${report.status}</p>
                <button id="close-report-modal" class="admin-btn">Close</button>
            </div>
        `;
        document.body.appendChild(modal);
        modal.querySelector('.modal-overlay').onclick = () => this.closeModal();
        document.getElementById('close-report-modal').onclick = () => this.closeModal();
    }

    resolveReport(reportId) {
        if (confirm('Mark this report as resolved?')) {
            fetch(`/api/admin/reports/${reportId}/resolve`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Report has been resolved');
                        this.loadReports(this.pagination.reports.page);
                    } else {
                        alert('Failed to resolve report: ' + data.message);
                    }
                });
        }
    }

    // --- Matches CRUD ---
    loadMatches(page = 1) {
        fetch(`/api/admin/matches?page=${page}&limit=${this.pagination.matches.limit}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.renderMatches(data.matches);
                    this.pagination.matches.total = data.total;
                    this.renderPagination('matches', page);
                }
            })
            .catch(err => console.error('Error loading matches:', err));
    }

    renderMatches(matches) {
        const tbody = document.querySelector('#matches-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (matches.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="7" class="no-data">No matches found</td>';
            tbody.appendChild(row);
            return;
        }
        matches.forEach(match => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${match.id}</td>
                <td>${match.user1_name || ''}</td>
                <td>${match.user2_name || ''}</td>
                <td>${this.formatDate(match.matched_at)}</td>
                <td>${match.message_count || 0}</td>
                <td>${match.status || 'Active'}</td>
                <td class="actions">
                    <button class="view-match-btn" data-id="${match.id}"><i class="fas fa-eye"></i></button>
                    <button class="delete-match-btn" data-id="${match.id}"><i class="fas fa-trash"></i></button>
                </td>
            `;
            tbody.appendChild(row);
        });
        this.addMatchActionListeners();
    }

    addMatchActionListeners() {
        document.querySelectorAll('.view-match-btn').forEach(btn => {
            btn.onclick = () => this.viewMatchDetails(btn.getAttribute('data-id'));
        });
        document.querySelectorAll('.delete-match-btn').forEach(btn => {
            btn.onclick = () => this.deleteMatch(btn.getAttribute('data-id'));
        });
    }

    viewMatchDetails(matchId) {
        fetch(`/api/admin/matches/${matchId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) this.showMatchModal(data.match);
            });
    }

    showMatchModal(match) {
        this.closeModal();
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <h2>Match Details</h2>
                <p><strong>ID:</strong> ${match.id}</p>
                <p><strong>User 1:</strong> ${match.user1_name || ''}</p>
                <p><strong>User 2:</strong> ${match.user2_name || ''}</p>
                <p><strong>Date:</strong> ${this.formatDate(match.matched_at)}</p>
                <p><strong>Status:</strong> ${match.status || 'Active'}</p>
                <button id="close-match-modal" class="admin-btn">Close</button>
            </div>
        `;
        document.body.appendChild(modal);
        modal.querySelector('.modal-overlay').onclick = () => this.closeModal();
        document.getElementById('close-match-modal').onclick = () => this.closeModal();
    }

    deleteMatch(matchId) {
        if (confirm('Are you sure you want to delete this match?')) {
            fetch(`/api/admin/matches/${matchId}`, { method: 'DELETE' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Match has been deleted');
                        this.loadMatches(this.pagination.matches.page);
                    } else {
                        alert('Failed to delete match: ' + data.message);
                    }
                });
        }
    }

    // --- Settings ---
    loadSettings() {
        fetch('/api/admin/settings')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('allow-registration').checked = data.settings.allowRegistration;
                    document.getElementById('maintenance-mode').checked = data.settings.maintenanceMode;
                    document.getElementById('max-age-diff').value = data.settings.maxAgeDifference;
                    document.getElementById('video-timeout').value = data.settings.videoTimeout;
                }
            })
            .catch(err => console.error('Error loading settings:', err));
    }

    saveSettings() {
        const settings = {
            allowRegistration: document.getElementById('allow-registration').checked,
            maintenanceMode: document.getElementById('maintenance-mode').checked,
            maxAgeDifference: document.getElementById('max-age-diff').value,
            videoTimeout: document.getElementById('video-timeout').value
        };
        fetch('/api/admin/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(settings)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Settings saved successfully');
                } else {
                    alert('Failed to save settings: ' + data.message);
                }
            })
            .catch(err => {
                console.error('Error saving settings:', err);
                alert('Connection error. Please try again.');
            });
    }

    // --- Utility ---
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    renderPagination(type, currentPage) {
        const container = document.getElementById(`${type}-pagination`);
        if (!container) return;
        const totalPages = Math.ceil(this.pagination[type].total / this.pagination[type].limit);
        let html = '';
        if (totalPages > 1) {
            html += `<button class="page-btn ${currentPage === 1 ? 'disabled' : ''}" data-page="prev">Previous</button>`;
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }
            html += `<button class="page-btn ${currentPage === totalPages ? 'disabled' : ''}" data-page="next">Next</button>`;
        }
        container.innerHTML = html;
        container.querySelectorAll('.page-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.classList.contains('disabled')) return;
                let page = btn.getAttribute('data-page');
                if (page === 'prev') page = currentPage - 1;
                else if (page === 'next') page = currentPage + 1;
                else page = parseInt(page);
                if (type === 'users') this.loadUsers(page);
                if (type === 'reports') this.loadReports(page);
                if (type === 'matches') this.loadMatches(page);
            });
        });
    }

    debounce(func, wait) {
        let timeout;
        return function () {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    closeModal() {
        const modal = document.querySelector('.modal');
        if (modal) modal.remove();
    }

    logout() {
        fetch('/api/auth/logout', {
            method: 'POST',
            credentials: 'include'
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (window.router) router.navigate('login');
                    else window.location.href = '/login';
                } else {
                    alert('Logout failed. Please try again.');
                }
            })
            .catch(err => {
                console.error('Error during logout:', err);
                alert('Connection error. Please try again.');
            });
    }
}

// Initialize the admin dashboard when the page loads
window.initAdmin = function () {
    new AdminDashboard();
};

document.addEventListener('DOMContentLoaded', () => {
    window.initAdmin();
});