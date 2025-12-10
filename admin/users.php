<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$conn = getConnection();
$message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle add new user
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $role = $_POST['role'];

        // Validation
        if (empty($username) || empty($password)) {
            $message = '<div class="error">Username and password are required!</div>';
        } elseif (strlen($username) < 3) {
            $message = '<div class="error">Username must be at least 3 characters!</div>';
        } elseif (strlen($password) < 4) {
            $message = '<div class="error">Password must be at least 4 characters!</div>';
        } elseif ($password !== $confirm_password) {
            $message = '<div class="error">Passwords do not match!</div>';
        } else {
            // Check if username already exists
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->bind_param("s", $username);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $message = '<div class="error">Username already exists! Please choose a different username.</div>';
            } else {
                // Insert new user
                $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $password, $role);

                if ($stmt->execute()) {
                    $new_user_id = $stmt->insert_id;

                    // Log the user creation
                    $admin_id = $_SESSION['user_id'];
                    $stmt2 = $conn->prepare("INSERT INTO user_creation_logs (admin_id, user_id, created_at) VALUES (?, ?, NOW())");
                    $stmt2->bind_param("ii", $admin_id, $new_user_id);
                    $stmt2->execute();
                    $stmt2->close();

                    $message = '<div class="success">Pengguna "' . htmlspecialchars($username) . '" berhasi didaftarkan!</div>';
                } else {
                    $message = '<div class="error">Error creating user: ' . $conn->error . '</div>';
                }
                $stmt->close();
            }
            $checkStmt->close();
        }
    }
    // Handle reset password (existing code)
    elseif (isset($_POST['reset_password'])) {
        $user_id = intval($_POST['user_id']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (empty($new_password)) {
            $message = '<div class="error">Password cannot be empty!</div>';
        } elseif ($new_password !== $confirm_password) {
            $message = '<div class="error">Passwords do not match!</div>';
        } elseif (strlen($new_password) < 4) {
            $message = '<div class="error">Password must be at least 4 characters!</div>';
        } else {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_password, $user_id);

            if ($stmt->execute()) {
                // Log the password reset
                $admin_id = $_SESSION['user_id'];
                $stmt2 = $conn->prepare("INSERT INTO password_reset_logs (admin_id, user_id, reset_at) VALUES (?, ?, NOW())");
                $stmt2->bind_param("ii", $admin_id, $user_id);
                $stmt2->execute();
                $stmt2->close();

                $message = '<div class="success">Password reset successfully!</div>';
            } else {
                $message = '<div class="error">Error resetting password: ' . $conn->error . '</div>';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);

        // Cannot delete admin user
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && $user['role'] === 'admin') {
            $message = '<div class="error">Cannot delete administrator accounts!</div>';
        } else {
            // Delete user's votes first
            $stmt = $conn->prepare("DELETE FROM votes WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            // Delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $message = '<div class="success">Pengguna berhasil dihapus!</div>';
            } else {
                $message = '<div class="error">Error deleting user: ' . $conn->error . '</div>';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['toggle_vote_status'])) {
        $user_id = intval($_POST['user_id']);

        $stmt = $conn->prepare("UPDATE users SET has_voted = NOT has_voted WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $message = '<div class="success">Vote status updated successfully!</div>';
        } else {
            $message = '<div class="error">Error updating vote status: ' . $conn->error . '</div>';
        }
        $stmt->close();
    } elseif (isset($_POST['change_role'])) {
        $user_id = intval($_POST['user_id']);
        $new_role = $_POST['new_role'];

        // Cannot change role of current admin
        if ($user_id == $_SESSION['user_id']) {
            $message = '<div class="error">Cannot change your own role!</div>';
        } else {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user_id);

            if ($stmt->execute()) {
                $message = '<div class="success">User role updated successfully!</div>';
            } else {
                $message = '<div class="error">Error updating role: ' . $conn->error . '</div>';
            }
            $stmt->close();
        }
    }
}

// Get all users
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$voted_filter = isset($_GET['voted']) ? $_GET['voted'] : '';

$query = "SELECT u.*, 
                 COUNT(v.id) as total_votes,
                 MAX(v.voted_at) as last_voted
          FROM users u
          LEFT JOIN votes v ON u.id = v.user_id";

