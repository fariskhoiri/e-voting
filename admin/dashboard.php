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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .nav a {
            margin-right: 15px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }

        .nav a:hover {
            text-decoration: underline;
        }

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h3 {
            color: #333;
            margin-top: 0;
            font-size: 1.2rem;
        }

        .card p {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
            margin: 10px 0;
        }

        .card small {
            color: #666;
            font-size: 0.9rem;
        }

        .results-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .results-table th {
            background: #007bff;
            color: white;
            padding: 15px;
            text-align: left;
        }

        .results-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .results-table tr:hover {
            background: #f9f9f9;
        }

        .percentage-bar-container {
            width: 100%;
            background: #e9ecef;
            border-radius: 20px;
            height: 20px;
            margin-top: 5px;
            overflow: hidden;
        }

        .percentage-bar {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #0056b3);
            border-radius: 20px;
            text-align: center;
            color: white;
            font-size: 0.8rem;
            line-height: 20px;
            transition: width 0.5s ease;
        }

        .percentage-text {
            font-weight: bold;
            color: #333;
        }

        .candidate-name {
            font-weight: bold;
            color: #333;
        }

        .party-name {
            color: #666;
            font-size: 0.9rem;
        }

        .total-votes {
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            display: inline-block;
            margin-top: 10px;
        }

        .no-candidates {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0; color: #333;">Admin Dashboard</h1>
        <div class="nav">
            <span style="color: #666; margin-right: 15px;">Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
            <a href="candidates.php">Kelola Kandidat</a>
            <a href="users.php">Kelola Pengguna</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="dashboard-cards">
        <div class="card">
            <h3>Jumlah Kandidat</h3>
            <p><?php
                $result = $conn->query("SELECT COUNT(*) as count FROM candidates");
                $row = $result->fetch_assoc();
                echo $row['count'];
                ?></p>
            <small>Maks 3 kandidat</small>
        </div>

        <div class="card">
            <h3>Pemilih Suara</h3>
            <p><?php
                $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='user'");
                $row = $result->fetch_assoc();
                echo $row['count'];
                ?></p>
            <small>Pemilih suara terdaftar</small>
        </div>

        <div class="card">
            <h3>Total Suara Diberikan</h3>
            <p><?php
                $result = $conn->query("SELECT COUNT(*) as count FROM votes");
                $row = $result->fetch_assoc();
                echo $row['count'];
                ?></p>
            <small>Suara telah diberikan</small>
        </div>
    </div>

    <div class="results-section">
        <h2 style="margin-top: 0; color: #333;">Hasil Pemungutan Suara</h2>

        <?php if ($totalVotes > 0): ?>
            <div class="total-votes">
                Total Pemungutan: <?php echo $totalVotes; ?>
            </div>
        <?php endif; ?>

        <?php if ($candidates->num_rows > 0): ?>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Kandidat</th>
                        <th>Partai</th>
                        <th>Suara</th>
                        <th>Persentase</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($candidate = $candidates->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></div>
                            </td>
                            <td>
                                <div class="party-name"><?php echo htmlspecialchars($candidate['party']); ?></div>
                            </td>
                            <td>
                                <strong><?php echo $candidate['votes']; ?></strong>
                            </td>
                            <td>
                                <div class="percentage-text"><?php echo $candidate['percentage']; ?>%</div>
                            </td>
                            <td style="width: 200px;">
                                <div class="percentage-bar-container">
                                    <div class="percentage-bar" style="width: <?php echo $candidate['percentage']; ?>%;">
                                        <?php if ($candidate['percentage'] > 10): ?>
                                            <?php echo $candidate['percentage']; ?>%
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($candidate['percentage'] <= 10): ?>
                                    <small><?php echo $candidate['percentage']; ?>%</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="stats-grid">
                <div class="stat-item">
                    <strong>Kandidat Teratas:</strong><br>
                    <?php
                    $candidates->data_seek(0);
                    $leading = $candidates->fetch_assoc();
                    if ($leading) {
                        echo htmlspecialchars($leading['name']) . " (" . $leading['percentage'] . "%)";
                    }
                    ?>
                </div>
                <div class="stat-item">
                    <strong>Partisipasi Pemilih:</strong><br>
                    <?php
                    $totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='user'")->fetch_assoc()['count'];
                    $votedUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE has_voted = TRUE AND role='user'")->fetch_assoc()['count'];
                    $turnout = $totalUsers > 0 ? round(($votedUsers / $totalUsers) * 100, 2) : 0;
                    echo $turnout . "% ($votedUsers/$totalUsers users)";
                    ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-candidates">
                <p>No candidates registered yet. Add candidates to start the election.</p>
                <a href="candidates.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;">Add Candidates</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
<?php $conn->close(); ?>