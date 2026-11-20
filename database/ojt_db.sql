-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ojt_db_test
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `attendance_records`
--

DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_records` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) unsigned NOT NULL COMMENT 'FK to students',
  `attendance_date` date NOT NULL,
  `block_type` enum('morning','afternoon','overtime','regular') NOT NULL COMMENT 'regular = scheduled shift, overtime = up to 4h after shift end; morning/afternoon = legacy records',
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL COMMENT 'Nullable if student forgot to time out',
  `break_start` datetime DEFAULT NULL COMMENT 'Start of break deducted from hours (NULL = none)',
  `break_end` datetime DEFAULT NULL COMMENT 'End of break deducted from hours',
  `hours` decimal(5,2) DEFAULT NULL COMMENT 'Calculated hours (derived from time_in/time_out)',
  `time_in_latitude` decimal(10,8) NOT NULL COMMENT 'GPS latitude when student timed in',
  `time_in_longitude` decimal(11,8) NOT NULL COMMENT 'GPS longitude when student timed in',
  `within_radius` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether student was within allowed radius (40 meters) of workplace',
  `photo_path` varchar(500) NOT NULL COMMENT 'Path to captured photo at time-in',
  `status` enum('ongoing','completed','pending_exception') NOT NULL DEFAULT 'ongoing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `forgot_timeout_reason` text DEFAULT NULL,
  `forgot_timeout_file` varchar(500) DEFAULT NULL,
  `instructor_response` text DEFAULT NULL,
  `request_status` enum('pending','approved','rejected') DEFAULT NULL,
  `missing_timeout_flagged_at` datetime DEFAULT NULL COMMENT 'When the system flagged this as missing timeout',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`student_id`,`attendance_date`,`block_type`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_block_type` (`block_type`),
  KEY `idx_status` (`status`),
  KEY `idx_time_in` (`time_in`),
  KEY `idx_attendance_student_date` (`student_id`,`attendance_date`),
  KEY `idx_request_status` (`request_status`),
  KEY `idx_attendance_student_date_block` (`student_id`,`attendance_date`,`block_type`),
  KEY `idx_attendance_date_block_status` (`attendance_date`,`block_type`,`status`),
  KEY `idx_attendance_created_at` (`created_at`),
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90015 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_records`
--

LOCK TABLES `attendance_records` WRITE;
/*!40000 ALTER TABLE `attendance_records` DISABLE KEYS */;
INSERT INTO `attendance_records` VALUES (90001,911,'2026-11-02','regular','2026-11-02 07:55:00','2026-11-02 17:02:00','2026-11-02 12:00:00','2026-11-02 13:00:00',8.12,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90002,911,'2026-11-03','regular','2026-11-03 08:31:00','2026-11-03 17:00:00','2026-11-03 12:00:00','2026-11-03 13:00:00',7.48,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90003,911,'2026-11-04','regular','2026-11-04 07:58:00','2026-11-04 17:00:00','2026-11-04 12:00:00','2026-11-04 13:00:00',8.03,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90004,911,'2026-11-04','overtime','2026-11-04 17:05:00','2026-11-04 19:30:00',NULL,NULL,2.42,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90005,911,'2026-11-06','regular','2026-11-06 13:00:00','2026-11-06 17:00:00',NULL,NULL,4.00,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90006,912,'2026-11-02','regular','2026-11-02 21:58:00','2026-11-03 06:01:00','2026-11-03 02:00:00','2026-11-03 03:00:00',7.05,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90007,912,'2026-11-03','regular','2026-11-03 22:40:00','2026-11-04 06:00:00','2026-11-04 02:00:00','2026-11-04 03:00:00',6.33,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90008,912,'2026-11-04','regular','2026-11-05 00:15:00','2026-11-05 06:00:00','2026-11-05 02:00:00','2026-11-05 03:00:00',4.75,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90009,912,'2026-11-04','overtime','2026-11-05 06:05:00','2026-11-05 08:00:00',NULL,NULL,1.92,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90010,912,'2026-11-09','regular','2026-11-09 22:00:00',NULL,NULL,NULL,NULL,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','pending_exception','2026-11-20 05:50:43','2026-11-20 05:50:43','Phone died, forgot to time out (test)',NULL,NULL,'pending','2026-11-10 07:00:00'),(90011,913,'2026-11-02','regular','2026-11-02 07:57:00','2026-11-02 17:03:00','2026-11-02 11:00:00','2026-11-02 12:00:00',8.10,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90012,913,'2026-11-03','regular','2026-11-03 08:12:00','2026-11-03 17:00:00','2026-11-03 11:00:00','2026-11-03 12:00:00',7.80,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90013,914,'2026-11-02','regular','2026-11-03 09:59:00','2026-11-03 18:00:00',NULL,NULL,8.02,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL),(90014,914,'2026-11-03','regular','2026-11-04 10:20:00','2026-11-04 18:02:00',NULL,NULL,7.70,0.00000000,0.00000000,1,'../images/attendance-placeholder.jpg','completed','2026-11-20 05:50:43','2026-11-20 05:50:43',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `attendance_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_submissions`
--

DROP TABLE IF EXISTS `document_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_submissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) unsigned NOT NULL COMMENT 'FK to students',
  `document_type_id` int(11) unsigned NOT NULL COMMENT 'FK to document_types',
  `file_path` varchar(500) NOT NULL COMMENT 'Path to uploaded file',
  `file_type` varchar(50) DEFAULT NULL COMMENT 'File extension/mime type',
  `file_size_bytes` bigint(20) unsigned DEFAULT NULL COMMENT 'File size in bytes',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','revise','rejected') NOT NULL DEFAULT 'pending',
  `reviewer_instructor_id` int(11) unsigned DEFAULT NULL COMMENT 'FK to instructors - nullable until reviewed',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `feedback` text DEFAULT NULL COMMENT 'Instructor remarks/feedback',
  `points` decimal(5,2) DEFAULT NULL COMMENT 'Bonus points awarded by instructor for document quality',
  `accuracyQualityPoints` decimal(5,2) DEFAULT NULL,
  `professionalPresentationPoints` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_document_type_id` (`document_type_id`),
  KEY `idx_status` (`status`),
  KEY `idx_reviewer_instructor_id` (`reviewer_instructor_id`),
  KEY `idx_submitted_at` (`submitted_at`),
  KEY `idx_document_student_type` (`student_id`,`document_type_id`),
  KEY `idx_document_student_status` (`student_id`,`status`),
  CONSTRAINT `fk_submissions_document_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_instructor` FOREIGN KEY (`reviewer_instructor_id`) REFERENCES `instructors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=973 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_submissions`
--

LOCK TABLES `document_submissions` WRITE;
/*!40000 ALTER TABLE `document_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_types`
--

DROP TABLE IF EXISTS `document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_types` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL COMMENT 'Document name (e.g., "Memorandum of Agreement (MOA)")',
  `code` varchar(50) DEFAULT NULL COMMENT 'Short key (e.g., "MOA", "Application letter")',
  `category` enum('pre_required','weekly','monthly','excuse','other') NOT NULL DEFAULT 'other',
  `is_pre_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Must be approved before OJT attendance can start',
  `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this is a mandatory requirement',
  `frequency` enum('once','weekly','monthly','per_incident') NOT NULL DEFAULT 'once',
  `description` text DEFAULT NULL,
  `template_path` varchar(255) DEFAULT NULL COMMENT 'Path to template file',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deadline` date DEFAULT NULL COMMENT 'Submission deadline for this document type',
  `instructor_id` int(10) unsigned DEFAULT NULL COMMENT 'ID of the instructor who created this document type',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code_per_instructor` (`code`,`instructor_id`),
  KEY `idx_category` (`category`),
  KEY `idx_is_pre_required` (`is_pre_required`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_document_types_instructor_id` (`instructor_id`),
  CONSTRAINT `fk_document_types_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_types`
