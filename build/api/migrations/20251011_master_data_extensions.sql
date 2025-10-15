-- Migration: Extend master_data with locations, flexible_workdays, daily_hours
-- Date: 2025-10-11
-- Safe to run multiple times

ALTER TABLE `master_data`
  ADD COLUMN IF NOT EXISTS `locations` JSON NULL AFTER `workdays`;

ALTER TABLE `master_data`
  ADD COLUMN IF NOT EXISTS `flexible_workdays` TINYINT(1) NOT NULL DEFAULT 0 AFTER `locations`;

ALTER TABLE `master_data`
  ADD COLUMN IF NOT EXISTS `daily_hours` JSON NULL AFTER `flexible_workdays`;

