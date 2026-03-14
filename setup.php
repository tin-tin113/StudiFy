<?php
// Setup Script for Studify
// Run this file once to create the database and tables
// WARNING: Delete this file after installation for security!

// Require explicit confirmation to prevent accidental/unauthorized runs
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    die("<!DOCTYPE html><html><head><title>Studify Setup</title></head><body style='font-family:sans-serif;padding:40px;max-width:600px;margin:auto;'>
    <h1>&#9888;&#65039; Studify Database Setup</h1>
    <p>This script will create or update the database schema. Only run this during initial installation.</p>
    <p><a href='setup.php?confirm=yes' style='display:inline-block;background:#16A34A;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;'>&#9989; Run Setup</a></p>
    <p style='color:#999;font-size:13px;margin-top:20px;'>&#9888;&#65039; Delete this file after installation for security.</p>
    </body></html>");
}

// Restrict setup execution to local environment or CLI
$is_cli = (php_sapi_name() === 'cli');
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
$is_local = in_array($remote_addr, ['127.0.0.1', '::1']);
if (!$is_cli && !$is_local) {
    http_response_code(403);
    die('Forbidden');
}

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
$conn->set_charset("utf8mb4");

// Create Users Table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    course VARCHAR(255),
    year_level INT,
    profile_photo VARCHAR(500) DEFAULT NULL,
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
    user_id INT NOT NULL,
    subject_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL COMMENT 'For subtasks - references parent task',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    deadline DATETIME NOT NULL,
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    type ENUM('Assignment', 'Quiz', 'Project', 'Exam', 'Report', 'Other') DEFAULT 'Assignment',
    status ENUM('Pending', 'Completed') DEFAULT 'Pending',
    is_recurring TINYINT(1) DEFAULT 0,
    recurrence_type ENUM('Daily', 'Weekly', 'Monthly') DEFAULT NULL,
    recurrence_end DATE DEFAULT NULL,
    position INT DEFAULT 0 COMMENT 'For Kanban ordering',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (parent_id) REFERENCES tasks(id) ON DELETE CASCADE,
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
    task_id INT DEFAULT NULL COMMENT 'Optional link to specific task',
    subject_id INT DEFAULT NULL COMMENT 'Optional link to specific subject',
    duration INT NOT NULL COMMENT 'Duration in minutes',
    session_type ENUM('Focus', 'Break') DEFAULT 'Focus',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
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
            [$subject_ids[1], 'SQL Exercise 3', 'Complete SQL queries exercises', date('Y-m-d H:i:s', strtotime('+3 days')), 'Medium', 'Assignment', 'Pending'],
            [$subject_ids[1], 'Database Design Project', 'Design ERD for the semester project', date('Y-m-d H:i:s', strtotime('+14 days')), 'High', 'Project', 'Pending'],
            [$subject_ids[2], 'Quiz 2 - DFD', 'Data Flow Diagram concepts', date('Y-m-d H:i:s', strtotime('+2 days')), 'Medium', 'Quiz', 'Pending'],
            [$subject_ids[2], 'Case Study Analysis', 'Analyze business case study', date('Y-m-d H:i:s', strtotime('-2 days')), 'Low', 'Assignment', 'Completed']
        ];
        
        foreach ($tasks as $task) {
            $stmt = $conn->prepare("INSERT INTO tasks (user_id, subject_id, title, description, deadline, priority, type, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssss", $student_id, $task[0], $task[1], $task[2], $task[3], $task[4], $task[5], $task[6]);
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

// Check and add columns manually for compatibility
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(500) DEFAULT NULL AFTER year_level");
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
$conn->query("UPDATE tasks SET status = 'Pending' WHERE status = 'In Progress'");
$conn->query("ALTER TABLE tasks MODIFY COLUMN status ENUM('Pending','Completed') DEFAULT 'Pending'");
echo "<p style='color:green;'>✅ Updated tasks enum values.</p>";

// Ensure tasks.user_id exists and is backfilled (for older installs)
$result = $conn->query("SHOW COLUMNS FROM tasks LIKE 'user_id'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE tasks ADD COLUMN user_id INT NULL FIRST");
    $conn->query("UPDATE tasks t
        LEFT JOIN subjects s ON s.id = t.subject_id
        LEFT JOIN semesters sem ON sem.id = s.semester_id
        SET t.user_id = sem.user_id
        WHERE t.user_id IS NULL");
    $conn->query("ALTER TABLE tasks MODIFY COLUMN user_id INT NOT NULL");
    $conn->query("ALTER TABLE tasks ADD INDEX idx_user_id (user_id)");
    $conn->query("ALTER TABLE tasks ADD CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    echo "<p style='color:green;'>✅ Added and backfilled tasks.user_id column.</p>";
}

// Align tasks.subject_id nullability and FK behavior with base schema
$conn->query("ALTER TABLE tasks MODIFY COLUMN subject_id INT DEFAULT NULL");

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

    // Create Task Templates Table
    $sql = "CREATE TABLE IF NOT EXISTS task_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        type ENUM('Assignment', 'Quiz', 'Project', 'Exam', 'Report', 'Other') DEFAULT 'Assignment',
        priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
        is_recurring TINYINT(1) DEFAULT 0,
        recurrence_type ENUM('Daily', 'Weekly', 'Monthly') DEFAULT NULL,
        is_system TINYINT(1) DEFAULT 0 COMMENT 'System templates available to all users',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_is_system (is_system)
    )";
    $conn->query($sql);
    echo "<p style='color:green;'>✅ Table 'task_templates' created/verified.</p>";

// Create Notes Table
$sql = "CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    content_type ENUM('plain', 'markdown') DEFAULT 'markdown',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    INDEX idx_subject_id (subject_id),
    INDEX idx_user_id (user_id)
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'notes' created/verified.</p>";

// Create Announcements Table
$sql = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    priority ENUM('Low', 'Normal', 'Important', 'Urgent') DEFAULT 'Normal',
    expires_at DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_expires_at (expires_at)
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'announcements' created/verified.</p>";

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
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'password_resets' created/verified.</p>";

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
echo "<p style='color:green;'>&#9989; Table 'activity_log' created/verified.</p>";

// Create File Attachments Table
$sql = "CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT DEFAULT NULL,
    note_id INT DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL COMMENT 'Size in bytes',
    file_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    CONSTRAINT chk_attachments_exactly_one_target CHECK (
        (task_id IS NOT NULL AND note_id IS NULL) OR
        (task_id IS NULL AND note_id IS NOT NULL)
    ),
    INDEX idx_task_id (task_id),
    INDEX idx_note_id (note_id),
    INDEX idx_user_id (user_id)
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'attachments' created/verified.</p>";

// Create Study Buddies Table
$sql = "CREATE TABLE IF NOT EXISTS study_buddies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    partner_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'unlinked') DEFAULT 'pending',
    invite_code VARCHAR(32) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pair_unordered ((LEAST(requester_id,partner_id)), (GREATEST(requester_id,partner_id))),
    INDEX idx_requester (requester_id),
    INDEX idx_partner (partner_id),
    INDEX idx_invite_code (invite_code),
    INDEX idx_status (status)
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'study_buddies' created/verified.</p>";

// Create Buddy Nudges Table
$sql = "CREATE TABLE IF NOT EXISTS buddy_nudges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_receiver (receiver_id),
    INDEX idx_is_read (is_read)
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'buddy_nudges' created/verified.</p>";

// Create Buddy Messages Table (Instant Messaging)
$sql = "CREATE TABLE IF NOT EXISTS buddy_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text','nudge','emoji','system') DEFAULT 'text',
    reply_to_id INT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation (sender_id, receiver_id, created_at),
    INDEX idx_receiver_unread (receiver_id, is_read),
    INDEX idx_created_at (created_at)
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'buddy_messages' created/verified.</p>";

// Create Buddy Typing Status Table
$sql = "CREATE TABLE IF NOT EXISTS buddy_typing_status (
    user_id INT PRIMARY KEY,
    typing_until TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'buddy_typing_status' created/verified.</p>";

// Create Buddy Blocks Table
$sql = "CREATE TABLE IF NOT EXISTS buddy_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_block (blocker_id, blocked_id),
    INDEX idx_blocker (blocker_id),
    INDEX idx_blocked (blocked_id)
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'buddy_blocks' created/verified.</p>";

// Create Buddy Reports Table
$sql = "CREATE TABLE IF NOT EXISTS buddy_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_id INT NOT NULL,
    reason ENUM('harassment', 'spam', 'inappropriate', 'impersonation', 'other') NOT NULL,
    details TEXT,
    status ENUM('pending', 'reviewed', 'resolved', 'dismissed') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reporter (reporter_id),
    INDEX idx_reported (reported_id),
    INDEX idx_status (status)
)";
$conn->query($sql);
echo "<p style='color:green;'>&#9989; Table 'buddy_reports' created/verified.</p>";