$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(u.username LIKE ? OR u.id = ?)";
    $params[] = "%$search%";
    $params[] = $search;
    $types .= 'si';
}

if (!empty($role_filter)) {
    $where[] = "u.role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if ($voted_filter === 'yes') {
    $where[] = "u.has_voted = TRUE";
} elseif ($voted_filter === 'no') {
    $where[] = "u.has_voted = FALSE";
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

// Prepare and execute
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Get statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
        SUM(CASE WHEN has_voted = TRUE AND role = 'user' THEN 1 ELSE 0 END) as voted_count,
        SUM(CASE WHEN has_voted = FALSE AND role = 'user' THEN 1 ELSE 0 END) as not_voted_count
    FROM users
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Administrator</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .nav a {
            margin-right: 15px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }

        .nav a:hover {
            text-decoration: underline;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 1rem;
            color: #666;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }

        .stat-card.admin {
            border-top: 4px solid #dc3545;
        }

        .stat-card.users {
            border-top: 4px solid #007bff;
        }

        .stat-card.voted {
            border-top: 4px solid #28a745;
        }

        .stat-card.not-voted {
            border-top: 4px solid #ffc107;
        }

        .stat-card.total {
            border-top: 4px solid #6c757d;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .users-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .users-table th {
            background: #007bff;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }

        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .users-table tr:hover {
            background: #f9f9f9;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .role-admin {
            background: #dc3545;
            color: white;
        }

        .role-user {
            background: #007bff;
            color: white;
        }

        .vote-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .voted-yes {
            background: #28a745;
            color: white;
        }

        .voted-no {
            background: #ffc107;
            color: #212529;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 3px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-reset {
            background: #17a2b8;
            color: white;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-toggle {
            background: #ffc107;
            color: #212529;
        }

        .btn-role {
            background: #6f42c1;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /*.export-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .btn-export {
            background: #28a745;
            color: white;
        }

        .btn-export:hover {
            background: #218838;
        }*/

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
        }

        .pagination a.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .users-table {
                font-size: 0.9rem;
            }

            .users-table th,
            .users-table td {
                padding: 10px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }
        }

        .add-user-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .add-user-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .btn-add {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }

        .btn-add:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .add-user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .add-user-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .toggle-add-form {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .toggle-add-form:hover {
            background: #545b62;
        }

        /*.password-hint {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }

        .quick-add-section {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .quick-add-title {
            font-weight: bold;
            color: #0056b3;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quick-add-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .quick-add-btn {
            padding: 12px;
            background: white;
            border: 2px solid #007bff;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }

        .quick-add-btn:hover {
            background: #007bff;
            color: white;
            transform: translateY(-2px);
        }

        .quick-add-btn h4 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .quick-add-btn:hover h4 {
            color: white;
        }

        .quick-add-btn p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }

        .quick-add-btn:hover p {
            color: rgba(255, 255, 255, 0.9);
        }*/

        /* Add User Modal */
        #addUserModal .modal-content {
            max-width: 600px;
        }

        .role-option {
            display: flex;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .role-option:hover {
            background: #f8f9fa;
            border-color: #007bff;
        }

        .role-option input[type="radio"] {
            margin-right: 10px;
        }

        .role-option label {
            cursor: pointer;
            flex: 1;
        }

        .role-description {
            font-size: 0.9rem;
            color: #666;
            margin-left: 25px;
        }

        /*.auto-generate {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .generate-btn {
            background: #17a2b8;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .generate-btn:hover {
            background: #138496;
        }*/

        /* Styling for footer */
        .simple-footer {
            background-color: #2c3e50;
            color: #ecf0f1;
            padding: 25px 0;
            border-top: 5px solid #3498db;
            margin-top: auto;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .copyright {
            font-size: 0.9em;
            color: #bdc3c7;
        }

        .footer-logo {
            display: none; 
        }

        .footer-content::after {
            display: none;
        }

        .copyright a {
            color: #3498db;
            text-decoration: none;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .add-user-form {
                grid-template-columns: 1fr;
            }

            .quick-add-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        function openModal(modalId, userId, username) {
            const modal = document.getElementById(modalId);
            if (modalId === 'resetPasswordModal') {
                document.getElementById('reset_user_id').value = userId;
                document.getElementById('reset_username').textContent = username;
            } else if (modalId === 'deleteUserModal') {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('delete_username').textContent = username;
            } else if (modalId === 'toggleVoteModal') {
                document.getElementById('toggle_user_id').value = userId;
                document.getElementById('toggle_username').textContent = username;
            } else if (modalId === 'changeRoleModal') {
                document.getElementById('role_user_id').value = userId;
                document.getElementById('role_username').textContent = username;
            }
            modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function confirmDelete(username) {
            return confirm(`Apakah kamu yakin ingin menghapus "${username}"?`);
        }

        function exportUsers(format) {
            const search = document.querySelector('input[name="search"]').value;
            const role = document.querySelector('select[name="role"]').value;
            const voted = document.querySelector('select[name="voted"]').value;

            window.location.href = `export_users.php?format=${format}&search=${encodeURIComponent(search)}&role=${role}&voted=${voted}`;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }

        /*function generatePassword() {
            const length = 8;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            let password = "";
            for (let i = 0; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            document.getElementById('password').value = password;
            document.getElementById('confirm_password').value = password;
        }

        function generateUsername() {
            const prefixes = ['user', 'voter', 'member', 'participant'];
            const suffixes = ['2024', 'election', 'vote', 'user'];
            const prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
            const suffix = suffixes[Math.floor(Math.random() * suffixes.length)];
            const randomNum = Math.floor(Math.random() * 1000);
            const username = `${prefix}_${suffix}_${randomNum}`;
            document.getElementById('username').value = username;
        }

        function quickAddUser(role, count = 1) {
            if (!confirm(`Create ${count} new ${role} user(s) with auto-generated credentials?`)) {
                return;
            }

            // In a real application, this would be an AJAX call
            // For now, we'll redirect to a processing page
            window.location.href = `quick_add_users.php?role=${role}&count=${count}`;
        }*/

        function toggleAddForm() {
            const form = document.getElementById('addUserForm');
            const toggleBtn = document.querySelector('.toggle-add-form');

            if (form.style.display === 'none') {
                form.style.display = 'grid';
                toggleBtn.textContent = 'Sembunyikan';
            } else {
                form.style.display = 'none';
                toggleBtn.textContent = 'Add New User';
            }
        }

        function validateAddUserForm() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (username.length < 3) {
                alert('Username must be at least 3 characters long');
                return false;
            }

            if (password.length < 4) {
                alert('Password must be at least 4 characters long');
                return false;
            }

            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return false;
            }

            return true;
        }

        function checkUsername() {
            const username = document.getElementById('username').value.trim();
            const checkSpan = document.getElementById('username-check');

            if (username.length < 12) {
                checkSpan.innerHTML = '<span style="color: #ffc107;">Minimum 12 characters</span>';
                return;
            }

            if (username.length > 50) {
                checkSpan.innerHTML = '<span style="color: #dc3545;">Maximum 50 characters</span>';
                return;
            }

            // Simple validation
            const usernameRegex = /^[a-zA-Z0-9_]+$/;
            if (!usernameRegex.test(username)) {
                checkSpan.innerHTML = '<span style="color: #dc3545;">Only letters, numbers, and underscores allowed</span>';
                return;
            }

            checkSpan.innerHTML = '<span style="color: #28a745;">✓ Valid format</span>';
        }
    </script>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0; color: #333;">Panel Manajemen Pengguna</h1>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="candidates.php">Kelola Kandidat</a>
            <a href="users.php" style="color: #dc3545;">Kelola Pengguna</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="warning">
        <strong>Penting:</strong> Sebagai administrator, kamu dapat menambah pengguna, mereset password, menghapus pengguna, dan mengubah status voting mereka.
    </div>

    <!-- Add New User Section -->
    <div class="add-user-section">
        <div class="add-user-header">
            <div class="add-user-title">Tambah Pengguna Baru</div>
            <button class="toggle-add-form" onclick="toggleAddForm()">Klik Disini</button>
        </div>

        <form id="addUserForm" method="POST" action="" onsubmit="return validateAddUserForm()" style="display: none;">
            <div class="add-user-form">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required
                        placeholder="Masukkan username"
                        onkeyup="checkUsername()">
                    <div id="username-check" class="password-hint"></div>
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required
                        placeholder="Masukkan password">
                    <!--<div class="password-hint">Minimum 4 characters</div>-->
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                        placeholder="Konfirmasi password">
                </div>

                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="user">Pengguna biasa</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" name="add_user" class="btn-add">
                        <span style="margin-right: 5px;">+</span> Tambahkan!
                    </button>
                </div>
            </div>

            <!--<div class="auto-generate">
                <button type="button" class="generate-btn" onclick="generateUsername()">Generate Username</button>
                <button type="button" class="generate-btn" onclick="generatePassword()">Generate Password</button>
            </div>-->
        </form>
    </div>

    <div class="stats-cards">
        <div class="stat-card total">
            <h3>Jumlah Pengguna</h3>
            <div class="number"><?php echo $stats['total_users']; ?></div>
        </div>
        <div class="stat-card admin">
            <h3>Admins</h3>
            <div class="number"><?php echo $stats['admin_count']; ?></div>
        </div>
        <div class="stat-card users">
            <h3>Pengguna Biasa</h3>
            <div class="number"><?php echo $stats['user_count']; ?></div>
        </div>
        <div class="stat-card voted">
            <h3>Sudah Memilih</h3>
            <div class="number"><?php echo $stats['voted_count']; ?></div>
        </div>
        <div class="stat-card not-voted">
            <h3>Belum Memilih</h3>
            <div class="number"><?php echo $stats['not_voted_count']; ?></div>
        </div>
    </div>

    <div class="filters">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label for="search">Cari Usernamae/ID:</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search users...">
            </div>

            <div class="filter-group">
                <label for="role">Role Filter:</label>
                <select id="role" name="role">
                    <option value="">Semua role</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                    <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Pengguna Biasa</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="voted">Status Pemilihan:</label>
                <select id="voted" name="voted">
                    <option value="">Semua Status</option>
                    <option value="yes" <?php echo $voted_filter === 'yes' ? 'selected' : ''; ?>>Sudah Memilih</option>
                    <option value="no" <?php echo $voted_filter === 'no' ? 'selected' : ''; ?>>Belum Memilih</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                <!--<a href="users.php" class="btn btn-secondary">Bersihkan Filter</a>-->
            </div>
        </form>
    </div>

    <!--<div class="export-buttons">
        <button class="btn btn-export" onclick="exportUsers('csv')">Export as CSV</button>
        <button class="btn btn-export" onclick="exportUsers('pdf')">Export as PDF</button>
    </div>-->

    <table class="users-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status Suara</th>
                <th>Total Suara</th>
                <th>Terakhir Memilih</th>
                <th>Dibuat pada</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($users->num_rows > 0): ?>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                <span style="color: #dc3545; font-size: 0.8rem;">(You)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="vote-status voted-<?php echo $user['has_voted'] ? 'yes' : 'no'; ?>">
                                <?php echo $user['has_voted'] ? 'Sudah Memilih' : 'Belum Memilih'; ?>
                            </span>
                        </td>
                        <td><?php echo $user['total_votes']; ?></td>
                        <td>
                            <?php
                            if ($user['last_voted']) {
                                echo date('M d, Y H:i', strtotime($user['last_voted']));
                            } else {
                                echo 'Tidak pernah';
                            }
                            ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-small btn-reset" onclick="openModal('resetPasswordModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                    Reset Password
                                </button>

                                <button class="btn-small btn-toggle" onclick="openModal('toggleVoteModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                    <?php echo $user['has_voted'] ? 'Belum' : 'Sudah'; ?>
                                </button>

                                <!--<button class="btn-small btn-role" onclick="openModal('changeRoleModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                    Change Role
                                </button>-->

                                <?php if ($user['role'] !== 'admin' || $user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn-small btn-delete" onclick="openModal('deleteUserModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        Hapus
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 30px; color: #666;">
                        No users found. <?php if (!empty($search) || !empty($role_filter) || !empty($voted_filter)): ?>Try changing your filters.<?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Reset Password</div>
                <button class="modal-close" onclick="closeModal('resetPasswordModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="reset_user_id" name="user_id">

                <div class="form-group">
                    <label>Username:</label>
                    <div id="reset_username" style="padding: 10px; background: #f8f9fa; border-radius: 4px;"></div>
                </div>

                <div class="form-group">
                    <label for="new_password">Password Baru:</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Masukkan password baru">
                    <small style="color: #666;">Minimal 4 karakter</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Konfirmasi password baru">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Batal</button>
                    <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Hapus Pengguna</div>
                <button class="modal-close" onclick="closeModal('deleteUserModal')">&times;</button>
            </div>
            <form method="POST" action="" onsubmit="return confirmDelete(document.getElementById('delete_username').textContent)">
                <input type="hidden" id="delete_user_id" name="user_id">

                <div style="padding: 20px; background: #fff3cd; border-radius: 5px; margin-bottom: 20px;">
                    ⚠️ <strong>Peringatan:</strong> Kamu akan menghapus user: <strong id="delete_username"></strong>
                    <br><br>
                    Aksi ini akan:
                    <ul>
                        <li>Secara permanen menghapus akun pengguna</li>
                        <li>Menghapus semua suara dari pengguna</li>
                        <li>Aksi ini tidak bisa dibatalkan!</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="confirm_delete">Ketik "DELETE" untuk konfirmasi:</label>
                    <input type="text" id="confirm_delete" name="confirm_delete" required placeholder="Ketik di sini">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteUserModal')">Batal</button>
                    <button type="submit" name="delete_user" class="btn btn-danger" style="background: #dc3545; color: white;">Hapus Pengguna</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Vote Status Modal -->
    <div id="toggleVoteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Ganti Status Suara</div>
                <button class="modal-close" onclick="closeModal('toggleVoteModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="toggle_user_id" name="user_id">

                <div class="form-group">
                    <label>Username:</label>
                    <div id="toggle_username" style="padding: 10px; background: #f8f9fa; border-radius: 4px;"></div>
                </div>

                <div style="padding: 15px; background: #e7f3ff; border-radius: 5px; margin-bottom: 20px;">
                    Mengganti status suara pengguna akan mengubah apakah mereka dianggap telah memilih atau belum dalam sistem.
                    <br><br>
                    <strong>Note:</strong> Ini tidak menghapus suara mereka, hanya mengubah status pemilihan mereka.
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('toggleVoteModal')">Batal</button>
                    <button type="submit" name="toggle_vote_status" class="btn btn-primary">Ganti Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Role Modal -->
    <div id="changeRoleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Change User Role</div>
                <button class="modal-close" onclick="closeModal('changeRoleModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="role_user_id" name="user_id">

                <div class="form-group">
                    <label>Username:</label>
                    <div id="role_username" style="padding: 10px; background: #f8f9fa; border-radius: 4px;"></div>
                </div>

                <div class="form-group">
                    <label for="new_role">New Role:</label>
                    <select id="new_role" name="new_role" required>
                        <option value="user">Regular User</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <div style="padding: 15px; background: #e7f3ff; border-radius: 5px; margin-bottom: 20px;">
                    <strong>Administrator Privileges:</strong>
                    <ul>
                        <li>Can add/edit/delete candidates</li>
                        <li>Can manage other users</li>
                        <li>Can reset passwords</li>
                        <li>Full access to admin dashboard</li>
                    </ul>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('changeRoleModal')">Cancel</button>
                    <button type="submit" name="change_role" class="btn btn-primary">Change Role</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="simple-footer">
        <div class="footer-content">
            <div class="copyright">
                &copy; <span id="current-year"></span> E-Voting. All Rights Reserved. Made with ❤️ by 
                <a href="https://github.com/fariskhoiri">Guess Who I am.</a>
            </div>
        </div>
    </footer>

    <script>
        document.getElementById('current-year').textContent = new Date().getFullYear();
    </script>
</body>

</html>
<?php
$stmt->close();
$conn->close();
?>