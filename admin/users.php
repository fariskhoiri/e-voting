<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$conn = getConnection();
$message = '';

// --- LOGIKA PHP TETAP SAMA (TIDAK DIUBAH) ---
// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle add new user
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $role = $_POST['role'];

        if (empty($username) || empty($password)) {
            $message = 'Username dan password wajib diisi!';
            $msg_type = 'error';
        } elseif (strlen($username) < 12) {
            $message = 'Username minimal 12 karakter!';
            $msg_type = 'error';
        } elseif (strlen($password) < 4) {
            $message = 'Password minimal 4 karakter!';
            $msg_type = 'error';
        } elseif ($password !== $confirm_password) {
            $message = 'Password tidak cocok!';
            $msg_type = 'error';
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->bind_param("s", $username);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $message = 'Username sudah digunakan!';
                $msg_type = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $password, $role);
                if ($stmt->execute()) {
                    // Log creation
                    $new_id = $stmt->insert_id;
                    $admin_id = $_SESSION['user_id'];
                    $conn->query("INSERT INTO user_creation_logs (admin_id, user_id, created_at) VALUES ($admin_id, $new_id, NOW())");

                    $message = 'Pengguna "' . htmlspecialchars($username) . '" berhasil didaftarkan!';
                    $msg_type = 'success';
                } else {
                    $message = 'Error: ' . $conn->error;
                    $msg_type = 'error';
                }
                $stmt->close();
            }
            $checkStmt->close();
        }
    }
    // Handle reset password
    elseif (isset($_POST['reset_password'])) {
        $user_id = intval($_POST['user_id']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (empty($new_password) || $new_password !== $confirm_password || strlen($new_password) < 4) {
            $message = 'Password tidak valid atau tidak cocok!';
            $msg_type = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_password, $user_id);
            if ($stmt->execute()) {
                $admin_id = $_SESSION['user_id'];
                $conn->query("INSERT INTO password_reset_logs (admin_id, user_id, reset_at) VALUES ($admin_id, $user_id, NOW())");
                $message = 'Password berhasil direset!';
                $msg_type = 'success';
            } else {
                $message = 'Gagal mereset password.';
                $msg_type = 'error';
            }
            $stmt->close();
        }
    }
    // Handle delete user
    elseif (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        $check = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_assoc();

        if ($check && $check['role'] === 'admin') {
            $message = 'Tidak dapat menghapus akun Administrator!';
            $msg_type = 'error';
        } else {
            $conn->query("DELETE FROM votes WHERE user_id = $user_id");
            if ($conn->query("DELETE FROM users WHERE id = $user_id")) {
                $message = 'Pengguna berhasil dihapus permanen!';
                $msg_type = 'success';
            } else {
                $message = 'Gagal menghapus pengguna.';
                $msg_type = 'error';
            }
        }
    }
    // Handle toggle vote status
    elseif (isset($_POST['toggle_vote_status'])) {
        $user_id = intval($_POST['user_id']);
        if ($conn->query("UPDATE users SET has_voted = NOT has_voted WHERE id = $user_id")) {
            $message = 'Status voting berhasil diperbarui!';
            $msg_type = 'success';
        }
    }
}

// Get Data & Filters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$voted_filter = isset($_GET['voted']) ? $_GET['voted'] : '';

$query = "SELECT u.*, COUNT(v.id) as total_votes, MAX(v.voted_at) as last_voted 
          FROM users u LEFT JOIN votes v ON u.id = v.user_id";
$where = [];
if (!empty($search)) $where[] = "(u.username LIKE '%$search%' OR u.id = '$search')";
if (!empty($role_filter)) $where[] = "u.role = '$role_filter'";
if ($voted_filter === 'yes') $where[] = "u.has_voted = TRUE";
elseif ($voted_filter === 'no') $where[] = "u.has_voted = FALSE";

if (!empty($where)) $query .= " WHERE " . implode(" AND ", $where);
$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$users = $conn->query($query);

// Statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
        SUM(CASE WHEN has_voted = TRUE AND role = 'user' THEN 1 ELSE 0 END) as voted_count,
        SUM(CASE WHEN has_voted = FALSE AND role = 'user' THEN 1 ELSE 0 END) as not_voted_count
    FROM users
