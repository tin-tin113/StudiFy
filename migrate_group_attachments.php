<?php
/**
 * STUDIFY – Group Attachments Migration (Production Safe)
 * Adds group_task_id column to attachments table if missing.
 * Run this once on production to fix group task uploads.
 *
 * Access: Admin-only via browser (not CLI-restricted so it works on Render).
 */
define('BASE_URL', './');
require_once 'config/db.php';
require_once 'includes/auth.php';

// Require admin login for safety
if (!isLoggedIn() || !isAdminRole()) {
    http_response_code(403);
    die('<h2>403 Forbidden</h2><p>You must be logged in as admin to run migrations.</p><p><a href="auth/login.php">Login</a></p>');
}

header('Content-Type: text/html; charset=utf-8');
echo '<html><head><title>Migration: Group Attachments</title>';
echo '<style>body{font-family:monospace;padding:2rem;background:#111;color:#0f0;} .ok{color:#0f0;} .err{color:#f44;} .warn{color:#fa0;} h2{color:#0ff;}</style>';
echo '</head><body>';
echo '<h2>🔧 Studify Migration: Group Task Attachments</h2><hr>';

$results = [];

// Step 1: Check if group_task_id column exists
$col_check = $conn->query("SHOW COLUMNS FROM attachments LIKE 'group_task_id'");
if ($col_check && $col_check->num_rows > 0) {
    echo '<p class="warn">⚠ Column `group_task_id` already exists — skipping ADD COLUMN.</p>';
} else {
    $sql = "ALTER TABLE attachments ADD COLUMN group_task_id INT DEFAULT NULL AFTER note_id";
    if ($conn->query($sql)) {
        echo '<p class="ok">✅ Added column `group_task_id` to attachments table.</p>';
    } else {
        echo '<p class="err">❌ Failed to add column: ' . htmlspecialchars($conn->error) . '</p>';
    }
}

// Step 2: Check and add foreign key
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attachments' 
    AND CONSTRAINT_NAME = 'fk_attachments_group_task'");
if ($fk_check && $fk_check->num_rows > 0) {
    echo '<p class="warn">⚠ Foreign key `fk_attachments_group_task` already exists — skipping.</p>';
} else {
    // Verify group_tasks table exists first
    $gt_check = $conn->query("SHOW TABLES LIKE 'group_tasks'");
    if ($gt_check && $gt_check->num_rows > 0) {
        $sql = "ALTER TABLE attachments ADD CONSTRAINT fk_attachments_group_task FOREIGN KEY (group_task_id) REFERENCES group_tasks(id) ON DELETE CASCADE";
        if ($conn->query($sql)) {
            echo '<p class="ok">✅ Added foreign key `fk_attachments_group_task`.</p>';
        } else {
            echo '<p class="err">❌ Failed to add FK: ' . htmlspecialchars($conn->error) . '</p>';
        }
    } else {
        echo '<p class="err">❌ Table `group_tasks` does not exist — cannot add FK. Run group tables migration first.</p>';
    }
}

// Step 3: Add index if missing
$idx_check = $conn->query("SHOW INDEX FROM attachments WHERE Key_name = 'idx_group_task_id'");
if ($idx_check && $idx_check->num_rows > 0) {
    echo '<p class="warn">⚠ Index `idx_group_task_id` already exists — skipping.</p>';
} else {
    $sql = "ALTER TABLE attachments ADD INDEX idx_group_task_id (group_task_id)";
    if ($conn->query($sql)) {
        echo '<p class="ok">✅ Added index `idx_group_task_id`.</p>';
    } else {
        echo '<p class="err">❌ Failed to add index: ' . htmlspecialchars($conn->error) . '</p>';
    }
}

// Step 4: Drop old CHECK constraint (if it exists) and add new one
// MySQL 8.0.16+ supports CHECK constraints; earlier versions silently ignore them
echo '<p>Updating CHECK constraint...</p>';

// Try to drop old constraint — MySQL syntax varies
$conn->query("ALTER TABLE attachments DROP CHECK chk_attachments_exactly_one_target");
$conn->query("ALTER TABLE attachments DROP CONSTRAINT chk_attachments_exactly_one_target");
// Both may fail silently if constraint doesn't exist — that's OK

$sql = "ALTER TABLE attachments ADD CONSTRAINT chk_attachments_exactly_one_target CHECK (
    (task_id IS NOT NULL AND note_id IS NULL AND group_task_id IS NULL) OR
    (task_id IS NULL AND note_id IS NOT NULL AND group_task_id IS NULL) OR
    (task_id IS NULL AND note_id IS NULL AND group_task_id IS NOT NULL)
)";
if ($conn->query($sql)) {
    echo '<p class="ok">✅ Updated CHECK constraint to allow group_task_id.</p>';
} else {
    // CHECK constraints may not be supported — this is non-critical
    echo '<p class="warn">⚠ Could not add CHECK constraint (may not be supported): ' . htmlspecialchars($conn->error) . '</p>';
    echo '<p class="warn">   This is non-critical — uploads will still work.</p>';
}

// Step 5: Verify uploads directory is writable
$upload_dir = __DIR__ . '/uploads/attachments/';
echo '<hr><h2>📁 Upload Directory Check</h2>';
if (is_dir($upload_dir)) {
    echo '<p class="ok">✅ Directory exists: ' . htmlspecialchars($upload_dir) . '</p>';
    if (is_writable($upload_dir)) {
        echo '<p class="ok">✅ Directory is writable.</p>';
    } else {
        echo '<p class="err">❌ Directory is NOT writable! Uploads will fail.</p>';
        echo '<p class="warn">   Fix: chmod 775 uploads/attachments/</p>';
    }
} else {
    echo '<p class="err">❌ Directory does NOT exist!</p>';
    if (@mkdir($upload_dir, 0775, true)) {
        echo '<p class="ok">✅ Created directory successfully.</p>';
    } else {
        echo '<p class="err">❌ Failed to create directory — filesystem may be read-only.</p>';
    }
}

echo '<hr>';
echo '<p class="ok" style="font-size:1.2em;">✅ Migration complete!</p>';
echo '<p><a href="admin/admin_dashboard.php" style="color:#0ff;">← Back to Admin Dashboard</a></p>';
echo '</body></html>';

$conn->close();
?>
