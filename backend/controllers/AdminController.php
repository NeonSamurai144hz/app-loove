<?php

namespace App\Controllers;

use PDO;

class AdminController {
    private $db;

    public function __construct() {
        session_start();
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
            $this->respondJson(['success' => false, 'message' => 'Unauthorized'], 401);
            exit;
        }
        $this->db = \App\Database::getInstance();
    }

    // Dashboard stats
    public function getDashboard() {
        $stats = $this->getStats();
        $this->respondJson(['success' => true, 'stats' => $stats]);
    }

    // --- USERS CRUD ---

    // Get users with pagination, search and filtering
    public function getUsers() {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, (int)($_GET['limit'] ?? 15));
        $search = trim($_GET['search'] ?? '');
        $filter = $_GET['filter'] ?? 'all';

        $users = $this->getUsersList($page, $limit, $search, $filter);
        $total = $this->getUsersCount($search, $filter);

        $this->respondJson([
            'success' => true,
            'users' => $users,
            'total' => $total
        ]);
    }

    // Get single user details
    public function getUserDetails($userId) {
        $user = $this->getUserById($userId);
        if (!$user) {
            $this->respondJson(['success' => false, 'message' => 'User not found'], 404);
            return;
        }
        $this->respondJson(['success' => true, 'user' => $user]);
    }

    // Create user
    public function createUser() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['email']) || empty($data['password'])) {
            $this->respondJson(['success' => false, 'message' => 'Missing required fields'], 400);
            return;
        }
        $result = $this->createUserInDb($data);
        if ($result) {
            $this->respondJson(['success' => true, 'message' => 'User created']);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Failed to create user'], 500);
        }
    }

    // Update user
    public function updateUser($userId) {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $this->updateUserInDb($userId, $data);
        if ($result) {
            $this->respondJson(['success' => true, 'message' => 'User updated']);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Failed to update user'], 500);
        }
    }

    // Delete user
    public function deleteUser($userId) {
        $result = $this->deleteUserFromDb($userId);
        if ($result) {
            $this->respondJson(['success' => true, 'message' => 'User deleted']);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Failed to delete user'], 500);
        }
    }

    // Ban a user
    public function banUser($userId) {
        $result = $this->setBanStatus($userId, true);
        if ($result) {
            $this->respondJson(['success' => true, 'message' => 'User banned successfully']);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Failed to ban user'], 500);
        }
    }

    // Unban a user
    public function unbanUser($userId) {
        $result = $this->setBanStatus($userId, false);
        if ($result) {
            $this->respondJson(['success' => true, 'message' => 'User unbanned successfully']);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Failed to unban user'], 500);
        }
    }

    // --- REPORTS ---

    public function getReports() {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, (int)($_GET['limit'] ?? 15));
        $filter = $_GET['filter'] ?? 'all';

        $reports = $this->getReportsList($page, $limit, $filter);
        $total = $this->getReportsCount($filter);

        $this->respondJson([
            'success' => true,
            'reports' => $reports,
            'total' => $total
        ]);
    }

    public function getReportDetails($reportId) {
        $report = $this->getReportById($reportId);
        if (!$report) {
            $this->respondJson(['success' => false, 'message' => 'Report not found'], 404);
            return;
        }
        $this->respondJson(['success' => true, 'report' => $report]);
    }

    public function resolveReport($reportId) {
        $result = $this->setReportStatus($reportId, 'resolved');
        if ($result) {
            $this->respondJson(['success' => true, 'message' => 'Report resolved successfully']);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Failed to resolve report'], 500);
        }
    }

    // --- MATCHES ---

    public function getMatches() {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, (int)($_GET['limit'] ?? 15));

        $matches = $this->getMatchesList($page, $limit);
        $total = $this->getMatchesCount();

        $this->respondJson([
            'success' => true,
            'matches' => $matches,
            'total' => $total
        ]);
    }

    public function getMatchDetails($matchId) {
        $match = $this->getMatchById($matchId);
        if (!$match) {
            $this->respondJson(['success' => false, 'message' => 'Match not found'], 404);
            return;
        }
        $this->respondJson(['success' => true, 'match' => $match]);
    }

    public function deleteMatch($matchId) {
        $result = $this->removeMatch($matchId);
        if ($result) {
            $this->respondJson(['success' => true, 'message' => 'Match deleted successfully']);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Failed to delete match'], 500);
        }
    }

    // --- SETTINGS ---

    public function getSettings() {
        $settings = $this->loadSettings();
        $this->respondJson(['success' => true, 'settings' => $settings]);
    }

    public function saveSettings() {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $this->updateSettings($data);
        if ($result) {
            $this->respondJson(['success' => true, 'message' => 'Settings saved successfully']);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Failed to save settings'], 500);
        }
    }

    // --- HELPER: JSON RESPONSE ---

    private function respondJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    // --- DATABASE HELPERS ---

    private function getStats() {
        $stats = [];
        $stats['totalUsers'] = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['activeUsers'] = $this->db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
        $stats['totalMatches'] = $this->db->query("SELECT COUNT(*) FROM matches")->fetchColumn();
        $stats['pendingReports'] = $this->db->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn();
        return $stats;
    }

    private function getUsersList($page, $limit, $search, $filter) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        if ($search) {
            $where[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($filter === 'active') {
            $where[] = "is_active=1 AND is_banned=0";
        } elseif ($filter === 'suspended') {
            $where[] = "is_suspended=1";
        } elseif ($filter === 'banned') {
            $where[] = "is_banned=1";
        }
        $sql = "SELECT * FROM users";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getUsersCount($search, $filter) {
        $where = [];
        $params = [];
        if ($search) {
            $where[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($filter === 'active') {
            $where[] = "is_active=1 AND is_banned=0";
        } elseif ($filter === 'suspended') {
            $where[] = "is_suspended=1";
        } elseif ($filter === 'banned') {
            $where[] = "is_banned=1";
        }
        $sql = "SELECT COUNT(*) FROM users";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function getUserById($userId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function createUserInDb($data) {
        $stmt = $this->db->prepare("INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        return $stmt->execute([
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['email'],
            $passwordHash,
            $data['role'] ?? 'user'
        ]);
    }

    private function updateUserInDb($userId, $data) {
        $fields = [];
        $params = [];
        if (isset($data['first_name'])) {
            $fields[] = "first_name=?";
            $params[] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $fields[] = "last_name=?";
            $params[] = $data['last_name'];
        }
        if (isset($data['email'])) {
            $fields[] = "email=?";
            $params[] = $data['email'];
        }
        if (isset($data['role'])) {
            $fields[] = "role=?";
            $params[] = $data['role'];
        }
        if (isset($data['is_active'])) {
            $fields[] = "is_active=?";
            $params[] = $data['is_active'];
        }
        if (isset($data['password']) && $data['password']) {
            $fields[] = "password=?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (!$fields) return false;
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id=?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    private function deleteUserFromDb($userId) {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id=?");
        return $stmt->execute([$userId]);
    }

    private function setBanStatus($userId, $status) {
        $stmt = $this->db->prepare("UPDATE users SET is_banned=? WHERE id=?");
        return $stmt->execute([$status ? 1 : 0, $userId]);
    }

    // --- REPORTS ---

    private function getReportsList($page, $limit, $filter) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];
        if ($filter === 'pending') {
            $where[] = "status='pending'";
        } elseif ($filter === 'resolved') {
            $where[] = "status='resolved'";
        }
        $sql = "SELECT * FROM reports";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getReportsCount($filter) {
        $where = [];
        if ($filter === 'pending') {
            $where[] = "status='pending'";
        } elseif ($filter === 'resolved') {
            $where[] = "status='resolved'";
        }
        $sql = "SELECT COUNT(*) FROM reports";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function getReportById($reportId) {
        $stmt = $this->db->prepare("SELECT * FROM reports WHERE id=?");
        $stmt->execute([$reportId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function setReportStatus($reportId, $status) {
        $stmt = $this->db->prepare("UPDATE reports SET status=? WHERE id=?");
        return $stmt->execute([$status, $reportId]);
    }

    // --- MATCHES ---

    private function getMatchesList($page, $limit) {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM matches ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getMatchesCount() {
        return (int)$this->db->query("SELECT COUNT(*) FROM matches")->fetchColumn();
    }

    private function getMatchById($matchId) {
        $stmt = $this->db->prepare("SELECT * FROM matches WHERE id=?");
        $stmt->execute([$matchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function removeMatch($matchId) {
        $stmt = $this->db->prepare("DELETE FROM matches WHERE id=?");
        return $stmt->execute([$matchId]);
    }

    // --- SETTINGS ---

    private function loadSettings() {
        $stmt = $this->db->query("SELECT * FROM settings LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            // Defaults
            return [
                'allowRegistration' => true,
                'maintenanceMode' => false,
                'maxAgeDifference' => 20,
                'videoTimeout' => 120
            ];
        }
        return [
            'allowRegistration' => (bool)$row['allow_registration'],
            'maintenanceMode' => (bool)$row['maintenance_mode'],
            'maxAgeDifference' => (int)$row['max_age_difference'],
            'videoTimeout' => (int)$row['video_timeout']
        ];
    }

    private function updateSettings($data) {
        $stmt = $this->db->prepare("UPDATE settings SET allow_registration=?, maintenance_mode=?, max_age_difference=?, video_timeout=?");
        return $stmt->execute([
            !empty($data['allowRegistration']) ? 1 : 0,
            !empty($data['maintenanceMode']) ? 1 : 0,
            (int)$data['maxAgeDifference'],
            (int)$data['videoTimeout']
        ]);
    }
}