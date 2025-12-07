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
            $message = '<div class="error">Maximum of 3 candidates allowed!</div>';
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
                    $message = '<div class="success">Candidate added successfully!</div>';
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
        $message = '<div class="success">Candidate deleted!</div>';
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

        .photo-upload-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px dashed #dee2e6;
        }
        
        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
            margin-bottom: 15px;
        }
        
        .photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            border: 2px dashed #adb5bd;
            margin-bottom: 15px;
        }
        
        .file-input-container {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input-label:hover {
            background: #0056b3;
        }
        
        .file-info {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .candidate-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        
        .candidate-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .candidate-photo-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
        }
        
        .photo-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-photo {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-photo-upload {
            background: #17a2b8;
            color: white;
        }
        
        .btn-photo-remove {
            background: #dc3545;
            color: white;
        }
        
        .upload-instructions {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .photo-status {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .photo-uploaded {
            background: #d4edda;
            color: #155724;
        }
        
        .photo-none {
            background: #f8d7da;
            color: #721c24;
        }
        
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .gallery-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .gallery-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .candidate-header {
                flex-direction: column;
                text-align: center;
            }
            
            .photo-actions {
                flex-direction: column;
            }
        }
    </style>
    <script>
        function previewPhoto(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        // Replace placeholder with image
                        const img = document.createElement('img');
                        img.id = previewId;
                        img.className = 'photo-preview';
                        img.src = e.target.result;
                        preview.parentNode.replaceChild(img, preview);
                    }
                }
                reader.readAsDataURL(file);
                
                // Show file info
                const fileInfo = document.getElementById('file-info');
                if (fileInfo) {
                    fileInfo.innerHTML = `
                        <strong>Selected:</strong> ${file.name}<br>
                        <strong>Size:</strong> ${(file.size / 1024).toFixed(2)} KB<br>
                        <strong>Type:</strong> ${file.type}
                    `;
                }
            }
        }
        
        function removePhoto(candidateId) {
            if (confirm('Remove photo for this candidate?')) {
                document.getElementById('remove_candidate_id').value = candidateId;
                document.getElementById('removePhotoForm').submit();
            }
        }
        
        function openPhotoModal(candidateId, candidateName) {
            document.getElementById('photo_candidate_id').value = candidateId;
            document.getElementById('photo_candidate_name').textContent = candidateName;
            document.getElementById('updatePhotoModal').style.display = 'flex';
        }
        
        function validatePhotoUpload() {
            const fileInput = document.getElementById('photo');
            if (!fileInput || !fileInput.files[0]) {
                alert('Please select a photo to upload.');
                return false;
            }
            
            const file = fileInput.files[0];
            const maxSize = 2 * 1024 * 1024; // 2MB
            
            if (file.size > maxSize) {
                alert('File size exceeds 2MB limit.');
                return false;
            }
            
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload an image (JPG, PNG, GIF, WebP).');
                return false;
            }
            
            return true;
        }
    </script>
</head>

