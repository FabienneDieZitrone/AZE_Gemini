-- Schema Update: Support for running time entries
-- Allows stop_time to be NULL for ongoing time tracking

-- 1. Modify time_entries table to allow NULL stop_time
ALTER TABLE `time_entries` 
MODIFY `stop_time` time NULL;

-- 2. Add status column for tracking entry state
ALTER TABLE `time_entries` 
ADD COLUMN `status` enum('completed', 'running') NOT NULL DEFAULT 'completed';

-- 3. Add index for finding running entries efficiently
ALTER TABLE `time_entries`
ADD INDEX `idx_running_entries` (`user_id`, `status`, `date`);