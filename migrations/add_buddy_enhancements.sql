-- Migration: Enhanced Study Buddy Features
-- Features: Weekly Goals, Check-ins, Buddy Streak, Scheduled Nudges

-- ============================================
-- 1. WEEKLY GOALS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS buddy_weekly_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_tasks INT NOT NULL DEFAULT 5,
    week_start DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_week (user_id, week_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. DAILY CHECK-INS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS buddy_checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    check_date DATE NOT NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, check_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. SCHEDULED NUDGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS buddy_scheduled_nudges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
    nudge_time TIME NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_active_day (is_active, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. BUDDY PAIR STREAK TRACKING
-- Add column to study_buddies table
-- ============================================
ALTER TABLE study_buddies
ADD COLUMN IF NOT EXISTS pair_streak INT NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS pair_streak_updated DATE DEFAULT NULL;

-- ============================================
-- 5. INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX IF NOT EXISTS idx_checkins_date ON buddy_checkins(check_date);
CREATE INDEX IF NOT EXISTS idx_goals_week ON buddy_weekly_goals(week_start);
