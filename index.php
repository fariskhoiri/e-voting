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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presidential Election System</title>

    <!-- Mengambil Font Modern dari Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Mengambil Icon Library (FontAwesome) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #0d47a1;
            /* Biru Tua Resmi */
            --primary-hover: #1565c0;
            --secondary-color: #e0e0e0;
            --text-color: #333;
            --bg-gradient-start: #ECE9E6;
            --bg-gradient-end: #FFFFFF;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
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

        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            text-align: center;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        /* Hiasan dekoratif di atas kartu */
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #d32f2f, #ffffff);
        }

        .icon-header {
            font-size: 3.5rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: inline-block;
        }

        h1 {
            color: #1a237e;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }

        p {
            color: #546e7a;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 35px;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Style dasar tombol */
        .btn {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 14px 25px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
        }

        .btn i {
            margin-right: 10px;
        }

        /* Tombol Login (Primary) */
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 15px rgba(13, 71, 161, 0.3);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(13, 71, 161, 0.4);
        }

        /* Tombol Register (Outline/Secondary) */
        .btn-secondary {
            background-color: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: #f0f4ff;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        /* Responsiveness untuk layar tablet ke atas */
        @media (min-width: 480px) {
            .nav {
                flex-direction: row;
                justify-content: center;
            }

            .btn {
                flex: 1;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Icon Kotak Suara -->
        <!-- <div class="icon-header">
            <i class="fas fa-vote-yea"></i>
        </div> -->

        <h1>Pemilihan Presiden</h1>
        <p>Selamat datang di platform pemilihan presiden digital. Silakan login untuk memberikan suara kamu atau register untuk menjadi pemberi suara.</p>

        <div class="nav">
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
            <a href="https://wa.link/uz3ox6" class="btn btn-secondary" target="_blank">
                <i class="fa-brands fa-whatsapp"></i> Register
            </a>
        </div>
    </div>
</body>

</html>