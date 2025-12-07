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

// Function to get candidate photo URL
function getCandidatePhoto($candidate, $size = 'medium') {
    if (!$candidate || !isset($candidate['photo_filename'])) {
        return null;
    }
    
    $photo_filename = $candidate['photo_filename'];
    if (empty($photo_filename)) {
        return null;
    }
    
    $upload_dir = 'uploads/candidate_photos/';
    $photo_path = $upload_dir . $photo_filename;
    
    // Check if file exists
    if (file_exists($photo_path)) {
        return $photo_path;
    }
    
    return null;
}

// Function to display candidate photo with fallback
function displayCandidatePhoto($candidate, $class = 'candidate-photo', $size = '150px') {
    $photo_url = getCandidatePhoto($candidate);
    
    if ($photo_url) {
        return '<img src="' . $photo_url . '" 
                    alt="' . htmlspecialchars($candidate['name']) . '" 
                    class="' . $class . '"
                    style="width: ' . $size . '; height: ' . $size . ';"
                    onerror="this.onerror=null; this.src=\'data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"' . str_replace('px', '', $size) . '\" height=\"' . str_replace('px', '', $size) . '\" viewBox=\"0 0 100 100\"><circle cx=\"50\" cy=\"50\" r=\"45\" fill=\"%23667eea\"/><text x=\"50\" y=\"60\" font-family=\"Arial\" font-size=\"40\" fill=\"white\" text-anchor=\"middle\">' . strtoupper(substr($candidate['name'], 0, 1)) . '</text></svg>\'">';
    } else {
        // Return placeholder with initial
        $initial = strtoupper(substr($candidate['name'], 0, 1));
        return '<div class="photo-placeholder" style="width: ' . $size . '; height: ' . $size . '; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: ' . (intval(str_replace('px', '', $size)) * 0.4) . 'px; font-weight: bold;">' . $initial . '</div>';
    }
}

// Function to validate uploaded photo
function validatePhotoUpload($file) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'No file uploaded or upload error occurred.';
        return $errors;
    }
    
    // Check file size (2MB max)
    $max_size = 2 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds 2MB limit.';
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = 'Invalid file type. Allowed: JPG, PNG, GIF, WebP.';
    }
    
    return $errors;
}

// Function to upload candidate photo
function uploadCandidatePhoto($file, $candidate_id) {
    $upload_dir = 'uploads/candidate_photos/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'candidate_' . $candidate_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $upload_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'filename' => $filename,
            'path' => $upload_path,
            'mime_type' => mime_content_type($upload_path),
            'size' => filesize($upload_path)
        ];
    }
    
    return false;
}
