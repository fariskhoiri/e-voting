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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --dark: #1f2937;
            --gray: #9ca3af;
            --light: #f3f4f6;
        }

        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);

            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Poppins', sans-serif;
            margin: 0;
        }

        /*body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }*/

        .login-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .icon-brand {
            width: 60px;
            height: 60px;
            background: #eef2ff;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 15px auto;
        }

        /* --- PERBAIKAN INPUT FORM YANG PRESISI --- */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.9rem;
        }

        /* Wrapper khusus untuk input dan ikon */
        .input-wrapper {
            position: relative;
            /* Kunci agar ikon absolute mengacu ke sini */
            width: 100%;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            /* Padding kiri 45px memberi ruang untuk ikon */
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s;
            box-sizing: border-box;
            /* Penting agar padding tidak melebarkan input */
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            /* Posisikan titik atas ikon di tengah vertikal container */
            transform: translateY(-50%);
            /* Geser ikon ke atas 50% ukurannya sendiri agar benar-benar di tengah */
            color: #9ca3af;
            font-size: 1.1rem;
            pointer-events: none;
            /* Agar klik pada ikon tembus ke input */
            transition: color 0.3s;
        }

        /* Ubah warna ikon saat input difokuskan */
        .form-control:focus+.input-icon,
        /* Selector fallback */
        .form-control:focus~.input-icon {
            color: var(--primary);
        }

        /* ----------------------------------------- */

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 10px;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .auth-links {
            margin-top: 25px;
            text-align: center;
            font-size: 0.9rem;
        }

        .auth-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .separator {
            margin: 25px 0;
            border-top: 1px solid #e5e7eb;
            position: relative;
            text-align: center;
        }

        .separator span {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 0 10px;
            color: #9ca3af;
            font-size: 0.8rem;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-header">
            <div class="icon-brand">
                <i class="fas fa-vote-yea"></i>
            </div>
            <h2 style="margin: 0; color: #333;">Selamat Datang</h2>
            <p style="color: #666; margin-top: 5px;">Silakan masuk untuk melanjutkan</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <div class="input-wrapper">
                    <input type="text" name="username" class="form-control"
                        value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
                        placeholder="Masukkan username" required autofocus>
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" class="form-control"
                        placeholder="Masukkan password" required>
                    <i class="fas fa-lock input-icon"></i>
                </div>
            </div>

            <div style="text-align: right; margin-bottom: 20px;">
                <a href="#" onclick="alert('Silakan hubungi Administrator untuk reset password.'); return false;"
                    style="font-size: 0.85rem; color: #666; text-decoration: none;">Lupa Password?</a>
            </div>

            <button type="submit" class="btn-login">
                Masuk Sekarang <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
            </button>
        </form>

        <div class="separator">
            <span>ATAU</span>
        </div>

        <div class="auth-links">
            Belum punya akun?
            <a href="https://wa.link/uz3ox6" target="_blank">
                Daftar disini
            </a>
        </div>

        <div style="margin-top: 30px; text-align: center; font-size: 0.75rem; color: #9ca3af;">
            &copy; <?php echo date('Y'); ?> E-Voting System
        </div>
    </div>
</body>

</html>