<body>
<div class="header">
        <h1 style="margin: 0; color: #333;">Manage Candidates</h1>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="candidates.php" style="color: #dc3545;">Candidates</a>
            <a href="users.php">Users</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="upload-instructions">
        <h3 style="margin-top: 0; color: #0056b3;">📸 Photo Upload Guidelines:</h3>
        <ul style="margin-bottom: 0;">
            <li>Supported formats: JPG, PNG, GIF, WebP</li>
            <li>Maximum file size: 2MB</li>
            <li>Recommended: Square photo (1:1 ratio) for best display</li>
            <li>Photo will be cropped to circle on display</li>
        </ul>
    </div>
    
    <h2>Add New Candidate</h2>
    <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validatePhotoUpload()">
        <div class="photo-upload-section">
            <h3 style="margin-top: 0;">Candidate Photo</h3>
            
            <div id="photo-preview" class="photo-placeholder">
                Click to select photo
            </div>
            
            <div class="file-input-container">
                <label class="file-input-label">
                    📁 Choose Photo
                    <input type="file" id="photo" name="photo" class="file-input" 
                           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                           onchange="previewPhoto(this, 'photo-preview')">
                </label>
            </div>
            
            <div id="file-info" class="file-info">
                No file selected
            </div>
        </div>
        
        <div class="form-group">
            <label>Full Name:</label>
            <input type="text" name="name" required>
        </div>
        <div class="form-group">
            <label>Political Party:</label>
            <input type="text" name="party" required>
        </div>
        <div class="form-group">
            <label>Description:</label>
            <textarea name="description" rows="3" required></textarea>
        </div>
        <button type="submit" name="add">Add Candidate</button>
    </form>
    
    <div class="candidate-list">
        <h2>Current Candidates (<?php echo $candidates->num_rows; ?>/3)</h2>
        
        <?php while ($candidate = $candidates->fetch_assoc()): ?>
            <div class="candidate">
                <div class="candidate-header">
                    <?php if ($candidate['photo_filename']): ?>
                        <img src="../uploads/candidate_photos/<?php echo htmlspecialchars($candidate['photo_filename']); ?>" 
                             alt="<?php echo htmlspecialchars($candidate['name']); ?>"
                             class="candidate-photo-large"
                             onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100\" height=\"100\" viewBox=\"0 0 100 100\"><rect width=\"100\" height=\"100\" fill=\"%23e9ecef\"/><text x=\"50\" y=\"50\" font-family=\"Arial\" font-size=\"14\" fill=\"%236c757d\" text-anchor=\"middle\" dy=\".3em\"></text></svg>
                    <?php else: ?>
                        <div class="photo-placeholder" style="width: 120px; height: 120px;">
                            No Photo
                        </div>
                    <?php endif; ?>
                    
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($candidate['name']); ?></h3>
                        <p style="margin: 0 0 10px 0; color: #666;">
                            <strong>Party:</strong> <?php echo htmlspecialchars($candidate['party']); ?>
                        </p>
                        <p style="margin: 0;">
                            <span class="photo-status <?php echo $candidate['photo_filename'] ? 'photo-uploaded' : 'photo-none'; ?>">
                                <?php echo $candidate['photo_filename'] ? 'Photo Uploaded' : 'No Photo'; ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <p><strong>Description:</strong> <?php echo htmlspecialchars($candidate['description']); ?></p>
                <p><strong>Votes:</strong> <?php echo $candidate['votes']; ?></p>
                
                <?php if ($candidate['photo_filename'] && $candidate['photo_uploaded_at']): ?>
                    <p style="font-size: 0.9rem; color: #666;">
                        <strong>Photo uploaded:</strong> 
                        <?php echo date('M d, Y H:i', strtotime($candidate['photo_uploaded_at'])); ?>
                        (<?php echo round($candidate['photo_size'] / 1024, 2); ?> KB)
                    </p>
                <?php endif; ?>
                
                <div class="photo-actions">
                    <!--<button type="button" class="btn-photo btn-photo-upload" 
                            onclick="openPhotoModal(<?php echo $candidate['id']; ?>, '<?php echo htmlspecialchars($candidate['name']); ?>')">
                        📤 Update Photo
                    </button>-->
                    
                    <!--<?php if ($candidate['photo_filename']): ?>
                        <button type="button" class="btn-photo btn-photo-remove" 
                                onclick="removePhoto(<?php echo $candidate['id']; ?>)">
                            🗑️ Remove Photo
                        </button>
                    <?php endif; ?>-->
                </div>
                
                <form method="POST" action="" style="margin-top: 10px;">
                    <input type="hidden" name="id" value="<?php echo $candidate['id']; ?>">
                    <button type="submit" name="delete" onclick="return confirm('Delete this candidate?')">Delete Candidate</button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
    
    <!-- Update Photo Modal -->
    <!--<div id="updatePhotoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Update Candidate Photo</div>
                <button class="modal-close" onclick="closeModal('updatePhotoModal')">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validatePhotoUpload()">
                <input type="hidden" id="photo_candidate_id" name="id">
                
                <div class="form-group">
                    <label>Candidate:</label>
                    <div id="photo_candidate_name" style="padding: 10px; background: #f8f9fa; border-radius: 4px;"></div>
                </div>
                
                <div class="photo-upload-section" style="margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">Select New Photo</h4>
                    
                    <div id="update-photo-preview" class="photo-placeholder">
                        Click to select photo
                    </div>
                    
                    <div class="file-input-container">
                        <label class="file-input-label">
                            📁 Choose Photo
                            <input type="file" id="update_photo" name="photo" class="file-input" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                   onchange="previewPhoto(this, 'update-photo-preview')">
                        </label>
                    </div>
                    
                    <div id="update-file-info" class="file-info">
                        No file selected
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('updatePhotoModal')">Cancel</button>
                    <button type="submit" name="update_photo" class="btn btn-primary">Update Photo</button>
                </div>
            </form>
        </div>
    </div>-->
    
    <!-- Remove Photo Form (hidden) -->
    <!--<form id="removePhotoForm" method="POST" action="" style="display: none;">
        <input type="hidden" id="remove_candidate_id" name="id">
        <input type="hidden" name="remove_photo" value="1">
    </form>-->
    
    <!-- Photo Gallery Preview -->
    <!--<?php if ($candidates->num_rows > 0): ?>
        <div class="image-gallery">
            <h3>Candidate Photos Gallery</h3>
            <?php 
            $candidates->data_seek(0); // Reset result pointer
            while ($candidate = $candidates->fetch_assoc()): 
            ?>
                <div class="gallery-item">
                    <?php if ($candidate['photo_filename']): ?>
                        <img src="../uploads/candidate_photos/<?php echo htmlspecialchars($candidate['photo_filename']); ?>" 
                             alt="<?php echo htmlspecialchars($candidate['name']); ?>"
                             class="gallery-photo"
                             onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100\" height=\"100\" viewBox=\"0 0 100 100\"><rect width=\"100\" height=\"100\" fill=\"%23e9ecef\"/><text x=\"50\" y=\"50\" font-family=\"Arial\" font-size=\"14\" fill=\"%236c757d\" text-anchor=\"middle\" dy=\".3em\">No Photo</text></svg>'">
                    <?php else: ?>
                        <div class="photo-placeholder" style="width: 100px; height: 100px; margin: 0 auto 10px auto;">
                            No Photo
                        </div>
                    <?php endif; ?>
                    <div><strong><?php echo htmlspecialchars($candidate['name']); ?></strong></div>
                    <div style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($candidate['party']); ?></div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>-->
</body>

</html>
<?php $conn->close(); ?>