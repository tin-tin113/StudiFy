<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only');
}

require_once 'config/db.php';

echo "Updating CHECK constraint to include group_task_id...\n";

// Drop old constraint
$conn->query("ALTER TABLE attachments DROP CONSTRAINT chk_attachments_exactly_one_target");
echo "Old constraint dropped.\n";

// Add new constraint that allows exactly one of three targets
$sql = "ALTER TABLE attachments ADD CONSTRAINT chk_attachments_exactly_one_target
        CHECK (
            (task_id IS NOT NULL AND note_id IS NULL AND group_task_id IS NULL) OR
            (task_id IS NULL AND note_id IS NOT NULL AND group_task_id IS NULL) OR
            (task_id IS NULL AND note_id IS NULL AND group_task_id IS NOT NULL)
        )";

if ($conn->query($sql)) {
    echo "New constraint added successfully!\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\nVerifying new constraint:\n";
$result = $conn->query("SHOW CREATE TABLE attachments");
$row = $result->fetch_assoc();
if (preg_match('/CONSTRAINT.*chk_attachments.*CHECK.*\(.*\)/s', $row['Create Table'], $match)) {
    echo $match[0] . "\n";
}