// Add last_active column to users if not exists
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL DEFAULT NULL AFTER locked_until");
    echo "<p style='color:green;'>&#9989; Added 'last_active' column to users.</p>";
}

// Upgrade study_buddies status enum to include 'unlinked'
$conn->query("ALTER TABLE study_buddies MODIFY COLUMN status ENUM('pending', 'accepted', 'declined', 'unlinked') DEFAULT 'pending'");
echo "<p style='color:green;'>&#9989; Updated study_buddies status enum.</p>";

// Enforce unordered uniqueness for buddy pairs in existing installs
$result = $conn->query("SHOW INDEX FROM study_buddies WHERE Key_name = 'unique_pair_unordered'");
if (!$result || $result->num_rows === 0) {
    // Remove reverse duplicates first (keep latest row)
    $conn->query("DELETE sb1 FROM study_buddies sb1
        JOIN study_buddies sb2
          ON LEAST(sb1.requester_id, sb1.partner_id) = LEAST(sb2.requester_id, sb2.partner_id)
         AND GREATEST(sb1.requester_id, sb1.partner_id) = GREATEST(sb2.requester_id, sb2.partner_id)
         AND sb1.id < sb2.id");

    $conn->query("ALTER TABLE study_buddies ADD UNIQUE INDEX unique_pair_unordered ((LEAST(requester_id,partner_id)), (GREATEST(requester_id,partner_id)))");
    $old_idx = $conn->query("SHOW INDEX FROM study_buddies WHERE Key_name = 'unique_pair'");
    if ($old_idx && $old_idx->num_rows > 0) {
        $conn->query("ALTER TABLE study_buddies DROP INDEX unique_pair");
    }
    echo "<p style='color:green;'>✅ Upgraded study_buddies to unordered unique pairing.</p>";
}

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