")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna</title>

    <?php require_once '../includes/head_styles.php'; ?>

    <style>
        /* Custom Styles untuk Halaman Ini */
        .stats-grid-5 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: 0.2s;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .btn-icon-blue {
            background: #3b82f6;
        }

        .btn-icon-yellow {
            background: #f59e0b;
        }

        .btn-icon-red {
            background: #ef4444;
        }
    </style>

    <script>
        function openModal(modalId, userId, username) {
            const modal = document.getElementById(modalId);
            // Set values based on modal type
            if (modalId === 'resetPasswordModal') {
                document.getElementById('reset_user_id').value = userId;
                document.getElementById('reset_username').textContent = username;
            } else if (modalId === 'deleteUserModal') {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('delete_username').textContent = username;
            } else if (modalId === 'toggleVoteModal') {
                document.getElementById('toggle_user_id').value = userId;
                document.getElementById('toggle_username').textContent = username;
            }
            modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function toggleAddForm() {
            const form = document.getElementById('addUserForm');
            const btn = document.getElementById('toggleBtn');
            if (form.style.display === 'none') {
                form.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-minus"></i> Sembunyikan';
            } else {
                form.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-plus"></i> Tambah User Baru';
            }
        }

        // Close modal clicking outside
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) e.target.style.display = 'none';
        }
    </script>
</head>

