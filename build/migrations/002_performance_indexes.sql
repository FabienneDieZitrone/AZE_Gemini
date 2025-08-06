-- Database Performance Optimization Indexes
-- File: /migrations/002_performance_indexes.sql
-- Purpose: Add indexes to optimize common query patterns and pagination performance
-- Author: Database Performance Expert
-- Date: 2025-08-06

-- Index for time_entries pagination and filtering
-- Improves ORDER BY date DESC, start_time DESC performance
ALTER TABLE `time_entries` 
ADD INDEX `idx_date_start_time` (`date` DESC, `start_time` DESC);

-- Index for time_entries user filtering with date ordering
-- Optimizes queries filtering by user_id and ordering by date
ALTER TABLE `time_entries` 
ADD INDEX `idx_user_date_start` (`user_id`, `date` DESC, `start_time` DESC);

-- Index for time_entries location filtering with date ordering
-- Optimizes queries filtering by location (for Standortleiter role)
ALTER TABLE `time_entries` 
ADD INDEX `idx_location_date_start` (`location`, `date` DESC, `start_time` DESC);

-- Index for time_entries to find running timers (stop_time IS NULL)
-- Optimizes the check_running_timer functionality
ALTER TABLE `time_entries` 
ADD INDEX `idx_user_stop_time_created` (`user_id`, `stop_time`, `created_at` DESC);

-- Index for approval_requests status filtering and ordering
-- Improves pagination performance for approvals and history endpoints
ALTER TABLE `approval_requests` 
ADD INDEX `idx_status_requested_at` (`status`, `requested_at` DESC);

-- Index for approval_requests filtering by requested_by
-- Optimizes queries for users viewing their own requests/history
ALTER TABLE `approval_requests` 
ADD INDEX `idx_requested_by_status_date` (`requested_by`, `status`, `requested_at` DESC);

-- Index for approval_requests entry_id foreign key lookups
-- Optimizes JOIN operations between approval_requests and time_entries
-- Note: This may already exist as a foreign key index, but we ensure it's optimal
ALTER TABLE `approval_requests` 
ADD INDEX `idx_entry_id_status` (`entry_id`, `status`);

-- Composite index for approval_requests resolved status queries
-- Optimizes history endpoint queries (status != 'pending')
ALTER TABLE `approval_requests` 
ADD INDEX `idx_resolved_status_date` (`status`, `resolved_at` DESC);

-- Index for users table for pagination and role-based filtering
-- Optimizes users endpoint pagination and role filtering
ALTER TABLE `users` 
ADD INDEX `idx_role_display_name` (`role`, `display_name` ASC);

-- Index for users table display_name ordering
-- Optimizes ORDER BY display_name ASC in users endpoint
ALTER TABLE `users` 
ADD INDEX `idx_display_name` (`display_name` ASC);

-- Optimize JSON column searches for location-based filtering
-- Note: JSON indexes are not directly supported in older MySQL versions
-- These queries use JSON_EXTRACT which can be slow, consider denormalizing location data

-- Add comment about performance monitoring
-- Use EXPLAIN SELECT to monitor query performance after applying these indexes
-- Monitor slow query log to identify additional optimization opportunities

COMMIT;