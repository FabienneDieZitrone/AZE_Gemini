-- Migration: enable 'create' approval type
-- Safe to re-run: checks are performed in PHP runner

ALTER TABLE `approval_requests`
  MODIFY COLUMN `type` ENUM('edit','delete','create') NOT NULL;

