<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$conn = getConnection();

// Get parameters
$role = isset($_GET['role']) ? $_GET['role'] : 'user';
$count = isset($_GET['count']) ? intval($_GET['count']) : 1;
$count = min($count, 20); // Limit to 20 users at once for safety

$created_users = [];

// Generate and insert users
for ($i = 1; $i <= $count; $i++) {
    // Generate unique username
    $prefix = ($role === 'admin') ? 'admin' : 'voter';
    $username = $prefix . '_' . time() . '_' . $i;
    
    // Generate random password
    $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    
    // Check if username exists (unlikely but just in case)
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $password, $role);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Log the creation
            $admin_id = $_SESSION['user_id'];
            $logStmt = $conn->prepare("INSERT INTO user_creation_logs (admin_id, user_id, created_at) VALUES (?, ?, NOW())");
            $logStmt->bind_param("ii", $admin_id, $user_id);
            $logStmt->execute();
            $logStmt->close();
            
            $created_users[] = [
                'id' => $user_id,
                'username' => $username,
                'password' => $password,
                'role' => $role
            ];
        }
        $stmt->close();
    }
    $checkStmt->close();
}

// Redirect back with success message
if (!empty($created_users)) {
    $_SESSION['quick_add_success'] = [
        'count' => count($created_users),
        'role' => $role,
        'users' => $created_users
    ];
}

redirect('users.php');
?>