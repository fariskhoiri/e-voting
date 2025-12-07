<?php
require_once 'includes/auth.php';

$error = '';
$success = '';

// Check for registration success message
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = "Pedaftaran berhasil! Silakan login.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Tolong masukkan password dan username!";
    } else {
        if (login($username, $password)) {
            if (isAdmin()) {
                redirect('admin/dashboard.php');
            } else {
                redirect('user/dashboard.php');
            }
        } else {
            // Check if username exists to give specific error
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error = "Username tidak ditemukan.";
            } else {
                $error = "Password salah, silakan coba lagi.";
            }

            $stmt->close();
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Pemilu</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background: url('indo-anniversary.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;

            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: var(--text-color);
        }

        .login-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
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

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        input:focus {
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

        .error a {
            color: #c62828;
            font-weight: bold;
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

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .demo-credentials {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #2196f3;
            font-size: 0.9rem;
        }

        .demo-credentials h4 {
            margin: 0 0 10px 0;
            color: #1565c0;
        }

        .form-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }

        .forgot-link {
            text-align: center;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .forgot-link a {
            color: #666;
            text-decoration: none;
        }

        .forgot-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h2>Login ke Sistem Pemilu</h2>

        <?php if ($error): ?>
            <div class="error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required placeholder="Masukkan username Anda">
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required placeholder="Masukkan password Anda">
            </div>
            <button type="submit">Login</button>
        </form>

        <div class="forgot-link">
            <a href="#" onclick="alert('Hubungi admnistrator untuk mereset password.'); return false;">Lupa password?</a>
        </div>

        <div class="register-link">
            Tidak punya akun? <a href="#">Daftar disini</a>
        </div>

        <!-- 
        <div class="demo-credentials">
            <h4>Demo Accounts:</h4>
            <strong>Admin:</strong> admin / admin123<br>
            <strong>User:</strong> user1 / password123
        </div>
        -->

        <div class="form-footer">
            Sistem dibuat untuk keperluan pemilu presiden. Setiap pengguna hanya dapat memilih satu kali.
        </div>
    </div>
</body>

</html>