<body>

    <nav class="navbar">
        <a href="dashboard.php" class="brand">
            <i class="fas fa-vote-yea"></i> Admin Panel
        </a>
        <div class="nav-links">
            <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="candidates.php"><i class="fas fa-users"></i> Kandidat</a>
            <a href="users.php" class="active"><i class="fas fa-user-cog"></i> Pengguna</a>
            <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h1>Manajemen Pengguna</h1>
                    <p class="text-gray">Kelola akun, reset password, dan pantau status pemilihan.</p>
                </div>
                <!--<div style="display: flex; gap: 10px;">
                    <a href="export_users.php?format=pdf" target="_blank" class="btn" style="background: #ef4444; color: white; font-size: 0.9rem;">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="export_users.php?format=csv" target="_blank" class="btn" style="background: #10b981; color: white; font-size: 0.9rem;">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                </div>-->
            </div>
        </div>

        <?php if ($message): ?>
            <div style="padding: 15px; margin-bottom: 20px; border-radius: 8px; 
                 background: <?php echo $msg_type == 'success' ? '#d1fae5' : '#fee2e2'; ?>; 
                 color: <?php echo $msg_type == 'success' ? '#065f46' : '#991b1b'; ?>; border: 1px solid currentColor;">
                <i class="fas <?php echo $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid-5">
            <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid var(--gray);">
                <small>Total Pengguna</small>
                <div class="card-number" style="font-size: 1.8rem; margin: 5px 0;"><?php echo $stats['total_users']; ?></div>
            </div>
            <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid var(--danger);">
                <small>Admin</small>
                <div class="card-number" style="font-size: 1.8rem; margin: 5px 0;"><?php echo $stats['admin_count']; ?></div>
            </div>
            <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid var(--primary);">
                <small>User Biasa</small>
                <div class="card-number" style="font-size: 1.8rem; margin: 5px 0;"><?php echo $stats['user_count']; ?></div>
            </div>
            <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid var(--secondary);">
                <small>Sudah Memilih</small>
                <div class="card-number" style="font-size: 1.8rem; margin: 5px 0; color: var(--secondary);"><?php echo $stats['voted_count']; ?></div>
            </div>
            <div class="card" style="padding: 20px; text-align: center; border-top: 4px solid var(--warning);">
                <small>Belum Memilih</small>
                <div class="card-number" style="font-size: 1.8rem; margin: 5px 0; color: var(--warning);"><?php echo $stats['not_voted_count']; ?></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0;">Tambah Pengguna</h3>
                    <button id="toggleBtn" onclick="toggleAddForm()" class="btn" style="background: var(--light); color: var(--dark); font-size: 0.9rem;">
                        <i class="fas fa-plus"></i> Tambah User Baru
                    </button>
                </div>

                <form id="addUserForm" method="POST" style="display: none; border-top: 1px solid #eee; padding-top: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <label style="font-size: 0.9rem; font-weight: 600;">Username</label>
                            <input type="text" name="username" required placeholder="Username unik">
                        </div>
                        <div>
                            <label style="font-size: 0.9rem; font-weight: 600;">Password</label>
                            <input type="password" name="password" required placeholder="Minimal 4 karakter">
                        </div>
                        <div>
                            <label style="font-size: 0.9rem; font-weight: 600;">Konfirmasi</label>
                            <input type="password" name="confirm_password" required placeholder="Ulangi password">
                        </div>
                        <div>
                            <label style="font-size: 0.9rem; font-weight: 600;">Role</label>
                            <select name="role">
                                <option value="user">User Biasa</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-save"></i> Simpan Pengguna
                    </button>
                </form>
            </div>

            <div class="card" style="background: #f8fafc;">
                <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; align-items: end;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: bold;">Cari:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username/ID...">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: bold;">Role:</label>
                        <select name="role">
                            <option value="">Semua</option>
                            <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>User</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: bold;">Status Vote:</label>
                        <select name="voted">
                            <option value="">Semua</option>
                            <option value="yes" <?php echo $voted_filter == 'yes' ? 'selected' : ''; ?>>Sudah</option>
                            <option value="no" <?php echo $voted_filter == 'no' ? 'selected' : ''; ?>>Belum</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                </form>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status Suara</th>
                        <th>Waktu Memilih</th>
                        <th>Terdaftar</th>
                        <th width="120">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->num_rows > 0): ?>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span style="font-size: 0.75rem; color: var(--primary); background: #e0e7ff; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">You</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['role'] == 'admin' ? 'badge-danger' : 'badge-admin'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['has_voted']): ?>
                                        <span class="badge badge-success">Sudah</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #666; font-size: 0.9rem;">
                                    <?php echo $user['last_voted'] ? date('d M, H:i', strtotime($user['last_voted'])) : '-'; ?>
                                </td>
                                <td style="color: #666; font-size: 0.9rem;">
                                    <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button class="action-btn btn-icon-blue" title="Reset Password"
                                            onclick="openModal('resetPasswordModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-key"></i>
                                        </button>

                                        <button class="action-btn btn-icon-yellow" title="Ubah Status Vote"
                                            onclick="openModal('toggleVoteModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas <?php echo $user['has_voted'] ? 'fa-times' : 'fa-check'; ?>"></i>
                                        </button>

                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="action-btn btn-icon-red" title="Hapus User"
                                                onclick="openModal('deleteUserModal', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--gray);">
                                <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>Tidak ada pengguna ditemukan.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top: 0;">Reset Password</h3>
            <p style="color: #666; margin-bottom: 20px;">Ubah password untuk user: <strong id="reset_username"></strong></p>

            <form method="POST">
                <input type="hidden" id="reset_user_id" name="user_id">
                <div style="margin-bottom: 15px;">
                    <label>Password Baru</label>
                    <input type="password" name="new_password" required>
                </div>
                <div style="margin-bottom: 20px;">
                    <label>Konfirmasi Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #e5e7eb; color: #333;" onclick="closeModal('resetPasswordModal')">Batal</button>
                    <button type="submit" name="reset_password" class="btn btn-primary">Simpan Password</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toggleVoteModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top: 0;">Ubah Status Pemilihan</h3>
            <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
                <i class="fas fa-exclamation-triangle"></i> <strong>Peringatan:</strong><br>
                Mengubah status ini akan memanipulasi apakah sistem menganggap user <strong id="toggle_username"></strong> sudah memilih atau belum.
            </div>
            <form method="POST">
                <input type="hidden" id="toggle_user_id" name="user_id">
                <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #e5e7eb; color: #333;" onclick="closeModal('toggleVoteModal')">Batal</button>
                    <button type="submit" name="toggle_vote_status" class="btn" style="background: #f59e0b; color: white;">Ya, Ubah Status</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top: 0; color: #ef4444;">Hapus Pengguna</h3>
            <p>Anda yakin ingin menghapus user <strong id="delete_username"></strong>?</p>
            <p style="font-size: 0.9rem; color: #666;">Tindakan ini permanen dan akan menghapus data suara (vote) terkait user ini.</p>

            <form method="POST">
                <input type="hidden" id="delete_user_id" name="user_id">
                <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" style="background: #e5e7eb; color: #333;" onclick="closeModal('deleteUserModal')">Batal</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Hapus Permanen</button>
                </div>
            </form>
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