<?php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Configuration
$upload_dir = '../uploads/candidate_photos/';
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$max_file_size = 2 * 1024 * 1024; // 2MB

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$conn = getConnection();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $name = $_POST['name'];
        $party = $_POST['party'];
        $description = $_POST['description'];
        $photo_file = $_FILES['photo'] ?? null;

        // Check if we already have 3 candidates
        $count = countCandidates();
        if ($count >= 3) {
            $message = '<div class="error">Maksimal 3 kandidat!</div>';
        } else {
            // Handle file upload
            $photo_filename = null;
            $photo_mime_type = null;
            $photo_size = 0;

            if ($photo_file && $photo_file['error'] === UPLOAD_ERR_OK) {
                // Validate file
                $file_type = mime_content_type($photo_file['tmp_name']);
                $file_size = $photo_file['size'];

                if (!in_array($file_type, $allowed_types)) {
                    $message = '<div class="error">Invalid file type. Allowed: JPG, PNG, GIF, WebP</div>';
                } elseif ($file_size > $max_file_size) {
                    $message = '<div class="error">File too large. Maximum size: 2MB</div>';
                } else {
                    // Generate unique filename
                    $extension = pathinfo($photo_file['name'], PATHINFO_EXTENSION);
                    $photo_filename = 'candidate_' . time() . '_' . uniqid() . '.' . $extension;
                    $upload_path = $upload_dir . $photo_filename;

                    if (move_uploaded_file($photo_file['tmp_name'], $upload_path)) {
                        $photo_mime_type = $file_type;
                        $photo_size = $file_size;
                    } else {
                        $message = '<div class="error">Failed to upload photo.</div>';
                    }
                }
            }

            if (empty($message)) {
                $stmt = $conn->prepare("INSERT INTO candidates (name, party, description, photo_filename, photo_mime_type, photo_size, photo_uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssssi", $name, $party, $description, $photo_filename, $photo_mime_type, $photo_size);
                if ($stmt->execute()) {
                    $message = '<div class="success">Kandidat berhasil ditambahkan!</div>';
                } else {
                    $message = '<div class="error">Error adding candidate: ' . $conn->error . '</div>';
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];

        // Get candidate to delete photo file
        $stmt = $conn->prepare("SELECT photo_filename FROM candidates WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $candidate = $result->fetch_assoc();
        $stmt->close();

        // Delete photo file if exists
        if ($candidate['photo_filename'] && file_exists($upload_dir . $candidate['photo_filename'])) {
            unlink($upload_dir . $candidate['photo_filename']);
        }

        // Delete candidate from database
        $stmt = $conn->prepare("DELETE FROM candidates WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $message = '<div class="success">Kandidat dihapus!</div>';
    } elseif (isset($_POST['update_photo'])) {
        $id = $_POST['id'];
        $photo_file = $_FILES['photo'] ?? null;

        if ($photo_file && $photo_file['error'] === UPLOAD_ERR_OK) {
            // Validate file
            $file_type = mime_content_type($photo_file['tmp_name']);
            $file_size = $photo_file['size'];

            if (!in_array($file_type, $allowed_types)) {
                $message = '<div class="error">Invalid file type. Allowed: JPG, PNG, GIF, WebP</div>';
            } elseif ($file_size > $max_file_size) {
                $message = '<div class="error">File too large. Maximum size: 2MB</div>';
            } else {
                // Get old photo filename to delete
                $stmt = $conn->prepare("SELECT photo_filename FROM candidates WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $candidate = $result->fetch_assoc();
                $stmt->close();

                // Delete old photo file
                if ($candidate['photo_filename'] && file_exists($upload_dir . $candidate['photo_filename'])) {
                    unlink($upload_dir . $candidate['photo_filename']);
                }

                // Generate new filename
                $extension = pathinfo($photo_file['name'], PATHINFO_EXTENSION);
                $photo_filename = 'candidate_' . time() . '_' . uniqid() . '.' . $extension;
                $upload_path = $upload_dir . $photo_filename;

                if (move_uploaded_file($photo_file['tmp_name'], $upload_path)) {
                    $stmt = $conn->prepare("UPDATE candidates SET photo_filename = ?, photo_mime_type = ?, photo_size = ?, photo_uploaded_at = NOW() WHERE id = ?");
                    $stmt->bind_param("sssi", $photo_filename, $file_type, $file_size, $id);

                    if ($stmt->execute()) {
                        $message = '<div class="success">Photo updated successfully!</div>';
                    } else {
                        $message = '<div class="error">Error updating photo: ' . $conn->error . '</div>';
                    }
                    $stmt->close();
                } else {
                    $message = '<div class="error">Failed to upload photo.</div>';
                }
            }
        } else {
            $message = '<div class="error">Please select a photo to upload.</div>';
        }
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
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kandidat</title>

    <?php require_once '../includes/head_styles.php'; ?>

    <script>
        // Script Preview Foto (Tidak berubah, logika tetap sama)
        function previewPhoto(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.id = previewId;
                        img.className = 'photo-preview';
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';
                        img.src = e.target.result;
                        preview.parentNode.replaceChild(img, preview);
                    }
                }
                reader.readAsDataURL(file);
                const fileInfo = document.getElementById('file-info');
                if (fileInfo) {
                    fileInfo.innerHTML = `<small>File: ${file.name} (${(file.size / 1024).toFixed(2)} KB)</small>`;
                }
            }
        }

        function validatePhotoUpload() {
            const fileInput = document.getElementById('photo');
            if (!fileInput || !fileInput.files[0]) {
                alert('Mohon pilih foto untuk diupload.');
                return false;
            }
            return true;
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
            <a href="candidates.php" class="active"><i class="fas fa-users"></i> Kandidat</a>
            <a href="users.php"><i class="fas fa-user-cog"></i> Pengguna</a>
            <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">

        <div class="page-header">
            <h2><i class="fas fa-user-tie"></i> Kelola Kandidat</h2>
            <p class="text-gray">Tambahkan atau hapus kandidat presiden (Maksimal 3).</p>
        </div>

        <?php if ($message): ?>
            <div style="padding: 15px; margin-bottom: 20px; border-radius: 8px; 
                 background: <?php echo strpos($message, 'success') ? '#d1fae5' : '#fee2e2'; ?>; 
                 color: <?php echo strpos($message, 'success') ? '#065f46' : '#991b1b'; ?>;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">

            <div class="card">
                <h3 style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Tambah Kandidat Baru</h3>

                <div class="upload-instructions" style="background: #eef2ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
                    <strong style="color: var(--primary);"><i class="fas fa-info-circle"></i> Info Upload:</strong>
                    <ul style="margin-left: 20px; margin-top: 5px; color: #555;">
                        <li>Format: JPG, PNG, WEBP (Max 2MB).</li>
                        <li>Gunakan foto rasio 1:1 (Persegi) agar rapi.</li>
                    </ul>
                </div>

                <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validatePhotoUpload()">
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div style="flex: 0 0 150px; text-align: center;">
                            <div id="photo-preview" style="width: 150px; height: 150px; background: #f3f4f6; border-radius: 50%; border: 3px dashed #d1d5db; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px;">
                                <i class="fas fa-camera" style="font-size: 2rem; color: #9ca3af;"></i>
                            </div>
                            <label class="btn" style="background: var(--light); color: var(--dark); font-size: 0.8rem; display: inline-block; width: 100%;">
                                Pilih Foto
                                <input type="file" id="photo" name="photo" style="display: none;"
                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                    onchange="previewPhoto(this, 'photo-preview')">
                            </label>
                            <div id="file-info" style="margin-top: 5px;"></div>
                        </div>

                        <div style="flex: 1; min-width: 250px;">
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label style="font-weight: bold;">Nama Lengkap</label>
                                <input type="text" name="name" required placeholder="Contoh: Budi Santoso">
                            </div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label style="font-weight: bold;">Partai Pengusung</label>
                                <input type="text" name="party" required placeholder="Contoh: Partai Mahasiswa">
                            </div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label style="font-weight: bold;">Visi & Misi Singkat</label>
                                <textarea name="description" rows="4" required placeholder="Jelaskan visi misi singkat..." style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;"></textarea>
                            </div>

                            <button type="submit" name="add" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-plus-circle"></i> Simpan Kandidat
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div>
                <h3 style="margin-bottom: 15px;">Daftar Kandidat Terdaftar (<?php echo $candidates->num_rows; ?>/3)</h3>

                <?php if ($candidates->num_rows > 0): ?>
                    <div class="grid-3">
                        <?php
                        $candidates->data_seek(0); // Reset pointer
                        while ($candidate = $candidates->fetch_assoc()):
                        ?>
                            <div class="card" style="text-align: center; position: relative;">
                                <div style="margin: -10px auto 15px auto;">
                                    <?php if ($candidate['photo_filename']): ?>
                                        <img src="../uploads/candidate_photos/<?php echo htmlspecialchars($candidate['photo_filename']); ?>"
                                            class="candidate-photo-large"
                                            style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); box-shadow: var(--shadow-sm);"
                                            onerror="this.src='https://via.placeholder.com/100?text=Error'">
                                    <?php else: ?>
                                        <div style="width: 100px; height: 100px; background: #e0e7ff; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 2rem; font-weight: bold;">
                                            <?php echo strtoupper(substr($candidate['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($candidate['name']); ?></h4>
                                <span class="badge badge-admin" style="margin-bottom: 15px;"><?php echo htmlspecialchars($candidate['party']); ?></span>

                                <p style="font-size: 0.9rem; color: #666; margin-bottom: 15px; min-height: 50px; text-align: left;">
                                    <?php echo substr(htmlspecialchars($candidate['description']), 0, 100) . '...'; ?>
                                </p>

                                <div style="border-top: 1px solid #eee; padding-top: 15px;">
                                    <form method="POST" action="" onsubmit="return confirm('Yakin ingin menghapus kandidat ini? Aksi ini akan menghapus suara yang masuk untuk kandidat ini!');">
                                        <input type="hidden" name="id" value="<?php echo $candidate['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger" style="width: 100%; font-size: 0.8rem; padding: 8px;">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card" style="text-align: center; padding: 40px; color: #888;">
                        <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 10px; color: #d1d5db;"></i>
                        <p>Belum ada kandidat yang didaftarkan.</p>
                    </div>
                <?php endif; ?>
            </div>

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