<?php
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Username and password are required!";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long!";
    } elseif (strlen($password) < 4) {
        $error = "Password must be at least 4 characters long!";
    } elseif (strlen($username) > 50) {
        $error = "Username is too long (max 50 characters)!";
    } else {
        // Check if username already exists
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username '$username' is already taken. Please choose another.";
        } else {
            // Register the user
            if (registerUser($username, $password)) {
                $success = "Registration successful! You can now login.";
                // Clear form
                $username = '';
                $password = '';
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Election System</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 400px; 
            margin: 50px auto; 
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            width: 100%;
        }
        h2 { 
            text-align: center; 
            color: #333;
            margin-bottom: 30px;
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        .error { 
            background: #ffebee; 
            color: #c62828; 
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
            font-size: 0.95rem;
        }
        .success { 
            background: #e8f5e9; 
            color: #2e7d32; 
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
            font-size: 0.95rem;
        }
        .info {
            background: #e3f2fd;
            color: #1565c0;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #1565c0;
            font-size: 0.95rem;
        }
        button { 
            background: linear-gradient(90deg, #667eea, #764ba2);
            color: white; 
            padding: 14px 20px; 
            border: none; 
            border-radius: 5px; 
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        button:active {
            transform: translateY(0);
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 5px;
            border-left: 3px solid #ddd;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .form-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }
        .username-availability {
            font-size: 0.85rem;
            margin-top: 5px;
            min-height: 20px;
        }
        .available {
            color: #2e7d32;
        }
        .taken {
            color: #c62828;
        }
    </style>
    <script>
        function checkUsernameAvailability() {
            const username = document.getElementById('username').value.trim();
            const availabilityDiv = document.getElementById('username-availability');
            
            if (username.length < 3) {
                availabilityDiv.innerHTML = '';
                return;
            }
            
            // Simple client-side validation
            if (username === 'admin') {
                availabilityDiv.innerHTML = '<span class="taken">Username "admin" is reserved</span>';
                return;
            }
            
            // You could add AJAX call here for real-time checking
            // For now, we'll rely on server-side validation
        }
        
        function validateForm() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (username.length < 3) {
                alert('Username must be at least 3 characters long');
                return false;
            }
            
            if (password.length < 4) {
                alert('Password must be at least 4 characters long');
                return false;
            }
            
            return true;
        }
    </script>
</head>
<body>
    <div class="register-container">
        <h2>Create Account</h2>
        
        <?php if ($error): ?>
            <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="info">
            ℹ️ Register to participate in the presidential election. Each user can vote only once.
        </div>
        
        <form method="POST" action="" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" 
                       onkeyup="checkUsernameAvailability()"
                       required
                       placeholder="Enter username (min 3 chars)">
                <div id="username-availability" class="username-availability"></div>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       value="<?php echo isset($password) ? htmlspecialchars($password) : ''; ?>" 
                       required
                       placeholder="Enter password (min 4 chars)">
                <div class="password-requirements">
                    • Must be at least 4 characters long<br>
                    • Use a combination of letters and numbers for security
                </div>
            </div>
            
            <button type="submit">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
        
        <div class="form-footer">
            This is an educational system. For demonstration purposes only.
        </div>
    </div>
</body>
</html>