<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang - E-Voting System</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        /* Override Style Khusus Halaman Landing */
        body {
            /* Background Gradient Modern */
            /*background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);*/

            /* JIKA INGIN TETAP PAKAI GAMBAR BACKGROUND, UN-COMMENT BARIS DI BAWAH INI: */
            background: url('indo-anniversary.png') no-repeat center center/cover;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;

            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Overlay gelap jika menggunakan gambar background agar teks terbaca */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }

        .landing-card {
            background: rgba(255, 255, 255, 0.98);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            max-width: 480px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Hiasan Garis Atas */
        .landing-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .icon-circle {
            width: 80px;
            height: 80px;
            background: #eef2ff;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 25px auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        h1 {
            font-size: 1.75rem;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 700;
        }

        p {
            color: var(--gray);
            margin-bottom: 35px;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn-wa {
            background: white;
            color: #25D366;
            /* Warna WA */
            border: 2px solid #25D366;
        }

        .btn-wa:hover {
            background: #25D366;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(37, 211, 102, 0.2);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .footer-credit {
            margin-top: 30px;
            font-size: 0.8rem;
            color: #9ca3af;
        }
    </style>
</head>

<body>

    <div class="landing-card">
        <div class="icon-circle">
            <i class="fas fa-vote-yea"></i>
        </div>

        <h1>E-Voting System</h1>
        <p>Selamat datang di platform pemilihan presiden digital yang transparan dan efisien. Gunakan hak suara Anda untuk masa depan yang lebih baik.</p>

        <div class="btn-group">
            <a href="login.php" class="btn btn-primary" style="padding: 12px; font-size: 1rem; border-radius: 50px;">
                <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i> Masuk ke Akun
            </a>

            <a href="https://wa.link/uz3ox6" target="_blank" class="btn btn-wa" style="padding: 12px; font-size: 1rem; border-radius: 50px; font-weight: 600; text-decoration: none; transition: 0.3s;">
                <i class="fab fa-whatsapp" style="margin-right: 8px;"></i> Daftar via WhatsApp
            </a>
        </div>

        <div class="footer-credit">
            &copy; <?php echo date('Y'); ?> E-Voting System. Created by <a href="#" style="color: var(--primary); text-decoration: none;">Guess Who I am</a>.
        </div>
    </div>

</body>

</html>