--

LOCK TABLES `document_types` WRITE;
/*!40000 ALTER TABLE `document_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `double_hours_dates`
--

DROP TABLE IF EXISTS `double_hours_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `double_hours_dates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `created_by` int(11) unsigned DEFAULT NULL COMMENT 'FK to users (admin)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_double_hours_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `double_hours_dates`
--

LOCK TABLES `double_hours_dates` WRITE;
/*!40000 ALTER TABLE `double_hours_dates` DISABLE KEYS */;
/*!40000 ALTER TABLE `double_hours_dates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) unsigned NOT NULL COMMENT 'FK to users (admin role)',
  `recipient_scope` enum('all_students','all_instructors','specific_student') NOT NULL,
  `student_id` int(11) unsigned DEFAULT NULL COMMENT 'FK to students - used when recipient_scope = specific_student',
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_recipient_scope` (`recipient_scope`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_sent_at` (`sent_at`),
  CONSTRAINT `fk_email_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_email_logs_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_logs`
--

LOCK TABLES `email_logs` WRITE;
/*!40000 ALTER TABLE `email_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `excused_dates`
--

DROP TABLE IF EXISTS `excused_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `excused_dates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) unsigned NOT NULL COMMENT 'FK to students table',
  `excused_date` date NOT NULL COMMENT 'The date marked as excused',
  `instructor_id` int(11) unsigned NOT NULL COMMENT 'FK to instructors table',
  `hours_added` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Hours credited for this excused date',
  `reason` text NOT NULL COMMENT 'Reason for the excused absence',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When this record was created',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_date` (`student_id`,`excused_date`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_excused_date` (`excused_date`),
  KEY `idx_instructor_id` (`instructor_id`),
  KEY `idx_date_student` (`excused_date`,`student_id`),
  CONSTRAINT `fk_excused_dates_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_excused_dates_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks excused dates for students with hours credited by instructors';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `excused_dates`
--

LOCK TABLES `excused_dates` WRITE;
/*!40000 ALTER TABLE `excused_dates` DISABLE KEYS */;
INSERT INTO `excused_dates` VALUES (76,911,'2026-11-05',0,8.00,'University event (test)','2026-11-20 05:50:43');
/*!40000 ALTER TABLE `excused_dates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `instructors`
--

DROP TABLE IF EXISTS `instructors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instructors` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT 'FK to users table',
  `department` varchar(100) DEFAULT NULL COMMENT 'Department/College',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_id` (`user_id`),
  KEY `idx_department` (`department`),
  CONSTRAINT `fk_instructors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=903 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instructors`
--

LOCK TABLES `instructors` WRITE;
/*!40000 ALTER TABLE `instructors` DISABLE KEYS */;
INSERT INTO `instructors` VALUES (902,9002,'College of Computer Studies','2026-11-20 05:50:43','2026-11-20 05:50:43');
/*!40000 ALTER TABLE `instructors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ojt_summaries`
--

DROP TABLE IF EXISTS `ojt_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ojt_summaries` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) unsigned NOT NULL COMMENT 'FK to students',
  `hours_completed` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of attendance hours + manual adjustments',
  `hours_required` decimal(6,2) NOT NULL DEFAULT 600.00 COMMENT 'Target OJT hours',
  `manual_adjustment_hours` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Instructor-added hours (e.g., +8 for school excuses)',
  `adjusted_by_instructor_id` int(11) unsigned DEFAULT NULL COMMENT 'FK to instructors - who made the adjustment',
  `adjustment_reason` text DEFAULT NULL COMMENT 'Reason for manual adjustment (e.g., "School activity", "Bulk adjustment")',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_id` (`student_id`),
  KEY `idx_adjusted_by_instructor_id` (`adjusted_by_instructor_id`),
  KEY `idx_last_updated` (`last_updated`),
  KEY `idx_ojt_summaries_student` (`student_id`),
  CONSTRAINT `fk_summaries_instructor` FOREIGN KEY (`adjusted_by_instructor_id`) REFERENCES `instructors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_summaries_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=916 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ojt_summaries`
--

LOCK TABLES `ojt_summaries` WRITE;
/*!40000 ALTER TABLE `ojt_summaries` DISABLE KEYS */;
INSERT INTO `ojt_summaries` VALUES (911,911,0.00,600.00,0.00,NULL,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(912,912,0.00,600.00,0.00,NULL,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(913,913,0.00,600.00,0.00,NULL,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(914,914,0.00,600.00,0.00,NULL,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(915,915,0.00,600.00,0.00,NULL,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43');
/*!40000 ALTER TABLE `ojt_summaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sections`
--

DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sections` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `section_code` varchar(10) NOT NULL COMMENT 'Short code like "4A", "4B", "4C"',
  `section_name` varchar(50) NOT NULL COMMENT 'Full name like "BSIT-4A", "BSIT-4B"',
  `department` varchar(100) NOT NULL COMMENT 'College of Computer Studies, College of Education, etc.',
  `instructor_id` int(11) unsigned DEFAULT NULL COMMENT 'Assigned instructor/supervisor (FK to instructors)',
  `year` varchar(10) NOT NULL COMMENT 'Academic year e.g., "2025"',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this section is currently active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_section` (`section_code`,`department`,`year`),
  KEY `idx_department` (`department`),
  KEY `idx_year` (`year`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_sections_instructor` (`instructor_id`),
  CONSTRAINT `fk_sections_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=902 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sections`
--

LOCK TABLES `sections` WRITE;
/*!40000 ALTER TABLE `sections` DISABLE KEYS */;
INSERT INTO `sections` VALUES (901,'4B','BSIT-4B','College of Computer Studies',902,'2026',1,'2026-11-20 05:50:43','2026-11-20 05:50:43');
/*!40000 ALTER TABLE `sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_workplaces`
--

DROP TABLE IF EXISTS `student_workplaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_workplaces` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) unsigned NOT NULL COMMENT 'FK to students',
  `company_name` varchar(200) NOT NULL,
  `company_head` varchar(100) DEFAULT NULL COMMENT 'Company head/supervisor name',
  `company_address` varchar(255) NOT NULL,
  `position_title` varchar(100) DEFAULT NULL COMMENT 'Student role (e.g., Software Development Intern)',
  `workplace_latitude` decimal(10,8) NOT NULL COMMENT 'GPS latitude for radius verification',
  `workplace_longitude` decimal(11,8) NOT NULL COMMENT 'GPS longitude for radius verification',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'Nullable if currently active',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Active placement flag',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `supervisor_position` varchar(255) NOT NULL,
  `head_trainee` varchar(255) NOT NULL,
  `head_trainee_position` varchar(255) NOT NULL,
  `head_trainee_contact` varchar(255) NOT NULL,
  `head_trainee_email` varchar(255) NOT NULL,
  `schedule_start_time` time DEFAULT NULL COMMENT 'Shift start (workplace local time)',
  `schedule_end_time` time DEFAULT NULL COMMENT 'Shift end; earlier than start = night shift ending next day',
  `break_start_time` time DEFAULT NULL COMMENT 'Unpaid break start (workplace local time); NULL = no break',
  `break_minutes` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Unpaid break length in minutes',
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Manila' COMMENT 'IANA timezone of the workplace, e.g. Asia/Manila, Asia/Tokyo',
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_student_workplaces_student_active` (`student_id`,`is_active`),
  CONSTRAINT `fk_workplaces_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=916 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_workplaces`
--

LOCK TABLES `student_workplaces` WRITE;
/*!40000 ALTER TABLE `student_workplaces` DISABLE KEYS */;
INSERT INTO `student_workplaces` VALUES (911,911,'Day Shift Test Corp (Bacolod)','Sam Supervisor','Day Shift Test Corp (Bacolod) address','OJT Trainee',10.66880000,122.94850000,'2026-11-02',NULL,1,'2026-11-20 05:50:43','2026-11-20 05:50:43','Manager','Sam Supervisor','Manager','09170000000','supervisor@test.local','08:00:00','17:00:00','12:00:00',60,'Asia/Manila'),(912,912,'Night Shift BPO Test (Bacolod)','Sam Supervisor','Night Shift BPO Test (Bacolod) address','OJT Trainee',10.66902000,122.94840000,'2026-11-02',NULL,1,'2026-11-20 05:50:43','2026-11-20 05:50:43','Manager','Sam Supervisor','Manager','09170000000','supervisor@test.local','22:00:00','06:00:00','02:00:00',60,'Asia/Manila'),(913,913,'Tokyo Test KK (Japan)','Sam Supervisor','Tokyo Test KK (Japan) address','OJT Trainee',35.68120000,139.76710000,'2026-11-02',NULL,1,'2026-11-20 05:50:43','2026-11-20 05:50:43','Manager','Sam Supervisor','Manager','09170000000','supervisor@test.local','09:00:00','18:00:00','12:00:00',60,'Asia/Tokyo'),(914,914,'New York Test LLC (USA)','Sam Supervisor','New York Test LLC (USA) address','OJT Trainee',40.75800000,-73.98550000,'2026-11-02',NULL,1,'2026-11-20 05:50:43','2026-11-20 05:50:43','Manager','Sam Supervisor','Manager','09170000000','supervisor@test.local','21:00:00','05:00:00',NULL,0,'America/New_York'),(915,915,'No Schedule Test Inc (Bacolod)','Sam Supervisor','No Schedule Test Inc (Bacolod) address','OJT Trainee',10.64009000,122.93498000,'2026-11-02',NULL,1,'2026-11-20 05:50:43','2026-11-20 05:50:43','Manager','Sam Supervisor','Manager','09170000000','supervisor@test.local',NULL,NULL,NULL,0,'Asia/Manila');
/*!40000 ALTER TABLE `student_workplaces` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT 'FK to users table',
  `department` varchar(100) DEFAULT NULL COMMENT 'Can be derived from sections but kept for convenience',
  `target_ojt_hours` decimal(6,2) NOT NULL DEFAULT 600.00 COMMENT 'Target OJT hours (e.g., 600)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_id` (`user_id`),
  KEY `idx_department` (`department`),
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=916 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (911,9011,'College of Computer Studies',600.00,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(912,9012,'College of Computer Studies',600.00,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(913,9013,'College of Computer Studies',600.00,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(914,9014,'College of Computer Studies',600.00,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(915,9015,'College of Computer Studies',600.00,'2026-11-20 05:50:43','2026-11-20 05:50:43');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` varchar(20) NOT NULL COMMENT 'School/Employee ID used in CSV & listings',
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL COMMENT 'Hashed password (bcrypt/argon2)',
  `role` enum('admin','student','instructor') NOT NULL,
  `gender` enum('male','female','non-binary') DEFAULT NULL,
  `section_id` int(11) unsigned DEFAULT NULL COMMENT 'FK to sections - only for students',
  `contact` varchar(20) DEFAULT NULL COMMENT 'Contact number',
  `facebook_name` varchar(100) DEFAULT NULL COMMENT 'Facebook name/username',
  `year` varchar(10) DEFAULT NULL COMMENT 'Academic year',
  `profile_pic_path` varchar(255) DEFAULT NULL COMMENT 'Path to profile picture',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'For archive-by-year feature',
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_school_id` (`school_id`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_section_id` (`section_id`),
  KEY `idx_year` (`year`),
  KEY `idx_is_archived` (`is_archived`),
  KEY `idx_users_section_role` (`section_id`,`role`,`is_archived`),
  CONSTRAINT `fk_users_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9016 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (9001,'TEST-ADMIN','Test Admin','admin@test.local','$2y$10$47TEmEElICH2FHOHTmibb.hGOfedPIXXwTsH2o/LykOCORt.hIaGq','admin',NULL,NULL,NULL,NULL,'2026',NULL,0,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(9002,'TEST-INST','Ines T. Instructor','inst@test.local','$2y$10$47TEmEElICH2FHOHTmibb.hGOfedPIXXwTsH2o/LykOCORt.hIaGq','instructor',NULL,NULL,NULL,NULL,'2026',NULL,0,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(9011,'TEST-DAY','JUAN D. DELA CRUZ','day@test.local','$2y$10$47TEmEElICH2FHOHTmibb.hGOfedPIXXwTsH2o/LykOCORt.hIaGq','student',NULL,901,NULL,NULL,'2026',NULL,0,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(9012,'TEST-NIGHT','MARIA S. SANTOS','night@test.local','$2y$10$47TEmEElICH2FHOHTmibb.hGOfedPIXXwTsH2o/LykOCORt.hIaGq','student',NULL,901,NULL,NULL,'2026',NULL,0,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(9013,'TEST-TOKYO','PEDRO R. REYES','tokyo@test.local','$2y$10$47TEmEElICH2FHOHTmibb.hGOfedPIXXwTsH2o/LykOCORt.hIaGq','student',NULL,901,NULL,NULL,'2026',NULL,0,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(9014,'TEST-NY','ALEX S. AMAR, JR','ny@test.local','$2y$10$47TEmEElICH2FHOHTmibb.hGOfedPIXXwTsH2o/LykOCORt.hIaGq','student',NULL,901,NULL,NULL,'2026',NULL,0,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43'),(9015,'TEST-NOSCHED','LUZ M. NOSCHEDULE','nosched@test.local','$2y$10$47TEmEElICH2FHOHTmibb.hGOfedPIXXwTsH2o/LykOCORt.hIaGq','student',NULL,901,NULL,NULL,'2026',NULL,0,NULL,'2026-11-20 05:50:43','2026-11-20 05:50:43');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workplace_change_requests`
--

DROP TABLE IF EXISTS `workplace_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workplace_change_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) unsigned NOT NULL,
  `workplace_name` varchar(255) NOT NULL,
  `workplace_address` text NOT NULL,
  `position_title` varchar(255) DEFAULT NULL,
  `supervisor_name` varchar(255) DEFAULT NULL,
  `supervisor_position` varchar(255) DEFAULT NULL,
  `head_trainee` varchar(255) DEFAULT NULL,
  `head_trainee_position` varchar(255) DEFAULT NULL,
  `head_trainee_contact` varchar(50) DEFAULT NULL,
  `head_trainee_email` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `workplace_change_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workplace_change_requests`
--

LOCK TABLES `workplace_change_requests` WRITE;
/*!40000 ALTER TABLE `workplace_change_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `workplace_change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'ojt_db_test'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
