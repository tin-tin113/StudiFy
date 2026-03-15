-- Studify - Student Task Management System
-- MySQL Database Schema (v2.0 - Enhanced)

-- Create Database
CREATE DATABASE IF NOT EXISTS studify;
USE studify;

-- Users Table (with profile photo & onboarding tracking)
CREATE TABLE IF NOT EXISTS users (
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
);

-- Password Reset Tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
);

-- Semesters Table
CREATE TABLE IF NOT EXISTS semesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
);

-- Subjects Table
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semester_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    instructor_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    INDEX idx_semester_id (semester_id)
);

-- Tasks Table (with subtasks support via parent_id)
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_id INT DEFAULT NULL COMMENT 'Optional link to a subject',
    parent_id INT DEFAULT NULL COMMENT 'For subtasks - references parent task',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    deadline DATETIME DEFAULT NULL,
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    type ENUM('Assignment', 'Quiz', 'Project', 'Exam', 'Report', 'Other') DEFAULT 'Assignment',
    status ENUM('Pending', 'Completed') DEFAULT 'Pending',
    is_recurring TINYINT(1) DEFAULT 0,
    recurrence_type ENUM('Daily', 'Weekly', 'Monthly') DEFAULT NULL,
    recurrence_end DATE DEFAULT NULL,
    position INT DEFAULT 0 COMMENT 'Reserved for future task ordering',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_subject_id (subject_id),
    INDEX idx_deadline (deadline),
    INDEX idx_status (status),
    INDEX idx_parent_id (parent_id)
);

-- Study Sessions Table (linked to tasks/subjects)
CREATE TABLE IF NOT EXISTS study_sessions (
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
);

-- Notes Table (with rich text support)
CREATE TABLE IF NOT EXISTS notes (
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
);

-- File Attachments Table
CREATE TABLE IF NOT EXISTS attachments (
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
);

-- Announcements Table (Admin → Students)
CREATE TABLE IF NOT EXISTS announcements (
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
);

-- Announcement Reads (track which students dismissed/read)
CREATE TABLE IF NOT EXISTS announcement_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (announcement_id, user_id)
);

-- Study Buddy Pairings
CREATE TABLE IF NOT EXISTS study_buddies (
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
);

-- Task Templates (user + system templates)
CREATE TABLE IF NOT EXISTS task_templates (
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
);

-- Study Buddy Nudges
CREATE TABLE IF NOT EXISTS buddy_nudges (
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
);

-- Activity Log (optional – for dedicated audit trail)
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Note: Login attempts are tracked via the users.login_attempts column
-- and users.locked_until for brute-force lockout. A standalone login_attempts
-- table is not used in the current implementation.

-- ============================================
-- Migration queries for existing databases:
-- ============================================
-- ALTER TABLE users ADD COLUMN profile_photo VARCHAR(500) DEFAULT NULL AFTER year_level;
-- ALTER TABLE users ADD COLUMN onboarding_completed TINYINT(1) DEFAULT 0 AFTER profile_photo;
-- ALTER TABLE users ADD COLUMN login_attempts INT DEFAULT 0 AFTER onboarding_completed;
-- ALTER TABLE users ADD COLUMN locked_until DATETIME DEFAULT NULL AFTER login_attempts;
-- ALTER TABLE tasks ADD COLUMN parent_id INT DEFAULT NULL AFTER subject_id;
-- ALTER TABLE tasks ADD COLUMN is_recurring TINYINT(1) DEFAULT 0 AFTER status;
-- ALTER TABLE tasks ADD COLUMN recurrence_type ENUM('Daily', 'Weekly', 'Monthly') DEFAULT NULL AFTER is_recurring;
-- ALTER TABLE tasks ADD COLUMN recurrence_end DATE DEFAULT NULL AFTER recurrence_type;
-- ALTER TABLE tasks ADD COLUMN position INT DEFAULT 0 AFTER recurrence_end;
-- ALTER TABLE tasks MODIFY COLUMN status ENUM('Pending', 'Completed') DEFAULT 'Pending';
-- ALTER TABLE tasks MODIFY COLUMN type ENUM('Assignment', 'Quiz', 'Project', 'Exam', 'Report', 'Other') DEFAULT 'Assignment';
-- ALTER TABLE study_sessions ADD COLUMN task_id INT DEFAULT NULL AFTER user_id;
-- ALTER TABLE study_sessions ADD COLUMN subject_id INT DEFAULT NULL AFTER task_id;
-- ALTER TABLE notes ADD COLUMN content_type ENUM('plain', 'markdown') DEFAULT 'markdown' AFTER content;
-- ALTER TABLE notes MODIFY COLUMN content LONGTEXT;
-- CREATE TABLE IF NOT EXISTS password_resets (...);
-- CREATE TABLE IF NOT EXISTS attachments (...);
-- CREATE TABLE IF NOT EXISTS study_buddies (...);
-- CREATE TABLE IF NOT EXISTS buddy_nudges (...);

