<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only');
}

require_once 'config/db.php';

echo "Running buddy enhancements migration...\n\n";

$queries = [
    "Weekly Goals Table" => "CREATE TABLE IF NOT EXISTS buddy_weekly_goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        target_tasks INT NOT NULL DEFAULT 5,
        week_start DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_week (user_id, week_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "Daily Check-ins Table" => "CREATE TABLE IF NOT EXISTS buddy_checkins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        check_date DATE NOT NULL,
        completed TINYINT(1) NOT NULL DEFAULT 0,
        note VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_date (user_id, check_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "Scheduled Nudges Table" => "CREATE TABLE IF NOT EXISTS buddy_scheduled_nudges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        day_of_week TINYINT NOT NULL,
        nudge_time TIME NOT NULL,
        message VARCHAR(500) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_sent_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_active_day (is_active, day_of_week)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($queries as $name => $sql) {
    if ($conn->query($sql)) {
        echo "[OK] $name\n";
    } else {
        echo "[ERROR] $name: " . $conn->error . "\n";
    }
}

// Add columns to study_buddies if not exist
$check = $conn->query("SHOW COLUMNS FROM study_buddies LIKE 'pair_streak'");
if ($check->num_rows === 0) {
    if ($conn->query("ALTER TABLE study_buddies ADD COLUMN pair_streak INT NOT NULL DEFAULT 0")) {
        echo "[OK] Added pair_streak column\n";
    } else {
        echo "[ERROR] pair_streak: " . $conn->error . "\n";
    }
}

$check = $conn->query("SHOW COLUMNS FROM study_buddies LIKE 'pair_streak_updated'");
if ($check->num_rows === 0) {
    if ($conn->query("ALTER TABLE study_buddies ADD COLUMN pair_streak_updated DATE DEFAULT NULL")) {
        echo "[OK] Added pair_streak_updated column\n";
    } else {
        echo "[ERROR] pair_streak_updated: " . $conn->error . "\n";
    }
}

echo "\nMigration complete!\n";
