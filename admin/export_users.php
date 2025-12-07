<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$conn = getConnection();

// Get filter parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$voted_filter = isset($_GET['voted']) ? $_GET['voted'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Build query
$query = "SELECT u.id, u.username, u.role, u.has_voted, 
                 COUNT(v.id) as total_votes,
                 MAX(v.voted_at) as last_voted,
                 u.created_at
          FROM users u
          LEFT JOIN votes v ON u.id = v.user_id";

$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(u.username LIKE ? OR u.id = ?)";
    $params[] = "%$search%";
    $params[] = $search;
    $types .= 'si';
}

if (!empty($role_filter)) {
    $where[] = "u.role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if ($voted_filter === 'yes') {
    $where[] = "u.has_voted = TRUE";
} elseif ($voted_filter === 'no') {
    $where[] = "u.has_voted = FALSE";
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

// Prepare and execute
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
        SUM(CASE WHEN has_voted = TRUE AND role = 'user' THEN 1 ELSE 0 END) as voted_count
    FROM users
")->fetch_assoc();

if ($format === 'csv') {
    // Export as CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users_' . date('Y-m-d_H-i-s') . '.csv');

    $output = fopen('php://output', 'w');

    // BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");

    // Headers
    fputcsv($output, [
        'ID',
        'Username',
        'Role',
        'Voting Status',
        'Total Votes',
        'Last Voted',
        'Created At'
    ]);

    // Data
    while ($user = $result->fetch_assoc()) {
        fputcsv($output, [
            $user['id'],
            $user['username'],
            ucfirst($user['role']),
            $user['has_voted'] ? 'Voted' : 'Not Voted',
            $user['total_votes'],
            $user['last_voted'] ? date('Y-m-d H:i:s', strtotime($user['last_voted'])) : 'Never',
            date('Y-m-d H:i:s', strtotime($user['created_at']))
        ]);
    }

    // Add statistics
    fputcsv($output, []);
    fputcsv($output, ['Statistics', '']);
    fputcsv($output, ['Total Users', $stats['total_users']]);
    fputcsv($output, ['Administrators', $stats['admin_count']]);
    fputcsv($output, ['Regular Users', $stats['user_count']]);
    fputcsv($output, ['Users Voted', $stats['voted_count']]);
    fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);

    fclose($output);
} elseif ($format === 'pdf') {
    // For PDF export, we'll create a simple HTML page that can be printed
    // In a real application, you would use a library like TCPDF or Dompdf

    header('Content-Type: text/html; charset=utf-8');
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Users Export - <?php echo date('Y-m-d'); ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }

            h1 {
                color: #333;
            }

            .report-info {
                margin-bottom: 30px;
                color: #666;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: left;
            }

            th {
                background: #f5f5f5;
            }

            .stats {
                margin-top: 30px;
                padding: 20px;
                background: #f9f9f9;
            }

            @media print {
                button {
                    display: none;
                }

                body {
                    margin: 0;
                }
            }
        </style>
    </head>

    <body>
        <h1>Users Export Report</h1>
        <div class="report-info">
            Generated on: <?php echo date('F j, Y, g:i a'); ?><br>
            Total Users: <?php echo $result->num_rows; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Voting Status</th>
                    <th>Total Votes</th>
                    <th>Last Voted</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo ucfirst($user['role']); ?></td>
                        <td><?php echo $user['has_voted'] ? 'Voted' : 'Not Voted'; ?></td>
                        <td><?php echo $user['total_votes']; ?></td>
                        <td><?php echo $user['last_voted'] ? date('Y-m-d H:i', strtotime($user['last_voted'])) : 'Never'; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="stats">
            <h3>Statistics</h3>
            <p>Total Users: <?php echo $stats['total_users']; ?></p>
            <p>Administrators: <?php echo $stats['admin_count']; ?></p>
            <p>Regular Users: <?php echo $stats['user_count']; ?></p>
            <p>Users Who Have Voted: <?php echo $stats['voted_count']; ?></p>
        </div>

        <button onclick="window.print()" style="margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Print / Save as PDF
        </button>
    </body>

    </html>
<?php
}

$stmt->close();
$conn->close();
exit();
