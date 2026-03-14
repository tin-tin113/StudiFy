-- Migration: Add Task Templates Table
-- Phase 1 Feature: Task Templates System

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some default system templates
INSERT INTO task_templates (user_id, name, title, description, type, priority, is_system) VALUES
(1, 'Weekly Lab Report', 'Lab Report - Week {week}', 'Complete lab report for this week\'s experiment', 'Report', 'Medium', 1),
(1, 'Quiz Preparation', 'Quiz: {subject}', 'Study and prepare for upcoming quiz', 'Quiz', 'High', 1),
(1, 'Assignment Submission', 'Assignment: {title}', 'Complete and submit assignment', 'Assignment', 'High', 1),
(1, 'Project Milestone', 'Project Milestone: {milestone}', 'Work on project milestone', 'Project', 'High', 1),
(1, 'Exam Review', 'Exam Review: {subject}', 'Review materials for upcoming exam', 'Exam', 'High', 1);
