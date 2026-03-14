<?php
/**
 * Run Task Templates Migration
 * Execute this file to add the task_templates table
 */

require_once 'config/db.php';

echo "Running Task Templates Migration...\n\n";

// Read migration file
$migration_file = __DIR__ . '/migrations/add_task_templates.sql';
if (!file_exists($migration_file)) {
    die("Error: Migration file not found: $migration_file\n");
}

$sql = file_get_contents($migration_file);

// Split by semicolon to execute statements separately
$statements = array_filter(array_map('trim', explode(';', $sql)));

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue; // Skip comments and empty lines
    }
    
    // Remove comments
    $statement = preg_replace('/--.*$/m', '', $statement);
    $statement = trim($statement);
    
    if (empty($statement)) {
        continue;
    }
    
    if ($conn->query($statement)) {
        $success_count++;
        echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
    } else {
        $error_count++;
        echo "✗ Error: " . $conn->error . "\n";
        echo "  Statement: " . substr($statement, 0, 100) . "...\n";
    }
}

echo "\n";
echo "Migration Complete!\n";
echo "Successful: $success_count\n";
echo "Errors: $error_count\n";

if ($error_count === 0) {
    echo "\n✓ Task templates table created successfully!\n";
    echo "You can now use task templates in the system.\n";
} else {
    echo "\n⚠ Some errors occurred. Please check the output above.\n";
}

$conn->close();
?>
