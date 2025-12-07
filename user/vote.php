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

        .candidate {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 30px 20px;
        }
        
        .candidate-photo-vote {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .photo-placeholder-vote {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 20px;
            border: 3px solid #dee2e6;
        }
        
        .candidate h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1.4rem;
        }
        
        .candidate p {
            margin: 0 0 15px 0;
            color: #666;
        }
        
        .vote-btn {
            background: linear-gradient(90deg, #28a745, #20c997);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
            width: 80%;
            max-width: 200px;
        }
        
        .vote-btn:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }
        
        .candidates {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .candidate:hover {
            transform: translateY(-5px);
            transition: transform 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .candidate-photo-vote,
            .photo-placeholder-vote {
                width: 120px;
                height: 120px;
            }
            
            .candidates {
                grid-template-columns: 1fr;
                gap: 40px;
            }
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
        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            Click on a candidate's photo to learn more and vote
        </p>
        
        <form method="POST" action="">
            <div class="candidates">
                <?php while ($candidate = $candidates->fetch_assoc()): ?>
                    <div class="candidate">
                        <!-- Candidate Photo -->
                        <?php if ($candidate['photo_filename']): ?>
                            <img src="../uploads/candidate_photos/<?php echo htmlspecialchars($candidate['photo_filename']); ?>" 
                                 alt="<?php echo htmlspecialchars($candidate['name']); ?>"
                                 class="candidate-photo-vote"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"150\" height=\"150\" viewBox=\"0 0 150 150\"><circle cx=\"75\" cy=\"75\" r=\"72\" fill=\"%23667eea\"/><text x=\"75\" y=\"85\" font-family=\"Arial\" font-size=\"40\" fill=\"white\" text-anchor=\"middle\">' + '<?php echo strtoupper(substr($candidate['name'], 0, 1)); ?>' + '</text></svg>'">
                        <?php else: ?>
                            <div class="photo-placeholder-vote">
                                <?php echo strtoupper(substr($candidate['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h3><?php echo htmlspecialchars($candidate['name']); ?></h3>
                        <p><strong>Party:</strong> <?php echo htmlspecialchars($candidate['party']); ?></p>
                        <p><?php echo htmlspecialchars($candidate['description']); ?></p>
                        
                        <button type="submit" name="candidate_id" value="<?php echo $candidate['id']; ?>" class="vote-btn">
                            ✅ Vote for <?php echo htmlspecialchars($candidate['name']); ?>
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        </form>
    <?php endif; ?>
</body>

</html>
<?php $conn->close(); ?>