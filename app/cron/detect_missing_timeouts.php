<?php
/**
 * Automatic Missing Timeout Detection Script
 * 
 * This script runs periodically to detect students who timed in but did not time out
 * before the block period ended. It automatically flags these records as "Missing Time-Out".
 * 
 * Schedule: run every hour (block end times now depend on each student's
 * own schedule and workplace timezone, so there is no single fixed time).
 * 
 * Usage:
 * - Via cron: php detect_missing_timeouts.php
 * - Via browser: http://localhost/ojtlast/app/cron/detect_missing_timeouts.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../services/ShiftSchedule.php';

use App\Services\ShiftSchedule;

date_default_timezone_set('Asia/Manila'); // log timestamps in PH time

class MissingTimeoutDetector
{
    private $db;

    public function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        // Same credentials as the website (.env via config/database.php)
        $config = require __DIR__ . '/../../config/database.php';
        $host = $config['host'];
        $dbname = $config['dbname'];
        $username = $config['username'];
        $password = $config['password'];

        try {
            $this->db = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            // Set timezone to Asia/Manila
            $this->db->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed.");
        }
    }

    /**
     * Detect and flag missing timeouts.
     *
     * A record is "missing" once its block window has ended:
     * - regular  -> the student's schedule end (next day for night shifts)
     * - overtime -> schedule end + 4 hours
     * - legacy morning/afternoon/overtime records without a schedule -> 12:00 / 18:00 / 22:00 PH
     * Schedules are read in the workplace's own timezone (student_workplaces.timezone).
     */
    public function detectMissingTimeouts()
    {
        $now = new DateTimeImmutable('now');
        $flaggedCount = 0;

        try {
            $records = $this->db->query("
                SELECT ar.id, ar.student_id, ar.attendance_date, ar.block_type, ar.time_in
                FROM attendance_records ar
                WHERE ar.time_out IS NULL
                AND ar.missing_timeout_flagged_at IS NULL
                AND ar.request_status IS NULL
            ")->fetchAll();
        } catch (PDOException $e) {
            $this->log("Error loading open attendance records: " . $e->getMessage(), 'ERROR');
            return 0;
        }

        $schedules = [];
        foreach ($records as $record) {
            $sid = $record['student_id'];
            if (!array_key_exists($sid, $schedules)) {
                $schedules[$sid] = $this->loadSchedule($sid);
            }

            $blockEnd = $this->blockEnd($schedules[$sid], $record['attendance_date'], $record['block_type']);
            if (!$blockEnd || $now < $blockEnd) {
                continue; // unknown block type, or block still in progress
            }

            if ($this->flagMissingTimeout($record['id'])) {
                $flaggedCount++;
                $this->log(
                    "Flagged missing timeout: Record ID {$record['id']}, " .
                    "Student ID {$sid}, " .
                    "Date {$record['attendance_date']}, " .
                    "Block {$record['block_type']}"
                );
            }
        }

        return $flaggedCount;
    }

    /** @return ShiftSchedule|null */
    private function loadSchedule($studentId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM student_workplaces
            WHERE student_id = ? AND is_active = 1
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['schedule_start_time']) || empty($row['schedule_end_time'])) {
            return null;
        }
        return new ShiftSchedule($row['schedule_start_time'], $row['schedule_end_time'], $row['timezone'] ?? null);
    }

    /** Same rules as StudentService::getBlockEnd() */
    private function blockEnd($shift, $attendanceDate, $blockType)
    {
        if ($shift && in_array($blockType, ['regular', 'overtime'], true)) {
            $w = $shift->windowFor($attendanceDate);
            return $blockType === 'overtime' ? $w['overtime_end'] : $w['end'];
        }
        $legacy = ['morning' => '12:00:00', 'afternoon' => '18:00:00', 'overtime' => '22:00:00'];
        if (isset($legacy[$blockType])) {
            return new DateTimeImmutable($attendanceDate . ' ' . $legacy[$blockType], ShiftSchedule::storageTz());
        }
        return null;
    }

    /**
     * Flag a record as missing timeout
     */
    private function flagMissingTimeout($recordId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE attendance_records 
                SET missing_timeout_flagged_at = NOW(),
                    status = 'pending_exception'
                WHERE id = ?
            ");
            $stmt->execute([$recordId]);

            return true;
        } catch (PDOException $e) {
            $this->log("Error flagging record {$recordId}: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Log messages
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

        // Log to file
        $logFile = __DIR__ . '/../../storage/logs/missing_timeout_detector.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        file_put_contents($logFile, $logMessage, FILE_APPEND);

        // Also output to console if running from CLI
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
}

// Run the detector
try {
    $detector = new MissingTimeoutDetector();
    $flaggedCount = $detector->detectMissingTimeouts();

    $message = "Missing timeout detection completed. Flagged {$flaggedCount} record(s).";
    error_log($message);

    if (php_sapi_name() === 'cli') {
        echo $message . "\n";
    } else {
        // If accessed via browser, return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'flagged_count' => $flaggedCount,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

} catch (Exception $e) {
    $errorMessage = "Error running missing timeout detector: " . $e->getMessage();
    error_log($errorMessage);

    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $errorMessage
        ]);
    } else {
        echo $errorMessage . "\n";
    }
}
