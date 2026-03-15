<?php
/**
 * STUDIFY – Register
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

$page_title = 'Register';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $name = cleanInput($_POST['name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $course = sanitize($_POST['course'] ?? '');
    $year_level = intval($_POST['year_level'] ?? 0);
    
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one uppercase letter and one number.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already registered. Please login instead.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $insert_query = "INSERT INTO users (name, email, password, course, year_level, role) VALUES (?, ?, ?, ?, ?, 'student')";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssssi", $name, $email, $hashed_password, $course, $year_level);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! Redirecting to login...';
                header("refresh:2; url=login.php");
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – Studify</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/style.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='40' height='40' rx='10' fill='%2316A34A'/%3E%3Cpath d='M10 13.5c0-.6.4-1 1-1 1.5 0 4.2.5 9 2.5v13.5c-4.8-2-7.5-2.5-9-2.5-.6 0-1-.4-1-1V13.5z' fill='%23fff' opacity='.9'/%3E%3Cpath d='M30 13.5c0-.6-.4-1-1-1-1.5 0-4.2.5-9 2.5v13.5c4.8-2 7.5-2.5 9-2.5.6 0 1-.4 1-1V13.5z' fill='%23fff' opacity='.7'/%3E%3C/svg%3E">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card" style="max-width: 460px;">
            <div class="auth-header">
                <div class="brand-icon">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="40" height="40" rx="10" fill="#16A34A"/>
                        <path d="M20 12c-2.5 0-5 .8-5 .8v14.4s2.5-.8 5-.8 5 .8 5 .8V12.8s-2.5-.8-5-.8z" fill="#fff" opacity=".15"/>
                        <path d="M10 13.5c0-.6.4-1 1-1 1.5 0 4.2.5 9 2.5v13.5c-4.8-2-7.5-2.5-9-2.5-.6 0-1-.4-1-1V13.5z" fill="#fff" opacity=".9"/>
                        <path d="M30 13.5c0-.6-.4-1-1-1-1.5 0-4.2.5-9 2.5v13.5c4.8-2 7.5-2.5 9-2.5.6 0 1-.4 1-1V13.5z" fill="#fff" opacity=".7"/>
                        <line x1="20" y1="14.5" x2="20" y2="28.5" stroke="#16A34A" stroke-width=".6" opacity=".5"/>
                    </svg>
                </div>
                <h2>Create Account</h2>
                <p>Join Studify and start organizing smarter</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfTokenField(); ?>
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Juan Dela Cruz" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required>
                </div>

                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label for="course" class="form-label">Course</label>
                        <input type="text" class="form-control" id="course" name="course" placeholder="e.g., BS Information Systems">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label for="year_level" class="form-label">Year Level</label>
                        <select class="form-select" id="year_level" name="year_level" required>
                            <option value="">Select</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Min 8 chars, 1 uppercase, 1 number" required>
                            <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePasswordVisibility(this)" title="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                            <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePasswordVisibility(this)" title="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-register">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
</body>
</html>
