-- =============================================
-- Studify Performance Indexes Migration
-- Run once to add composite indexes that optimize
-- the most frequently executed queries.
-- =============================================

-- CRITICAL: Covers 12+ task listing/counting queries on every page load
CREATE INDEX idx_tasks_user_parent_status_deadline
ON tasks (user_id, parent_id, status, deadline);

-- CRITICAL: Covers group chat polling (every 2s per user per group)
CREATE INDEX idx_group_messages_group_id_id
ON group_messages (group_id, id);

-- HIGH: Covers all study_sessions analytics, streaks, dashboard, gamification
CREATE INDEX idx_sessions_user_type_created
ON study_sessions (user_id, session_type, created_at);

-- HIGH: Covers weekly completion stats, streak calculations, buddy progress
CREATE INDEX idx_tasks_user_status_updated
ON tasks (user_id, status, updated_at);

-- HIGH: Covers reverse direction of bidirectional buddy chat (polled every 2s)
CREATE INDEX idx_buddy_messages_receiver_sender_created
ON buddy_messages (receiver_id, sender_id, created_at);

-- MEDIUM: Covers active semester lookup (every page load)
CREATE INDEX idx_semesters_user_active
ON semesters (user_id, is_active);

-- MEDIUM: Covers group member progress stats
CREATE INDEX idx_group_tasks_group_assignee_status
ON group_tasks (group_id, assigned_to, status);

-- MEDIUM: Covers group task listing with sort
CREATE INDEX idx_group_tasks_group_status_deadline
ON group_tasks (group_id, status, deadline);

-- MEDIUM: Covers unread nudge count and mark-read
CREATE INDEX idx_nudges_receiver_read
ON buddy_nudges (receiver_id, is_read);

-- MEDIUM: Covers group chat rate limiting
CREATE INDEX idx_group_messages_sender_group_created
ON group_messages (sender_id, group_id, created_at);

-- MEDIUM: Covers notification listing with pagination
CREATE INDEX idx_notifications_user_dismissed_created
ON notifications (user_id, is_dismissed, created_at);

-- MEDIUM: Covers notification dedup in notification_checker.php
CREATE INDEX idx_notifications_dedup
ON notifications (user_id, type, reference_type, reference_id);

-- FULLTEXT: Enables fast global search without leading-wildcard LIKE scans
ALTER TABLE tasks ADD FULLTEXT INDEX ft_tasks_search (title, description);
ALTER TABLE notes ADD FULLTEXT INDEX ft_notes_search (title, content);
ALTER TABLE subjects ADD FULLTEXT INDEX ft_subjects_search (name, instructor_name);

-- Drop redundant single-column indexes now covered by composites
DROP INDEX idx_is_active ON semesters;
DROP INDEX idx_is_read ON buddy_nudges;
