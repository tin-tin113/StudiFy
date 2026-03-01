<?php
// Setup Script for Studify
// Run this file once to create the database and tables

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'studify';

echo "<h1>Studify - Database Setup</h1>";

// Connect to MySQL (without database)
$conn = new mysqli($db_host, $db_user, $db_password);

if ($conn->connect_error) {
    die("<p style='color:red;'>Connection Failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color:green;'>✅ Connected to MySQL successfully.</p>";

// Create Database
$sql = "CREATE DATABASE IF NOT EXISTS `$db_name`";
if ($conn->query($sql)) {
    echo "<p style='color:green;'>✅ Database '$db_name' created/verified.</p>";
} else {
    die("<p style='color:red;'>Error creating database: " . $conn->error . "</p>");
}

// Select database
$conn->select_db($db_name);
$conn->set_charset("utf8");

// Create Users Table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    course VARCHAR(255),
    year_level INT,
    profile_photo VARCHAR(255) DEFAULT NULL,
    onboarding_completed TINYINT(1) DEFAULT 0,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
)";
if ($conn->query($sql)) {
    echo "<p style='color:green;'>✅ Table 'users' created/verified.</p>";
} else {
    echo "<p style='color:red;'>Error creating users table: " . $conn->error . "</p>";
}

// Create Semesters Table
$sql = "CREATE TABLE IF NOT EXISTS semesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
)";
if ($conn->query($sql)) {
    echo "<p style='color:green;'>✅ Table 'semesters' created/verified.</p>";
} else {
    echo "<p style='color:red;'>Error creating semesters table: " . $conn->error . "</p>";
}

// Create Subjects Table
$sql = "CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semester_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    instructor_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    INDEX idx_semester_id (semester_id)
)";
if ($conn->query($sql)) {
    echo "<p style='color:green;'>✅ Table 'subjects' created/verified.</p>";
} else {
    echo "<p style='color:red;'>Error creating subjects table: " . $conn->error . "</p>";
}

// Create Tasks Table
$sql = "CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    deadline DATETIME NOT NULL,
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    type ENUM('Assignment', 'Quiz', 'Project', 'Exam', 'Report', 'Other') DEFAULT 'Assignment',
    status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_subject_id (subject_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_deadline (deadline),
    INDEX idx_status (status)
)";
if ($conn->query($sql)) {
    echo "<p style='color:green;'>✅ Table 'tasks' created/verified.</p>";
} else {
    echo "<p style='color:red;'>Error creating tasks table: " . $conn->error . "</p>";
}

// Create Study Sessions Table
$sql = "CREATE TABLE IF NOT EXISTS study_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    duration INT NOT NULL COMMENT 'Duration in minutes',
    session_type ENUM('Focus', 'Break') DEFAULT 'Focus',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
)";
if ($conn->query($sql)) {
    echo "<p style='color:green;'>✅ Table 'study_sessions' created/verified.</p>";
} else {
    echo "<p style='color:red;'>Error creating study_sessions table: " . $conn->error . "</p>";
}

// Insert default users (Admin and Student)
$default_password = password_hash('password123', PASSWORD_BCRYPT);

// Check if admin exists
$check = $conn->query("SELECT id FROM users WHERE email = 'admin@studify.com'");
if ($check->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, course, year_level) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $admin_name = 'Admin User';
    $admin_email = 'admin@studify.com';
    $admin_role = 'admin';
    $admin_course = 'System Administrator';
    $admin_year = 0;
    $stmt->bind_param("sssssi", $admin_name, $admin_email, $default_password, $admin_role, $admin_course, $admin_year);
    
    if ($stmt->execute()) {
        echo "<p style='color:green;'>✅ Default admin user created.</p>";
    } else {
        echo "<p style='color:red;'>Error creating admin user: " . $stmt->error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ Admin user already exists.</p>";
}

