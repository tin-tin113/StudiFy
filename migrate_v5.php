<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only');
}

require_once 'config/db.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS dashboard_widgets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        widget_key VARCHAR(50) NOT NULL,
        position INT DEFAULT 0,
        is_visible TINYINT(1) DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_widget (user_id, widget_key),
        INDEX idx_user_id (user_id)
    )",
    "CREATE TABLE IF NOT EXISTS user_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        achievement_key VARCHAR(100) NOT NULL,
        unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_achievement (user_id, achievement_key),
        INDEX idx_user_id (user_id)
    )"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "OK: Table created\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
    }
}
echo "Migration v5.0 complete!\n";
