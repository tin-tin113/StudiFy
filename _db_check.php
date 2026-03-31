<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only');
}

require_once 'config/db.php';

echo "=== Attachments Table Structure ===\n";
$result = $conn->query("SHOW CREATE TABLE attachments");
$row = $result->fetch_assoc();
echo $row['Create Table'] . "\n\n";

echo "=== Column Defaults ===\n";
$result = $conn->query("SHOW COLUMNS FROM attachments");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . ": Default=" . var_export($row['Default'], true) . ", Null=" . $row['Null'] . "\n";
}
