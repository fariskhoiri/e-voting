<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
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
    <title>Admin Dashboard</title>

    <?php require_once '../includes/head_styles.php'; ?>
</head>

<body>

    <nav class="navbar">
        <a href="dashboard.php" class="brand">
            <i class="fas fa-vote-yea"></i> Admin Panel
        </a>
        <div class="nav-links">
            <a href="dashboard.php" class="active"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="candidates.php"><i class="fas fa-users"></i> Kandidat</a>
            <a href="users.php"><i class="fas fa-user-cog"></i> Pengguna</a>
            <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">

        <div class="page-header">
            <h1>Dashboard Overview</h1>
            <p class="text-gray">Selamat datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</p>
        </div>

        <div class="grid-3">
            <div class="card">
                <div class="card-icon"><i class="fas fa-user-tie"></i></div>
                <div class="card-title">Jumlah Kandidat</div>
                <div class="card-number">
                    <?php
                    $result = $conn->query("SELECT COUNT(*) as count FROM candidates");
                    $row = $result->fetch_assoc();
                    echo $row['count'];
                    ?>
                </div>
                <small class="text-gray">Maksimal 3 kandidat</small>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-users"></i></div>
                <div class="card-title">Pemilih Terdaftar</div>
                <div class="card-number">
                    <?php
                    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='user'");
                    $row = $result->fetch_assoc();
                    echo $row['count'];
                    ?>
                </div>
                <small class="text-gray">User aktif</small>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-box-open"></i></div>
                <div class="card-title">Total Suara Masuk</div>
                <div class="card-number">
                    <?php
                    $result = $conn->query("SELECT COUNT(*) as count FROM votes");
                    $row = $result->fetch_assoc();
                    echo $row['count'];
                    ?>
                </div>
                <small style="color: var(--primary);">Data Real-time</small>
            </div>
        </div>

        <div class="card" style="margin-top: 30px; padding: 0; border: none; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;"><i class="fas fa-poll"></i> Hasil Pemungutan Suara</h3>
                <?php if ($totalVotes > 0): ?>
                    <span class="badge badge-success">Total: <?php echo $totalVotes; ?> Suara</span>
                <?php endif; ?>
            </div>

            <?php if ($candidates->num_rows > 0): ?>
                <div class="table-container" style="margin-top: 0; box-shadow: none; border-radius: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Kandidat</th>
                                <th width="40%">Progress Suara</th>
                                <th>Total</th>
                                <th>Persentase</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Mengambil statistik partisipasi untuk ditampilkan di bawah
                            $totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='user'")->fetch_assoc()['count'];
                            $votedUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE has_voted = TRUE AND role='user'")->fetch_assoc()['count'];

                            while ($candidate = $candidates->fetch_assoc()):
                            ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 15px;">
                                            <?php if ($candidate['photo_filename']): ?>
                                                <img src="../uploads/candidate_photos/<?php echo htmlspecialchars($candidate['photo_filename']); ?>"
                                                    style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb;">
                                            <?php else: ?>
                                                <div style="width: 40px; height: 40px; background: #e0e7ff; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                                    <?php echo strtoupper(substr($candidate['name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div>
                                                <div style="font-weight: bold; color: var(--dark);"><?php echo htmlspecialchars($candidate['name']); ?></div>
                                                <div style="font-size: 0.8rem; color: var(--gray);"><?php echo htmlspecialchars($candidate['party']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="width: 100%; background: #eef2ff; height: 10px; border-radius: 10px; overflow: hidden;">
                                            <div style="height: 100%; background: var(--primary); border-radius: 10px; width: <?php echo $candidate['percentage']; ?>%;"></div>
                                        </div>
                                    </td>
                                    <td style="font-weight: bold; font-size: 1.1rem;"><?php echo $candidate['votes']; ?></td>
                                    <td>
                                        <span class="badge badge-admin"><?php echo $candidate['percentage']; ?>%</span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding: 20px; background: #f9fafb; border-top: 1px solid #eee;">
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div style="flex: 1; background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <strong style="display: block; color: var(--gray); font-size: 0.8rem; text-transform: uppercase;">Kandidat Unggul</strong>
                            <?php
                            $candidates->data_seek(0);
                            $leading = $candidates->fetch_assoc();
                            if ($leading && $leading['votes'] > 0) {
                                echo "<span style='color: var(--primary); font-weight: bold; font-size: 1.1rem;'>" . htmlspecialchars($leading['name']) . "</span> (" . $leading['percentage'] . "%)";
                            } else {
                                echo "-";
                            }
                            ?>
                        </div>
                        <div style="flex: 1; background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <strong style="display: block; color: var(--gray); font-size: 0.8rem; text-transform: uppercase;">Partisipasi Pemilih</strong>
                            <?php
                            $turnout = $totalUsers > 0 ? round(($votedUsers / $totalUsers) * 100, 2) : 0;
                            echo "<span style='font-weight: bold; font-size: 1.1rem;'>" . $turnout . "%</span> <span style='color: #666; font-size: 0.9rem;'>($votedUsers dari $totalUsers user)</span>";
                            ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div style="text-align: center; padding: 50px;">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: #d1d5db; margin-bottom: 15px;"></i>
                    <p style="color: #666;">Belum ada kandidat yang terdaftar.</p>
                    <a href="candidates.php" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-plus"></i> Tambah Kandidat
                    </a>
                </div>
            <?php endif; ?>
        </div>

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