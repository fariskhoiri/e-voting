<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($url)
{
    header("Location: $url");
    exit();
}

function countCandidates()
{
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM candidates");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row['count'];
}

// New validation functions
function validateUsername($username)
{
    if (empty($username)) {
        return "Username is required";
    }

    if (strlen($username) < 3) {
        return "Username must be at least 3 characters";
    }

    if (strlen($username) > 50) {
        return "Username cannot exceed 50 characters";
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return "Username can only contain letters, numbers, and underscores";
    }

    if (strtolower($username) === 'admin') {
        return "Username 'admin' is reserved";
    }

    return null; // No error
}

function validatePassword($password)
{
    if (empty($password)) {
        return "Password is required";
    }

    if (strlen($password) < 4) {
        return "Password must be at least 4 characters";
    }

    return null; // No error
}

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function showError($message)
{
    echo '<div class="error">' . htmlspecialchars($message) . '</div>';
}

function showSuccess($message)
{
    echo '<div class="success">' . htmlspecialchars($message) . '</div>';
}

// New function to log user activity
function logActivity($user_id, $activity_type, $description)
{
    $conn = getConnection();

    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $activity_type, $description, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Function to check if user exists
function userExists($user_id)
{
    $conn = getConnection();

    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;

    $stmt->close();
    $conn->close();

    return $exists;
}

// Function to get user details
function getUserDetails($user_id)
{
    $conn = getConnection();

    $stmt = $conn->prepare("SELECT id, username, role, has_voted, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();
    $conn->close();

    return $user;
}

// Function to get password reset logs
function getPasswordResetLogs($limit = 50)
{
    $conn = getConnection();

    $stmt = $conn->prepare("
        SELECT prl.*, 
               admin.username as admin_name,
               user.username as user_name
        FROM password_reset_logs prl
        JOIN users admin ON prl.admin_id = admin.id
        JOIN users user ON prl.user_id = user.id
        ORDER BY prl.reset_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = [];

    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    $stmt->close();
    $conn->close();

    return $logs;
}