-- Note: It's recommended to run setup.php instead of this file.
-- The setup.php script will generate proper bcrypt password hashes.
-- If you use this SQL file, register new users through the application.

-- Insert Sample Admin User (password: password123)
-- INSERT INTO users (name, email, password, role, course, year_level) 
-- VALUES ('Admin User', 'admin@studify.com', 'USE_SETUP_PHP_INSTEAD', 'admin', 'System Administrator', 0);

-- Insert Sample Student User (password: password123)
-- INSERT INTO users (name, email, password, role, course, year_level) 
-- VALUES ('John Doe', 'student@studify.com', 'USE_SETUP_PHP_INSTEAD', 'student', 'BS Information Systems', 3);

-- Note: Password is 'password123' - Use setup.php to create users with proper hashing

-- ============================================
-- v3.0 – Buddy Chat / Instant Messaging Tables
-- ============================================

-- Buddy Chat Messages (Instant Messaging)
CREATE TABLE IF NOT EXISTS buddy_messages (
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
    FOREIGN KEY (reply_to_id) REFERENCES buddy_messages(id) ON DELETE SET NULL,
    INDEX idx_conversation (sender_id, receiver_id, created_at),
    INDEX idx_receiver_unread (receiver_id, is_read),
    INDEX idx_created_at (created_at)
);

-- Typing Status Tracking (for real-time typing indicators)
CREATE TABLE IF NOT EXISTS buddy_typing_status (
    user_id INT PRIMARY KEY,
    typing_until TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- Migration queries for v3.0 (Buddy Chat):
-- ============================================
-- ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL DEFAULT NULL AFTER locked_until;
-- ALTER TABLE study_buddies MODIFY COLUMN status ENUM('pending', 'accepted', 'declined', 'unlinked') DEFAULT 'pending';

-- ============================================
-- v3.1 – Buddy Safety & Performance
-- ============================================

-- Buddy Blocks (prevent requests & messages)
CREATE TABLE IF NOT EXISTS buddy_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_block (blocker_id, blocked_id),
    INDEX idx_blocker (blocker_id),
    INDEX idx_blocked (blocked_id)
);

-- Buddy Reports (admin review queue)
CREATE TABLE IF NOT EXISTS buddy_reports (
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
);

-- ============================================
-- v4.0 – Notification & Reminder System
-- ============================================

-- Notifications Table (in-app notifications)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deadline_24h', 'deadline_1h', 'overdue', 'study_reminder', 'streak_risk') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    reference_id INT DEFAULT NULL COMMENT 'Optional task or session ID',
    reference_type ENUM('task', 'session', 'general') DEFAULT 'general',
    is_read TINYINT(1) DEFAULT 0,
    is_dismissed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read, is_dismissed),
    INDEX idx_user_type_ref (user_id, type, reference_id),
    INDEX idx_created_at (created_at)
);

-- Notification Preferences (per-user toggles)
CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id INT PRIMARY KEY,
    deadline_24h TINYINT(1) DEFAULT 1,
    deadline_1h TINYINT(1) DEFAULT 1,
    overdue_alerts TINYINT(1) DEFAULT 1,
    study_reminders TINYINT(1) DEFAULT 1,
    streak_alerts TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- Migration queries for v4.0 (Notifications):
-- ============================================
-- CREATE TABLE IF NOT EXISTS notifications (...);
-- CREATE TABLE IF NOT EXISTS notification_preferences (...);

-- ============================================
-- v5.0 – Dashboard Widgets & Study Streak
-- ============================================

-- Dashboard Widget Preferences (per-user widget order & visibility)
CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    widget_key VARCHAR(50) NOT NULL,
    position INT DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_widget (user_id, widget_key),
    INDEX idx_user_id (user_id)
);

-- User Achievements / Badges
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_key VARCHAR(100) NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_achievement (user_id, achievement_key),
    INDEX idx_user_id (user_id)
);

-- ============================================
-- Migration queries for v5.0:
-- ============================================
-- CREATE TABLE IF NOT EXISTS dashboard_widgets (...);
-- CREATE TABLE IF NOT EXISTS user_achievements (...);
