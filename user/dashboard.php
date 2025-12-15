<?php
require_once '../includes/functions.php';

// Cek apakah user login dan BUKAN admin (karena ini halaman Voter)
if (!isLoggedIn() || isAdmin()) {
    redirect('../login.php');
}

$conn = getConnection();

// Get total votes
$totalVotesResult = $conn->query("SELECT SUM(votes) as total_votes FROM candidates");
$totalVotesRow = $totalVotesResult->fetch_assoc();
$totalVotes = $totalVotesRow['total_votes'] ?: 0;

// Get candidates with percentage
$candidates = $conn->query("
    SELECT *, 
           CASE 
               WHEN $totalVotes > 0 THEN ROUND((votes * 100.0) / $totalVotes, 2)
               ELSE 0 
           END as percentage
    FROM candidates 
    ORDER BY votes DESC
");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pemilih</title>

    <?php require_once '../includes/head_styles.php'; ?>

    <style>
        /* Style Tambahan Khusus Halaman Voter */
        .hero-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 16px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        }

        .hero-pattern {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            opacity: 0.1;
            background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0);
            background-size: 20px 20px;
        }

        .candidate-img-wrapper {
            width: 120px;
            height: 120px;
            margin: -60px auto 20px auto;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: white;
            position: relative;
            z-index: 10;
        }

        .candidate-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .rank-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 20;
            background: #10b981;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .vote-card {
            margin-top: 40px;
            /* Space untuk foto yang menonjol keluar */
            padding-top: 0;
            text-align: center;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a href="dashboard.php" class="brand">
            <i class="fas fa-vote-yea"></i> E-Voting
        </a>
        <div class="nav-links">
            <span style="padding: 0.5rem 1rem; color: var(--gray); font-size: 0.9rem;">
                Halo, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            </span>
            <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">

        <div class="hero-banner">
            <div class="hero-pattern"></div>
            <div style="position: relative; z-index: 2; text-align: center;">
                <?php if ($_SESSION['has_voted']): ?>
                    <div style="font-size: 3rem; margin-bottom: 10px;"><i class="fas fa-check-circle"></i></div>
                    <h1 style="margin: 0; color: white;">Terima Kasih Telah Memilih!</h1>
                    <p style="margin-top: 10px; opacity: 0.9;">Suara Anda telah berhasil direkam dalam sistem. Berikut adalah hasil sementara pemilihan.</p>
                <?php else: ?>
                    <div style="font-size: 3rem; margin-bottom: 10px;"><i class="fas fa-box-open"></i></div>
                    <h1 style="margin: 0; color: white;">Gunakan Hak Pilih Anda!</h1>
                    <p style="margin-top: 10px; opacity: 0.9;">Masa depan ada di tangan Anda. Silakan pilih kandidat terbaik di bawah ini.</p>
                    <a href="vote.php" class="btn" style="background: white; color: var(--primary); margin-top: 20px; font-weight: bold;">
                        <i class="fas fa-vote-yea"></i> Mulai Voting Sekarang
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 15px 25px; border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0;">
            <div>
                <h3 style="margin: 0; font-size: 1.1rem;"><i class="fas fa-chart-bar"></i> Hasil Real-time</h3>
                <p style="margin: 0; font-size: 0.85rem; color: var(--gray);">Data diperbarui secara otomatis saat suara masuk.</p>
            </div>
            <?php if ($totalVotes > 0): ?>
                <div class="badge badge-admin" style="font-size: 0.9rem; padding: 8px 15px;">
                    Total Suara Masuk: <strong><?php echo $totalVotes; ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($candidates->num_rows > 0): ?>
            <div class="grid-3">
                <?php
                $isFirst = true;
                while ($candidate = $candidates->fetch_assoc()):
                ?>
                    <div class="card vote-card" style="position: relative;">

                        <?php if ($isFirst && $candidate['votes'] > 0): ?>
                            <div class="rank-badge"><i class="fas fa-crown"></i> Unggul Sementara</div>
                            <?php $isFirst = false; ?>
                        <?php endif; ?>

                        <div style="height: 60px; background: linear-gradient(to right, #e0e7ff, #f3f4f6); border-radius: 12px 12px 0 0; position: absolute; top: 0; left: 0; width: 100%;"></div>

                        <div class="candidate-img-wrapper">
                            <?php if ($candidate['photo_filename']): ?>
                                <img src="../uploads/candidate_photos/<?php echo htmlspecialchars($candidate['photo_filename']); ?>"
                                    alt="<?php echo htmlspecialchars($candidate['name']); ?>"
                                    class="candidate-img"
                                    onerror="this.src='https://via.placeholder.com/150?text=<?php echo substr($candidate['name'], 0, 1); ?>'">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; border-radius: 50%;">
                                    <?php echo strtoupper(substr($candidate['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="padding-bottom: 20px;">
                            <h3 style="margin: 0; font-size: 1.2rem;"><?php echo htmlspecialchars($candidate['name']); ?></h3>
                            <span class="badge badge-admin" style="margin-top: 5px;"><?php echo htmlspecialchars($candidate['party']); ?></span>

                            <p style="font-size: 0.9rem; color: #666; margin: 15px 0; min-height: 45px; line-height: 1.4;">
                                <?php echo htmlspecialchars($candidate['description']); ?>
                            </p>

                            <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 5px;">
                                    <span style="font-size: 2rem; font-weight: 800; color: var(--primary); line-height: 1;">
                                        <?php echo $candidate['votes']; ?>
                                    </span>
                                    <span style="font-weight: 600; color: var(--dark);">
                                        <?php echo $candidate['percentage']; ?>%
                                    </span>
                                </div>
                                <div style="width: 100%; background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="height: 100%; background: var(--primary); width: <?php echo $candidate['percentage']; ?>%; transition: width 1s ease;"></div>
                                </div>
                                <div style="text-align: left; font-size: 0.75rem; color: #888; margin-top: 5px;">Perolehan Suara</div>
                            </div>

                            <?php if (!$_SESSION['has_voted']): ?>
                                <a href="vote.php" class="btn btn-primary" style="width: 100%; border-radius: 30px;">
                                    Pilih <?php echo explode(' ', $candidate['name'])[0]; ?> <i class="fas fa-arrow-right"></i>
                                </a>
                            <?php else: ?>
                                <button disabled class="btn" style="width: 100%; background: #e5e7eb; color: #9ca3af; cursor: not-allowed; border-radius: 30px;">
                                    <i class="fas fa-lock"></i> Voting Ditutup
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="grid-3" style="margin-top: 30px;">
                <div class="card" style="text-align: center; padding: 20px;">
                    <i class="fas fa-users" style="font-size: 2rem; color: var(--secondary); margin-bottom: 10px;"></i>
                    <div style="font-weight: bold; color: var(--gray);">Total Kandidat</div>
                    <div style="font-size: 1.5rem; font-weight: bold;"><?php echo $candidates->num_rows; ?></div>
                </div>
                <div class="card" style="text-align: center; padding: 20px;">
                    <i class="fas fa-envelope-open-text" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                    <div style="font-weight: bold; color: var(--gray);">Total Suara Sah</div>
                    <div style="font-size: 1.5rem; font-weight: bold;"><?php echo $totalVotes; ?></div>
                </div>
                <div class="card" style="text-align: center; padding: 20px;">
                    <i class="fas fa-trophy" style="font-size: 2rem; color: var(--warning); margin-bottom: 10px;"></i>
                    <div style="font-weight: bold; color: var(--gray);">Posisi Teratas</div>
                    <div style="font-size: 1.1rem; font-weight: bold; color: var(--dark);">
                        <?php
                        $candidates->data_seek(0);
                        $leading = $candidates->fetch_assoc();
                        echo ($leading && $leading['votes'] > 0) ? htmlspecialchars($leading['name']) : "-";
                        ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="card" style="text-align: center; padding: 50px;">
                <i class="fas fa-hourglass-start" style="font-size: 4rem; color: #e5e7eb; margin-bottom: 20px;"></i>
                <h2>Belum Ada Kandidat</h2>
                <p style="color: #666;">Panitia pemilihan belum mendaftarkan kandidat.<br>Silakan kembali lagi nanti.</p>
            </div>
        <?php endif; ?>

    </div>

    <div class="main-footer">
        <div class="copyright">
            &copy; <span id="current-year"></span> E-Voting System. All Rights Reserved.
        </div>
        <div class="credits">
            Created with ❤️ by <a href="https://github.com/fariskhoiri" target="_blank" style="color: var(--primary); text-decoration: none;">Guess Who I am</a>.
        </div>
    </div>
    <script>
        document.getElementById('current-year').textContent = new Date().getFullYear();
    </script>

</body>

</html>
<?php $conn->close(); ?>