<?php
require_once 'functions.php';

function login($username, $password)
{
    $conn = getConnection();

    $stmt = $conn->prepare("SELECT id, username, password, role, has_voted FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Compare plaintext passwords (EDUCATIONAL ONLY)
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['has_voted'] = $user['has_voted'];

            $stmt->close();
            $conn->close();
            return true;
        }
    }

    $stmt->close();
    $conn->close();
    return false;
}

function registerUser($username, $password, $role = 'user')
{
    $conn = getConnection();

    // Validate username doesn't exist
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        $conn->close();
        return false; // Username already exists
    }
    $checkStmt->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hashed_password, $role);

    $success = $stmt->execute();

    $stmt->close();
    $conn->close();

    return $success;
}

// New function to check username availability
function checkUsernameAvailable($username)
{
    $conn = getConnection();

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $available = $result->num_rows === 0;

    $stmt->close();
    $conn->close();

    return $available;
}
