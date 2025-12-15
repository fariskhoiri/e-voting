<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../login.php');
}

$conn = getConnection();
$message = '';

// Check if user has already voted
if ($_SESSION['has_voted']) {
    $message = "Kamu sudah memilih!";
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_SESSION['has_voted']) {
    $candidate_id = $_POST['candidate_id'];
    $user_id = $_SESSION['user_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert vote record
        $stmt = $conn->prepare("INSERT INTO votes (user_id, candidate_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $candidate_id);
        $stmt->execute();
        $stmt->close();

        // Update candidate vote count
        $stmt = $conn->prepare("UPDATE candidates SET votes = votes + 1 WHERE id = ?");
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $stmt->close();

        // Mark user as voted
        $stmt = $conn->prepare("UPDATE users SET has_voted = TRUE WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Update session
        $_SESSION['has_voted'] = true;

        $conn->commit();
        $message = "Vote submitted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error submitting vote: " . $e->getMessage();
    }
}

// Get all candidates
$candidates = $conn->query("SELECT * FROM candidates ORDER BY name");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilik Suara Digital</title>

    <?php require_once '../includes/head_styles.php'; ?>

    <style>
        /* Style Tambahan Khusus Halaman Vote */
        .vote-card {
            text-align: center;
            padding-top: 0;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .vote-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        /* Highlight border saat dipilih (opsional, bisa via JS) */
        .vote-card.selected {
            border: 2px solid var(--primary);
            background: #f0f9ff;
        }

        .candidate-img-large {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin: -70px auto 20px auto;
            position: relative;
            z-index: 10;
            background: white;
        }

        .candidate-header-bg {
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, #818cf8 100%);
            margin: -24px -24px 0 -24px;
            /* Menutupi padding card */
        }

        .success-banner {
            background: #d1fae5;
            color: #065f46;
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 30px;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a href="dashboard.php" class="brand">
            <i class="fas fa-vote-yea"></i> Bilik Suara
        </a>
        <div class="nav-links">
            <a href="dashboard.php"><i class="fas fa-chevron-left"></i> Kembali ke Dashboard</a>
            <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">

        <div class="page-header" style="text-align: center; border: none; margin-bottom: 40px;">
            <?php if (!$_SESSION['has_voted']): ?>
                <h1 style="font-size: 2.5rem; margin-bottom: 10px;">Tentukan Pilihanmu</h1>
                <p class="text-gray" style="font-size: 1.1rem;">
                    Silakan pilih satu kandidat di bawah ini. Pilihan Anda bersifat rahasia dan tidak dapat diubah setelah dikirim.
                </p>
            <?php else: ?>
                <div class="success-banner">
                    <i class="fas fa-check-circle" style="font-size: 4rem; margin-bottom: 15px;"></i>
                    <h2 style="margin: 0 0 10px 0;">Terima Kasih Telah Berpartisipasi!</h2>
                    <p style="font-size: 1.1rem;">Suara Anda telah berhasil direkam. "May the justice be with you, always!"</p>
                    <div style="margin-top: 20px;">
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-chart-bar"></i> Lihat Hasil Sementara
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div style="padding: 15px; margin-bottom: 30px; text-align: center; border-radius: 8px; font-weight: bold;
                 background: <?php echo strpos($message, 'successfully') !== false ? '#d1fae5' : '#fee2e2'; ?>; 
                 color: <?php echo strpos($message, 'successfully') !== false ? '#065f46' : '#991b1b'; ?>;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!$_SESSION['has_voted']): ?>
            <form method="POST" action="" id="voteForm">
                <div class="grid-3">
                    <?php while ($candidate = $candidates->fetch_assoc()): ?>
                        <div class="card vote-card">

                            <div class="candidate-header-bg"></div>

                            <div class="candidate-img-large">
                                <?php if ($candidate['photo_filename']): ?>
                                    <img src="../uploads/candidate_photos/<?php echo htmlspecialchars($candidate['photo_filename']); ?>"
                                        alt="<?php echo htmlspecialchars($candidate['name']); ?>"
                                        style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                                        onerror="this.src='https://via.placeholder.com/140?text=<?php echo substr($candidate['name'], 0, 1); ?>'">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: var(--light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: bold; border-radius: 50%;">
                                        <?php echo strtoupper(substr($candidate['name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h3 style="margin-bottom: 5px; color: var(--dark);"><?php echo htmlspecialchars($candidate['name']); ?></h3>
                            <div style="margin-bottom: 15px;">
                                <span class="badge badge-admin" style="font-size: 0.9rem;"><?php echo htmlspecialchars($candidate['party']); ?></span>
                            </div>

                            <p style="color: #666; font-size: 0.95rem; line-height: 1.5; margin-bottom: 25px; min-height: 60px;">
                                <?php echo htmlspecialchars($candidate['description']); ?>
                            </p>

                            <button type="submit" name="candidate_id" value="<?php echo $candidate['id']; ?>"
                                class="btn btn-primary" style="width: 100%; border-radius: 50px; padding: 12px;"
                                onclick="return confirm('Apakah Anda yakin ingin memilih <?php echo htmlspecialchars($candidate['name']); ?>? Pilihan tidak dapat diubah.');">
                                <i class="fas fa-check"></i> Pilih Kandidat Ini
                            </button>
                        </div>
                    <?php endwhile; ?>
                </div>
            </form>
        <?php endif; ?>

    </div>

    <div class="main-footer">
        <div class="copyright">
            &copy; <span id="current-year"></span> E-Voting System. Hak Cipta Dilindungi.
        </div>
    </div>
    <script>
        document.getElementById('current-year').textContent = new Date().getFullYear();
    </script>

</body>

</html>
<?php $conn->close(); ?>