-- Unpaid break (e.g. 1-hour lunch) that does not count toward DTR hours.
-- Safe to run more than once (MariaDB / XAMPP syntax).

-- The student's break, set with their working schedule (workplace local time).
ALTER TABLE `student_workplaces`
  ADD COLUMN IF NOT EXISTS `break_start_time` TIME DEFAULT NULL
    COMMENT 'Unpaid break start (workplace local time); NULL = no break'
    AFTER `schedule_end_time`,
  ADD COLUMN IF NOT EXISTS `break_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Unpaid break length in minutes'
    AFTER `break_start_time`;

-- The break actually deducted from this record (Philippine time, like time_in/time_out).
-- Saved at time-out so the DTR stays correct even if the schedule changes later.
ALTER TABLE `attendance_records`
  ADD COLUMN IF NOT EXISTS `break_start` DATETIME DEFAULT NULL
    COMMENT 'Start of break deducted from hours (NULL = none)'
    AFTER `time_out`,
  ADD COLUMN IF NOT EXISTS `break_end` DATETIME DEFAULT NULL
    COMMENT 'End of break deducted from hours'
    AFTER `break_start`;
