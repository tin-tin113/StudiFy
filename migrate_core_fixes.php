<?php
/**
 * Studify Core Fixes Migration
 * Applies schema fixes for tasks ownership, template availability,
 * buddy pair uniqueness, and attachment integrity constraints.
 */

require_once 'config/db.php';

echo "Running core fixes migration...\n\n";

function runQuery($conn, $sql, $label) {
    try {
        if ($conn->query($sql)) {
            echo "OK: $label\n";
            return true;
        }
        echo "WARN: $label -> " . $conn->error . "\n";
        return false;
    } catch (Throwable $e) {
        echo "WARN: $label -> " . $e->getMessage() . "\n";
        return false;
    }
}

// Ensure task_templates exists
runQuery($conn, "CREATE TABLE IF NOT EXISTS task_templates (
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
)", 'task_templates table');

// Ensure tasks.user_id exists
$res = $conn->query("SHOW COLUMNS FROM tasks LIKE 'user_id'");
if ($res && $res->num_rows === 0) {
    runQuery($conn, "ALTER TABLE tasks ADD COLUMN user_id INT NULL FIRST", 'tasks.user_id added');
    runQuery($conn, "UPDATE tasks t
        LEFT JOIN subjects s ON s.id = t.subject_id
        LEFT JOIN semesters sem ON sem.id = s.semester_id
        SET t.user_id = sem.user_id
        WHERE t.user_id IS NULL", 'tasks.user_id backfilled');
    runQuery($conn, "ALTER TABLE tasks MODIFY COLUMN user_id INT NOT NULL", 'tasks.user_id set NOT NULL');
    runQuery($conn, "ALTER TABLE tasks ADD INDEX idx_user_id (user_id)", 'tasks.user_id index');
    runQuery($conn, "ALTER TABLE tasks ADD CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE", 'tasks.user_id foreign key');
}

runQuery($conn, "ALTER TABLE tasks MODIFY COLUMN subject_id INT DEFAULT NULL", 'tasks.subject_id nullable');

// Add buddy unordered uniqueness
runQuery($conn, "DELETE sb FROM study_buddies sb
    LEFT JOIN users u1 ON u1.id = sb.requester_id
    LEFT JOIN users u2 ON u2.id = sb.partner_id
    WHERE u1.id IS NULL OR u2.id IS NULL", 'study_buddies orphan rows removed');

runQuery($conn, "DELETE sb1 FROM study_buddies sb1
    JOIN study_buddies sb2
      ON LEAST(sb1.requester_id, sb1.partner_id) = LEAST(sb2.requester_id, sb2.partner_id)
     AND GREATEST(sb1.requester_id, sb1.partner_id) = GREATEST(sb2.requester_id, sb2.partner_id)
     AND sb1.id < sb2.id", 'study_buddies reverse duplicates removed');

$res = $conn->query("SHOW INDEX FROM study_buddies WHERE Key_name = 'unique_pair_unordered'");
if (!$res || $res->num_rows === 0) {
    $addedFunctional = runQuery($conn,
        "ALTER TABLE study_buddies ADD UNIQUE INDEX unique_pair_unordered ((LEAST(requester_id,partner_id)), (GREATEST(requester_id,partner_id)))",
        'study_buddies unordered unique index'
    );

    if ($addedFunctional) {
        $old = $conn->query("SHOW INDEX FROM study_buddies WHERE Key_name = 'unique_pair'");
        if ($old && $old->num_rows > 0) {
            runQuery($conn, "ALTER TABLE study_buddies DROP INDEX unique_pair", 'study_buddies old unique index dropped');
        }
    }
} else {
    $old = $conn->query("SHOW INDEX FROM study_buddies WHERE Key_name = 'unique_pair'");
    if ($old && $old->num_rows > 0) {
        runQuery($conn, "ALTER TABLE study_buddies DROP INDEX unique_pair", 'study_buddies old unique index dropped');
    }
}

// Add attachment XOR check if possible
$chk = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'attachments'
      AND CONSTRAINT_TYPE = 'CHECK'
      AND CONSTRAINT_NAME = 'chk_attachments_exactly_one_target'");
if (!$chk || $chk->num_rows === 0) {
    runQuery($conn, "ALTER TABLE attachments
        ADD CONSTRAINT chk_attachments_exactly_one_target CHECK (
            (task_id IS NOT NULL AND note_id IS NULL) OR
            (task_id IS NULL AND note_id IS NOT NULL)
        )", 'attachments XOR target check');
} else {
    echo "OK: attachments XOR target check\n";
}

echo "\nCore fixes migration complete.\n";
