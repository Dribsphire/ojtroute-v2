-- Workplace timezone for students on OJT outside the Philippines.
--
-- schedule_start_time / schedule_end_time are read as wall-clock times in this
-- timezone. attendance_records.time_in / time_out stay stored in Philippine time
-- (Asia/Manila), so existing records and reports are unaffected.
--
-- Safe to run more than once (MariaDB / XAMPP supports IF NOT EXISTS).

ALTER TABLE `student_workplaces`
  ADD COLUMN IF NOT EXISTS `timezone` VARCHAR(64) NOT NULL DEFAULT 'Asia/Manila'
  COMMENT 'IANA timezone of the workplace, e.g. Asia/Manila, Asia/Tokyo'
  AFTER `schedule_end_time`;
