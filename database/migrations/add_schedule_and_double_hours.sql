-- Schema the code uses that was missing from database/ojt_db.sql.
-- Your live database most likely has these already; every statement is safe
-- to re-run (MariaDB / XAMPP syntax).

-- Working schedule per workplace (set by the student in their profile).
-- Read in the workplace's timezone (see add_workplace_timezone.sql).
ALTER TABLE `student_workplaces`
  ADD COLUMN IF NOT EXISTS `schedule_start_time` TIME DEFAULT NULL COMMENT 'Shift start (workplace local time)',
  ADD COLUMN IF NOT EXISTS `schedule_end_time` TIME DEFAULT NULL COMMENT 'Shift end; earlier than start = night shift ending next day';

-- Current system uses one 'regular' block per shift (+ overtime).
-- morning/afternoon are kept for old records.
ALTER TABLE `attendance_records`
  MODIFY `block_type` ENUM('morning','afternoon','overtime','regular') NOT NULL
  COMMENT 'regular = scheduled shift, overtime = up to 4h after shift end; morning/afternoon = legacy records';

-- Dates where hours count double (managed by admin).
CREATE TABLE IF NOT EXISTS `double_hours_dates` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL COMMENT 'FK to users (admin)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_double_hours_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
