<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$conn = getConnection();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $name = $_POST['name'];
        $party = $_POST['party'];
        $description = $_POST['description'];

        // Check if we already have 3 candidates
        $count = countCandidates();
        if ($count >= 3) {
            $message = "Maksimal hanya 3 kandidat!";
        } else {
            $stmt = $conn->prepare("INSERT INTO candidates (name, party, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $party, $description);
            if ($stmt->execute()) {
                $message = "Kandidat telah berhasil ditambahkan!";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete'])) {
        $id = (int) $_POST['id'];

        // Hapus dulu semua votes yang terkait kandidat ini
        $stmt = $conn->prepare("DELETE FROM votes WHERE candidate_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Baru hapus kandidatnya
        $stmt = $conn->prepare("DELETE FROM candidates WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $message = "Kandidat dan data vote terkait berhasil dihapus!";
    }
}

// Get all candidates
$candidates = $conn->query("SELECT * FROM candidates ORDER BY id DESC");

// Get all candidates with percentages
$totalVotesResult = $conn->query("SELECT SUM(votes) as total_votes FROM candidates");
$totalVotesRow = $totalVotesResult->fetch_assoc();
$totalVotes = $totalVotesRow['total_votes'] ?: 0;

$candidates = $conn->query("
    SELECT *, 
           CASE 
               WHEN $totalVotes > 0 THEN ROUND((votes * 100.0) / $totalVotes, 2)
               ELSE 0 
           END as percentage
    FROM candidates 
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kandidat</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .nav a {
            margin-right: 15px;
            text-decoration: none;
            color: #007bff;
        }

        .form-group {
            margin-bottom: 15px;
        }

        input,
        textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }

        button {
            background: #007bff;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .delete-btn button {
            background: #ff101f;
        }

        .message {
            padding: 10px;
            background: #d4edda;
            color: #155724;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .candidate-list {
            margin-top: 30px;
        }

        .candidate {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Kelola Kandidat</h1>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <h2>Tambah Kandidat Baru</h2>
    <form method="POST" action="">
        <div class="form-group">
            <label>Nama Lengkap:</label>
            <input type="text" name="name" required>
        </div>
        <div class="form-group">
            <label>Partai Politik:</label>
            <input type="text" name="party" required>
        </div>
        <div class="form-group">
            <label>Deskripsi:</label>
            <textarea name="description" rows="3" required></textarea>
        </div>
        <button type="submit" name="add">Tambah Kandidat</button>
    </form>

    <div class="candidate-list">
        <h2>Kandidat Sekarang (<?php echo $candidates->num_rows; ?>/3)</h2>
        <?php while ($candidate = $candidates->fetch_assoc()): ?>
            <div class="candidate">
                <h3><?php echo htmlspecialchars($candidate['name']); ?></h3>
                <p><strong>Partai:</strong> <?php echo htmlspecialchars($candidate['party']); ?></p>
                <p><strong>Deskripsi:</strong> <?php echo htmlspecialchars($candidate['description']); ?></p>
                <p>
                    <strong>Suara Diperoleh:</strong> <?php echo $candidate['votes']; ?>
                    (<?php echo $candidate['percentage']; ?>%)
                </p>

                <!-- Add a small progress bar -->
                <div style="width: 100%; background: #e9ecef; height: 10px; border-radius: 5px; margin: 10px 0;">
                    <div style="width: <?php echo $candidate['percentage']; ?>%; background: #007bff; height: 100%; border-radius: 5px;"></div>
                </div>

                <form method="POST" action="" style="margin-top: 10px;">
                    <input type="hidden" name="id" value="<?php echo $candidate['id']; ?>">
                    <div class="delete-btn"><button type="submit" name="delete" onclick="return confirm('Delete this candidate?')">Hapus</button></div>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</body>

</html>
<?php $conn->close(); ?>