// Check if student exists
$check = $conn->query("SELECT id FROM users WHERE email = 'student@studify.com'");
if ($check->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, course, year_level) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $student_name = 'John Doe';
    $student_email = 'student@studify.com';
    $student_role = 'student';
    $student_course = 'BS Information Systems';
    $student_year = 3;
    $stmt->bind_param("sssssi", $student_name, $student_email, $default_password, $student_role, $student_course, $student_year);
    
    if ($stmt->execute()) {
        echo "<p style='color:green;'>✅ Default student user created.</p>";
        
        // Create sample data for student
        $student_id = $conn->insert_id;
        
        // Create sample semester
        $stmt = $conn->prepare("INSERT INTO semesters (user_id, name, is_active) VALUES (?, ?, ?)");
        $sem_name = '1st Semester 2025-2026';
        $active = 1;
        $stmt->bind_param("isi", $student_id, $sem_name, $active);
        $stmt->execute();
        $semester_id = $conn->insert_id;
        
        // Create sample subjects
        $subjects = [
            ['Information Systems', 'Prof. Maria Santos'],
            ['Database Management', 'Prof. Juan Cruz'],
            ['Systems Analysis', 'Prof. Ana Reyes']
        ];
        
        foreach ($subjects as $subj) {
            $stmt = $conn->prepare("INSERT INTO subjects (semester_id, name, instructor_name) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $semester_id, $subj[0], $subj[1]);
            $stmt->execute();
            $subject_ids[] = $conn->insert_id;
        }
        
        // Create sample tasks
        $tasks = [
            [$subject_ids[0], 'Research Paper Draft', 'Write the first draft of the research paper', date('Y-m-d H:i:s', strtotime('+5 days')), 'High', 'Assignment', 'Pending'],
            [$subject_ids[0], 'Midterm Exam', 'Chapters 1-5', date('Y-m-d H:i:s', strtotime('+10 days')), 'High', 'Exam', 'Pending'],
            [$subject_ids[1], 'SQL Exercise 3', 'Complete SQL queries exercises', date('Y-m-d H:i:s', strtotime('+3 days')), 'Medium', 'Assignment', 'In Progress'],
            [$subject_ids[1], 'Database Design Project', 'Design ERD for the semester project', date('Y-m-d H:i:s', strtotime('+14 days')), 'High', 'Project', 'Pending'],
            [$subject_ids[2], 'Quiz 2 - DFD', 'Data Flow Diagram concepts', date('Y-m-d H:i:s', strtotime('+2 days')), 'Medium', 'Quiz', 'Pending'],
            [$subject_ids[2], 'Case Study Analysis', 'Analyze business case study', date('Y-m-d H:i:s', strtotime('-2 days')), 'Low', 'Assignment', 'Completed']
        ];
        
        foreach ($tasks as $task) {
            $stmt = $conn->prepare("INSERT INTO tasks (subject_id, title, description, deadline, priority, type, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $task[0], $task[1], $task[2], $task[3], $task[4], $task[5], $task[6]);
            $stmt->execute();
        }
        
        echo "<p style='color:green;'>✅ Sample data (semester, subjects, tasks) created.</p>";
    } else {
        echo "<p style='color:red;'>Error creating student user: " . $stmt->error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ Student user already exists.</p>";
}

echo "<br><hr>";
echo "<h2>Applying Schema Upgrades...</h2>";

// Add new columns to users table if not exist
$upgrades = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT NULL AFTER year_level",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS onboarding_completed TINYINT(1) DEFAULT 0 AFTER profile_photo",
];

// Some MySQL versions don't support IF NOT EXISTS for ALTER TABLE ADD COLUMN
foreach ($upgrades as $sql) {
    $conn->query($sql); // Silently ignore if column already exists
}

// Check and add columns manually for compatibility
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER year_level");
    echo "<p style='color:green;'>✅ Added 'profile_photo' column to users.</p>";
}

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'onboarding_completed'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN onboarding_completed TINYINT(1) DEFAULT 0 AFTER profile_photo");
    echo "<p style='color:green;'>✅ Added 'onboarding_completed' column to users.</p>";
}

