<?php
/**
 * STUDIFY – Profile Page
 * With CSRF protection and profile photo upload
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$page_title = 'My Profile';
$user_id = getCurrentUserId();
$user = getUserInfo($user_id, $conn);

if (!$user_id || !$user) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$error = '';
$success = '';

// Ensure uploads directory exists
$upload_dir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    // Handle remove photo action
    if (isset($_POST['remove_photo'])) {
        if ($user['profile_photo'] && file_exists(__DIR__ . '/../' . $user['profile_photo'])) {
            unlink(__DIR__ . '/../' . $user['profile_photo']);
        }
        $stmt = $conn->prepare("UPDATE users SET profile_photo = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $success = 'Profile photo removed.';
        $user = getUserInfo($user_id, $conn);
    } else {
    // Handle profile update
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $course = sanitize($_POST['course'] ?? '');
    $year_level = intval($_POST['year_level'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } else {
        if ($email !== $user['email']) {
            $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = 'Email is already in use.';
            }
        }
        
        // Handle profile photo upload
        $photo_path = $user['profile_photo'] ?? null;
        if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file_type = mime_content_type($_FILES['profile_photo']['tmp_name']);
            $file_size = $_FILES['profile_photo']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error = 'Invalid image type. Allowed: JPG, PNG, GIF, WebP.';
            } elseif ($file_size > $max_size) {
                $error = 'Image too large. Maximum size is 2MB.';
            } else {
                $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
                    // Delete old photo if exists
                    if ($photo_path && file_exists(__DIR__ . '/../' . $photo_path)) {
                        unlink(__DIR__ . '/../' . $photo_path);
                    }
                    $photo_path = 'uploads/avatars/' . $filename;
                } else {
                    $error = 'Failed to upload image. Please try again.';
                }
            }
        }
        
        if (empty($error)) {
            if (!empty($new_password)) {
                if (strlen($new_password) < 8) {
                    $error = 'New password must be at least 8 characters with at least one uppercase letter and one number.';
                } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                    $error = 'Password must contain at least one uppercase letter and one number.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $update_query = "UPDATE users SET name = ?, email = ?, course = ?, year_level = ?, password = ?, profile_photo = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("sssissi", $name, $email, $course, $year_level, $hashed_password, $photo_path, $user_id);
                }
            } else {
                $update_query = "UPDATE users SET name = ?, email = ?, course = ?, year_level = ?, profile_photo = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("sssisi", $name, $email, $course, $year_level, $photo_path, $user_id);
            }
            
            if (empty($error) && $stmt->execute()) {
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $success = 'Profile updated successfully!';
                $user = getUserInfo($user_id, $conn);
            } elseif (empty($error)) {
                $error = 'Error updating profile. Please try again.';
            }
        }
    }
    } // end else (profile update)
}
?>
<?php include '../includes/header.php'; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Info Card -->
            <div class="col-lg-4 mb-4">
                <div class="card text-center">
                    <div class="card-body" style="padding: 40px 24px;">
                        <div class="profile-photo-wrapper" style="position: relative; width: 100px; height: 100px; margin: 0 auto 16px;">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="<?php echo BASE_URL . htmlspecialchars($user['profile_photo']); ?>" 
                                     alt="Profile" class="rounded-circle" 
                                     style="width: 100px; height: 100px; object-fit: cover; border: 3px solid var(--primary);">
                            <?php else: ?>
                                <div style="width: 100px; height: 100px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 36px; font-weight: 700;">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <label for="photoUpload" class="photo-edit-btn" style="position: absolute; bottom: 0; right: 0; width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid var(--card-bg); font-size: 12px;">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                        <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>" style="font-size: 12px; padding: 6px 16px;">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                        
                        <?php if (!empty($user['profile_photo'])): ?>
                        <form method="POST" class="mt-2">
                            <?php echo getCSRFField(); ?>
                            <input type="hidden" name="remove_photo" value="1">
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return StudifyConfirm.buttonConfirm(event, 'Remove Photo', 'Are you sure you want to remove your profile photo?', 'warning')">
                                <i class="fas fa-trash-alt"></i> Remove Photo
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <hr>
                        <div class="text-start" style="font-size: 13.5px;">
                            <?php if ($user['role'] !== 'admin'): ?>
                            <p class="mb-2"><i class="fas fa-graduation-cap text-primary me-2"></i> <strong>Course:</strong> <?php echo htmlspecialchars($user['course'] ?? 'Not set'); ?></p>
                            <p class="mb-2"><i class="fas fa-layer-group text-primary me-2"></i> <strong>Year:</strong> <?php echo $user['year_level'] ? $user['year_level'] . ' Year' : 'Not set'; ?></p>
                            <?php else: ?>
                            <p class="mb-2"><i class="fas fa-user-shield text-primary me-2"></i> <strong>Role:</strong> System Administrator</p>
                            <?php endif; ?>
                            <p class="mb-0"><i class="fas fa-calendar text-primary me-2"></i> <strong>Joined:</strong> <?php echo formatDate($user['created_at']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <?php echo getCSRFField(); ?>
                            
                            <!-- Hidden photo upload -->
                            <input type="file" id="photoUpload" name="profile_photo" accept="image/*" 
                                   style="display: none;" onchange="previewPhoto(this)">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>

                            <?php if ($user['role'] !== 'admin'): ?>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="course" class="form-label">Course</label>
                                    <input type="text" class="form-control" id="course" name="course" 
                                           value="<?php echo htmlspecialchars($user['course'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="year_level" class="form-label">Year Level</label>
                                    <select class="form-select" id="year_level" name="year_level">
                                        <option value="">Select</option>
                                        <option value="1" <?php echo $user['year_level'] == 1 ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2" <?php echo $user['year_level'] == 2 ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3" <?php echo $user['year_level'] == 3 ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4" <?php echo $user['year_level'] == 4 ? 'selected' : ''; ?>>4th Year</option>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>

                            <hr class="my-4">
                            <h6 class="fw-700 mb-3"><i class="fas fa-lock text-primary me-2"></i> Change Password <small class="text-muted fw-500"></small></h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password"
                                           placeholder="Leave blank to keep current">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                           placeholder="Repeat new password">
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <a href="<?php echo BASE_URL; ?><?php echo $user['role'] === 'admin' ? 'admin/admin_dashboard.php' : 'student/dashboard.php'; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        // Auto-submit when photo is selected
        input.closest('form').submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
