-- Migration: Add group_task_id column to attachments table
-- This allows file attachments on group tasks

-- Add the column
ALTER TABLE attachments ADD COLUMN group_task_id INT DEFAULT NULL AFTER note_id;

-- Add foreign key
ALTER TABLE attachments ADD CONSTRAINT fk_attachments_group_task FOREIGN KEY (group_task_id) REFERENCES group_tasks(id) ON DELETE CASCADE;

-- Add index
ALTER TABLE attachments ADD INDEX idx_group_task_id (group_task_id);

-- Update CHECK constraint to allow group_task_id as a valid target
-- (Only one of task_id, note_id, or group_task_id can be set)
ALTER TABLE attachments DROP CONSTRAINT IF EXISTS chk_attachments_exactly_one_target;
ALTER TABLE attachments ADD CONSTRAINT chk_attachments_exactly_one_target
    CHECK (
        (task_id IS NOT NULL AND note_id IS NULL AND group_task_id IS NULL) OR
        (task_id IS NULL AND note_id IS NOT NULL AND group_task_id IS NULL) OR
        (task_id IS NULL AND note_id IS NULL AND group_task_id IS NOT NULL)
    );
