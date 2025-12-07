<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../login.php');
}

$conn = getConnection();
$message = '';

// Check if user has already voted
if ($_SESSION['has_voted']) {
    $message = "You have already voted!";
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Your Vote</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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

        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .candidates {
            display: grid;
            gap: 15px;
        }

        .candidate {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .vote-btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .vote-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Cast Your Vote</h1>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($_SESSION['has_voted']): ?>
        <p>You have already cast your vote. Thank you for participating!</p>
        <a href="dashboard.php">View Results</a>
    <?php else: ?>
        <h2>Select Your Candidate</h2>
        <form method="POST" action="">
            <div class="candidates">
                <?php while ($candidate = $candidates->fetch_assoc()): ?>
                    <div class="candidate">
                        <h3><?php echo htmlspecialchars($candidate['name']); ?></h3>
                        <p><strong>Party:</strong> <?php echo htmlspecialchars($candidate['party']); ?></p>
                        <p><?php echo htmlspecialchars($candidate['description']); ?></p>
                        <button type="submit" name="candidate_id" value="<?php echo $candidate['id']; ?>" class="vote-btn">
                            Vote for <?php echo htmlspecialchars($candidate['name']); ?>
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        </form>
    <?php endif; ?>
</body>

</html>
<?php $conn->close(); ?>