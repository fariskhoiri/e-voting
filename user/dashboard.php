<?php
require_once '../includes/functions.php';

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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .nav a {
            margin-right: 15px;
            text-decoration: none;
            color: #667eea;
            font-weight: bold;
        }

        .nav a:hover {
            text-decoration: underline;
        }

        .voted-badge {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .not-voted-badge {
            background: #ffc107;
            color: #212529;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .dashboard-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            color: #333;
            margin-top: 0;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .results-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-votes {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .candidate-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .candidate-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .candidate-name {
            font-size: 1.4rem;
            color: #333;
            margin: 0 0 5px 0;
        }

        .party-name {
            color: #667eea;
            font-weight: bold;
            margin: 0 0 15px 0;
            font-size: 1.1rem;
        }

        .candidate-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .vote-count {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .votes-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-right: 10px;
        }

        .votes-label {
            color: #666;
            font-size: 0.9rem;
        }

        .percentage-container {
            margin: 15px 0;
        }

        .percentage-text {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .percentage-bar-bg {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .percentage-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
            text-align: center;
            color: white;
            font-size: 0.8rem;
            line-height: 20px;
            transition: width 1s ease;
        }

        .leading-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .vote-button {
            background: linear-gradient(90deg, #667eea, #764ba2);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            display: block;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .vote-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .vote-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .no-candidates {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }

        .no-candidates h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .status-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .candidates-grid {
                grid-template-columns: 1fr;
            }

            .results-summary {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0; color: #333;">Presidential Election 2024</h1>
        <div class="nav">
            <span style="color: #666; margin-right: 15px;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
            <?php if ($_SESSION['has_voted']): ?>
                <span class="voted-badge">✓ You Have Voted</span>
            <?php else: ?>
                <span class="not-voted-badge">✗ Not Voted Yet</span>
            <?php endif; ?>
            <a href="vote.php">Cast Your Vote</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="dashboard-container">
        <h2 class="page-title">Election Results Live</h2>

        <?php if ($_SESSION['has_voted']): ?>
            <div class="status-message">
                ✅ Thank you for participating in the election! Your vote has been recorded.
            </div>
        <?php else: ?>
            <div class="status-message" style="background: #fff3cd; color: #856404;">
                ⚠️ You haven't voted yet. <a href="vote.php" style="color: #856404; font-weight: bold;">Click here to vote</a> before the election ends!
            </div>
        <?php endif; ?>

        <div class="results-summary">
            <div>
                <h3 style="margin: 0; color: #333;">Live Election Results</h3>
                <p style="color: #666; margin: 5px 0 0 0;">Real-time updates as votes come in</p>
            </div>
            <?php if ($totalVotes > 0): ?>
                <div class="total-votes">
                    Total Votes Cast: <?php echo $totalVotes; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($candidates->num_rows > 0): ?>
            <div class="candidates-grid">
                <?php
                $isFirst = true;
                while ($candidate = $candidates->fetch_assoc()):
                ?>
                    <div class="candidate-card">
                        <?php if ($isFirst && $candidate['votes'] > 0): ?>
                            <div class="leading-badge">Current Leader</div>
                            <?php $isFirst = false; ?>
                        <?php endif; ?>

                        <h3 class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></h3>
                        <p class="party-name"><?php echo htmlspecialchars($candidate['party']); ?></p>

                        <p class="candidate-description"><?php echo htmlspecialchars($candidate['description']); ?></p>

                        <div class="vote-count">
                            <div class="votes-number"><?php echo $candidate['votes']; ?></div>
                            <div class="votes-label">votes</div>
                        </div>

                        <div class="percentage-container">
                            <div class="percentage-text"><?php echo $candidate['percentage']; ?>% of total votes</div>
                            <div class="percentage-bar-bg">
                                <div class="percentage-bar-fill" style="width: <?php echo $candidate['percentage']; ?>%;">
                                    <?php if ($candidate['percentage'] > 15): ?>
                                        <?php echo $candidate['percentage']; ?>%
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($candidate['percentage'] <= 15): ?>
                                <small style="color: #666;"><?php echo $candidate['percentage']; ?>%</small>
                            <?php endif; ?>
                        </div>

                        <?php if (!$_SESSION['has_voted']): ?>
                            <a href="vote.php">
                                <button class="vote-button">Vote for <?php echo htmlspecialchars($candidate['name']); ?></button>
                            </a>
                        <?php else: ?>
                            <button class="vote-button" disabled>
                                <?php if ($candidate['votes'] > 0): ?>
                                    Viewing Results
                                <?php else: ?>
                                    No Votes Yet
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>

            <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                <h3 style="color: #333; margin-top: 0;">Election Statistics</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="background: white; padding: 15px; border-radius: 5px;">
                        <strong>Total Candidates:</strong> <?php echo $candidates->num_rows; ?>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 5px;">
                        <strong>Total Votes:</strong> <?php echo $totalVotes; ?>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 5px;">
                        <strong>Leading Candidate:</strong>
                        <?php
                        $candidates->data_seek(0);
                        $leading = $candidates->fetch_assoc();
                        if ($leading && $leading['votes'] > 0) {
                            echo htmlspecialchars($leading['name']) . " (" . $leading['percentage'] . "%)";
                        } else {
                            echo "No votes yet";
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="no-candidates">
                <h3>No Candidates Registered</h3>
                <p>Waiting for the administrator to add presidential candidates.</p>
                <p style="color: #999; font-size: 0.9rem;">Check back later when candidates are added.</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
<?php $conn->close(); ?>