// Add login lockout columns to users table
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'login_attempts'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN login_attempts INT DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN locked_until DATETIME DEFAULT NULL");
    echo "<p style='color:green;'>✅ Added 'login_attempts' and 'locked_until' columns to users.</p>";
}

// Upgrade tasks table - add additional types and statuses
$conn->query("ALTER TABLE tasks MODIFY COLUMN type ENUM('Assignment','Quiz','Project','Exam','Report','Other') DEFAULT 'Assignment'");
$conn->query("ALTER TABLE tasks MODIFY COLUMN status ENUM('Pending','In Progress','Completed') DEFAULT 'Pending'");
echo "<p style='color:green;'>✅ Updated tasks enum values.</p>";

// Add parent_id for subtasks support
$result = $conn->query("SHOW COLUMNS FROM tasks LIKE 'parent_id'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE tasks ADD COLUMN parent_id INT DEFAULT NULL AFTER subject_id");
    $conn->query("ALTER TABLE tasks ADD INDEX idx_parent_id (parent_id)");
    echo "<p style='color:green;'>✅ Added 'parent_id' column to tasks for subtask support.</p>";
}

// Add position for task ordering
$result = $conn->query("SHOW COLUMNS FROM tasks LIKE 'position'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE tasks ADD COLUMN position INT DEFAULT 0");
    echo "<p style='color:green;'>✅ Added 'position' column to tasks.</p>";
}

// Add recurring fields to tasks if not exist
$result = $conn->query("SHOW COLUMNS FROM tasks LIKE 'is_recurring'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE tasks ADD COLUMN is_recurring TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE tasks ADD COLUMN recurrence_type ENUM('Daily','Weekly','Monthly') DEFAULT NULL");
    $conn->query("ALTER TABLE tasks ADD COLUMN recurrence_end DATE DEFAULT NULL");
    echo "<p style='color:green;'>✅ Added recurring fields to tasks.</p>";
}

// Create Notes Table
$sql = "CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
)";
$conn->query($sql);
echo "<p style='color:green;'>✅ Table 'notes' created/verified.</p>";

// Create Announcements Table
$sql = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    priority ENUM('Normal','Important','Urgent') DEFAULT 'Normal',
    created_by INT,
    expires_at DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
)";
$conn->query($sql);
echo "<p style='color:green;'>✅ Table 'announcements' created/verified.</p>";

// Create Announcement Reads Table
$sql = "CREATE TABLE IF NOT EXISTS announcement_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    announcement_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (user_id, announcement_id)
)";
$conn->query($sql);
echo "<p style='color:green;'>✅ Table 'announcement_reads' created/verified.</p>";

// Create Password Resets Table
$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token)
)";
$conn->query($sql);
echo "<p style='color:green;'>✅ Table 'password_resets' created/verified.</p>";

// Create Login Attempts Table
$sql = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
)";
$conn->query($sql);
echo "<p style='color:green;'>✅ Table 'login_attempts' created/verified.</p>";

// Create Activity Log Table
$sql = "CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
)";
$conn->query($sql);
echo "<p style='color:green;'>✅ Table 'activity_log' created/verified.</p>";

// Create uploads directory
$upload_dirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/avatars',
    __DIR__ . '/uploads/photos',
];
foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "<p style='color:green;'>✅ Created directory: " . basename($dir) . "</p>";
    }
}

echo "<br><hr>";
echo "<h2>Setup Complete!</h2>";
echo "<p><strong>Default Login Credentials:</strong></p>";
echo "<p><strong>Admin:</strong> admin@studify.com / password123</p>";
echo "<p><strong>Student:</strong> student@studify.com / password123</p>";
echo "<br>";
echo "<p><a href='index.php' style='font-size:1.2rem; color:#667eea;'>🚀 Go to Studify</a></p>";
echo "<br>";
echo "<p style='color:orange;'>⚠️ <strong>Important:</strong> Delete this file (setup.php) after installation for security!</p>";

$conn->close();
?>
