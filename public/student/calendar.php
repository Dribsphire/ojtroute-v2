<?php
session_start();
// Login + role check + CSRF protection
require_once __DIR__ . '/../../app/middleware/requireStudent.php';
require_once __DIR__ . '/../../app/services/StudentService.php';

$studentService = new \App\Services\StudentService();
$userId = $_SESSION['user_id'] ?? 0;
$dbId = $studentService->getStudentDbId($userId);

// Schedule + workplace timezone: times are stored in PH time and shown in the
// workplace's local time (same thing for students in the Philippines).
$shiftSchedule = $studentService->getShiftSchedule($dbId);
$toLocal = function ($v) use ($shiftSchedule) {
    return ($shiftSchedule && $v) ? $shiftSchedule->storageToLocalString($v) : $v;
};

// Month selection (defaults to current month)
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

if ($selectedMonth < 1)
    $selectedMonth = 1;
if ($selectedMonth > 12)
    $selectedMonth = 12;

$firstDayTs = strtotime(sprintf('%04d-%02d-01', $selectedYear, $selectedMonth));
$daysInMonth = (int) date('t', $firstDayTs);
$firstDow = (int) date('w', $firstDayTs); // 0=Sun

$prevYear = $selectedYear;
$prevMonth = $selectedMonth - 1;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextYear = $selectedYear;
$nextMonth = $selectedMonth + 1;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthLabel = date('F Y', $firstDayTs);

// Fetch attendance records for the selected month
$attendance = [];
try {
    $config = require __DIR__ . '/../../config/database.php';
    $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", $config['host'], $config['dbname'], $config['charset']);
    $db = new PDO($dsn, $config['username'], $config['password'], $config['options']);

    $startDate = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
    $endDate = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $daysInMonth);

    $stmt = $db->prepare("
        SELECT *  -- includes break_start/break_end once add_schedule_break.sql is run
        FROM attendance_records 
        WHERE student_id = ? 
        AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date, block_type
    ");
    $stmt->execute([$dbId, $startDate, $endDate]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize data by date
    foreach ($records as $record) {
        $date = $record['attendance_date'];

        if (!isset($attendance[$date])) {
            $attendance[$date] = [
                'status' => 'present', // If there's any record, mark as present
                'blocks' => [],
                'details' => []
            ];
        }

        $blockType = $record['block_type'];
        $attendance[$date]['blocks'][$blockType] = $record['photo_path'];
        $attendance[$date]['details'][$blockType] = [
            'time_in' => $toLocal($record['time_in']),
            'time_out' => $toLocal($record['time_out']),
            'hours' => $record['hours'],
            'status' => $record['status'],
            // Unpaid break deducted from this record (shown as AM OUT / PM IN on the DTR)
            'break_start' => $toLocal($record['break_start'] ?? null),
            'break_end' => $toLocal($record['break_end'] ?? null)
        ];
    }

    // Fetch excused dates from excused_dates table
    try {
        if (!isset($db)) {
            $config = require __DIR__ . '/../../config/database.php';
            $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", $config['host'], $config['dbname'], $config['charset']);
            $db = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        }

        $stmt = $db->prepare("
            SELECT 
                excused_date,
                hours_added,
                reason,
                created_at
            FROM excused_dates
            WHERE student_id = ?
            AND excused_date BETWEEN ? AND ?
            ORDER BY excused_date
        ");
        $stmt->execute([$dbId, $startDate, $endDate]);
        $excusedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($excusedRecords as $excuse) {
            $excusedDate = $excuse['excused_date'];

            // Add excuse data to the date (even if attendance exists)
            if (!isset($attendance[$excusedDate])) {
                // No attendance - mark as excused only
                $attendance[$excusedDate] = [
                    'status' => 'excused',
                    'blocks' => [
                        'morning' => null,
                        'afternoon' => null,
                        'overtime' => null
                    ],
                    'details' => [
                        'morning' => null,
                        'afternoon' => null,
                        'overtime' => null
                    ],
                    'excuse_reason' => $excuse['reason'],
                    'excuse_hours' => $excuse['hours_added'],
                    'excuse_date' => $excuse['created_at'],
                    'has_excuse' => true
                ];
            } else {
                // Attendance exists - add excuse data to existing record
                $attendance[$excusedDate]['excuse_reason'] = $excuse['reason'];
                $attendance[$excusedDate]['excuse_hours'] = $excuse['hours_added'];
                $attendance[$excusedDate]['excuse_date'] = $excuse['created_at'];
                $attendance[$excusedDate]['has_excuse'] = true;
            }
        }
    } catch (Exception $e) {
        error_log('Excused dates fetch error: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log('Calendar fetch error: ' . $e->getMessage());
}

// Fetch student information for DTR
$studentInfo = [];
try {
    if (!isset($db)) {
        $config = require __DIR__ . '/../../config/database.php';
        $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", $config['host'], $config['dbname'], $config['charset']);
        $db = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    }

    $stmt = $db->prepare("
        SELECT 
            u.full_name,
            u.school_id,
            s.section_name,
            s.section_code,
            s.department,
            sw.company_name,
            sw.company_head,
            sw.position_title,
            sw.schedule_start_time,
            COALESCE(os.manual_adjustment_hours, 0) as manual_hours
        FROM students st
        JOIN users u ON st.user_id = u.id
        LEFT JOIN sections s ON u.section_id = s.id
        LEFT JOIN student_workplaces sw ON st.id = sw.student_id
        LEFT JOIN ojt_summaries os ON st.id = os.student_id
        WHERE st.id = ?
        ORDER BY sw.id DESC
        LIMIT 1
    ");
    $stmt->execute([$dbId]);
    $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($shiftSchedule) {
        $studentInfo['timezone'] = $shiftSchedule->timezoneName();
        $studentInfo['timezone_abbr'] = $shiftSchedule->timezoneAbbr();
        $studentInfo['abroad'] = !$shiftSchedule->isPhilippineTime();
    }
} catch (Exception $e) {
    error_log('Student info fetch error: ' . $e->getMessage());
}

// Fetch all excused dates for this student (for DTR printing)
$allExcusedDates = [];
try {
    if (!isset($db)) {
        $config = require __DIR__ . '/../../config/database.php';
        $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", $config['host'], $config['dbname'], $config['charset']);
        $db = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    }

    $stmt = $db->prepare("
        SELECT 
            excused_date,
            hours_added,
            reason
        FROM excused_dates
        WHERE student_id = ?
        ORDER BY excused_date
    ");
    $stmt->execute([$dbId]);
    $allExcusedDates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Excused dates fetch error for DTR: ' . $e->getMessage());
}

// Fetch double hours dates for the selected month
$doubleHoursDates = [];
try {
    if (!isset($db)) {
        $config = require __DIR__ . '/../../config/database.php';
        $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", $config['host'], $config['dbname'], $config['charset']);
        $db = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    }
    $stmt = $db->prepare("SELECT date FROM double_hours_dates WHERE date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $doubleHoursDates = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'date');
} catch (Exception $e) {
    error_log('Double hours dates fetch error: ' . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - OJTRoute System</title>
    <link rel="icon" type="image/png" href="../images/CHMSU.png">
    <link rel="stylesheet" href="../css/student_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- CRITICAL FIX: Add hours validation utilities to prevent frontend crashes -->
    <script src="../js/hours-validation.js"></script>
    <style>
        .calendar-card {
            background: var(--hover-clr);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .calendar-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .calendar-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }

        .calendar-actions {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .cal-btn {
            background: transparent;
            border: 1px solid var(--line-clr);
            color: var(--text-clr);
            padding: .55rem .85rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .cal-btn:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        .cal-btn.primary {
            border-color: var(--accent-clr);
            color: var(--accent-clr);
        }

        .legend {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin: 1.5rem 0 0;
            justify-content: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: .5rem;
            color: var(--text-clr);
            font-size: .95rem;
        }

        .legend-box {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }

        .legend-box.present {
            background: #1ad21c33;
        }

        .legend-box.absent {
            background: #ff4d4d33;
        }

        .legend-box.excused {
            background: #4da6ff33;
        }

        /* Calendar display style based on instructor/student_information.php */
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.25rem;
            padding: 0.25rem;
        }

        .calendar-header {
            text-align: center;
            font-weight: 600;
            padding: 0.5rem;
            background: #2a2b3a;
            border-radius: 4px;
        }

        .calendar-day {
            position: relative;
            aspect-ratio: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            background: #2a2b3a;
            padding: 0.1rem;
            font-size: 0.8rem;
            min-height: 30px;
        }

        .calendar-day small {
            font-size: 0.65rem;
            line-height: 1;
            margin-top: 2px;
        }

        .dh-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            font-size: 0.60rem;
            font-weight: 800;
            color: #f59e0b;
            line-height: 1;
            pointer-events: none;
        }

        .present {
            background: #1ad21c33;
            color: var(--accent-clr);
        }

        .absent {
            background: #ff4d4d33;
            color: #ff4d4d;
        }

        .excused {
            background: #4da6ff33;
            color: #4da6ff;
        }

        .calendar-day.present.clickable {
            cursor: pointer;
        }

        .calendar-day.present.clickable:hover {
            outline: 2px solid rgba(26, 210, 28, 0.45);
        }

        .calendar-day.excused.clickable {
            cursor: pointer;
        }

        .calendar-day.excused.clickable:hover {
            outline: 2px solid rgba(77, 166, 255, 0.45);
        }

        /* Dates with both attendance and excuse - show blue border */
        .calendar-day.has-excuse.present {
            border-left: 4px solid #4da6ff;
            position: relative;
        }

        .calendar-day.has-excuse.present::after {
            content: '★';
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 0.6rem;
            color: #4da6ff;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            padding: 1.5rem;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            width: min(980px, 96vw);
            max-height: 90vh;
            overflow: auto;
            background: var(--base-clr);
            border: 1px solid var(--line-clr);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--line-clr);
        }

        .modal-title {
            margin: 0;
            font-size: 1.05rem;
        }

        .modal-close {
            background: transparent;
            border: 1px solid var(--line-clr);
            color: var(--text-clr);
            padding: .35rem .65rem;
            border-radius: 8px;
            cursor: pointer;
        }

        .modal-body {
            padding: 1.25rem;
        }

        .block-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        @media(max-width: 900px) {
            .block-grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 1rem;
            }
        }

        .block-card {
            border: 1px solid var(--line-clr);
            border-radius: 12px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.03);
        }

        .block-card h4 {
            margin: 0;
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--line-clr);
            font-size: 0.95rem;
        }

        .block-img-wrap {
            padding: .75rem 1rem 1rem;
            position: relative;
            overflow: hidden;
        }

        .late-badge {
            position: absolute;
            bottom: 0.5rem;
            left: 0.5rem;
            background: rgba(220, 38, 38, 0.88);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            pointer-events: none;
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .late-badge::before {
            font-size: 0.7rem;
        }

        .block-img {
            width: 100%;
            height: 220px;
            border-radius: 10px;
            object-fit: cover;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .no-photo {
            width: 100%;
            height: 220px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-text-clr);
            border: 1px dashed rgba(255, 255, 255, 0.12);
            background: rgba(0, 0, 0, 0.18);
            font-size: .9rem;
        }

        .print-row {
            display: flex;
            align-items: flex-end;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
        }

        .form-field label {
            color: var(--secondary-text-clr);
            font-size: .9rem;
        }

        .form-field select {
            min-width: 220px;
            padding: .65rem .75rem;
            border-radius: 8px;
            border: 1px solid var(--line-clr);
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-clr);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .calendar-card {
                padding: 0.75rem;
                height: 34rem;
                width: 19rem;
                margin: 0 auto;
            }

            .calendar-topbar {
                flex-direction: column;
                align-items: stretch;
                margin-bottom: 0.75rem;
            }

            .calendar-title {
                font-size: 0.95rem;
                text-align: center;
                margin-bottom: 0.5rem;
            }

            .calendar-actions {
                justify-content: center;
                gap: 0.35rem;
            }

            .cal-btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.75rem;
            }

            .calendar {
                gap: 0.1rem;
                padding: 0.1rem;
            }

            .calendar-header {
                font-size: 0.65rem;
                padding: 0.3rem 0.1rem;
            }

            .calendar-day {
                font-size: 0.7rem;
                min-height: 18px;
                padding: 0.05rem;
            }

            /* Hide status text (Present, Absent, Excused) on responsive */
            .calendar-day small {
                display: none;
            }

            .legend {
                gap: 0.5rem;
                margin: 0.75rem 0 0;
                font-size: 0.7rem;
            }

            .legend-item {
                gap: 0.3rem;
            }

            .legend-box {
                width: 12px;
                height: 12px;
            }
        }

        @media (max-width: 480px) {
            .calendar-card {
                padding: 0.75rem;
                height: auto;
                width: 19rem;
                margin: 0 auto;
            }

            .calendar-topbar {
                margin-bottom: 0.5rem;
            }

            .calendar-title {
                font-size: 0.9rem;
            }

            .cal-btn {
                padding: 0.35rem 0.5rem;
                font-size: 0.7rem;
            }

            .cal-btn i {
                font-size: 0.7rem;
            }

            .calendar {
                gap: 0.08rem;
                padding: 0.08rem;
            }

            .calendar-header {
                font-size: 0.6rem;
                padding: 0.25rem 0.05rem;
            }

            .calendar-day {
                font-size: 0.65rem;
                aspect-ratio: 1;
                min-height: 20px;
                padding: 0.03rem;
            }

            /* Hide status text (Present, Absent, Excused) on responsive */
            .calendar-day small {
                display: none;
            }

            .legend {
                gap: 0.4rem;
                margin: 0.5rem 0 0;
                font-size: 0.65rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            .legend-item {
                gap: 0.25rem;
            }

            .legend-box {
                width: 10px;
                height: 10px;
            }
        }
    </style>
</head>

<body>
    <?php require_once 'student_nav.php'; ?>
    <main>
        <div class="calendar-container">
            <h1>Attendance Calendar</h1><br>

            <div class="calendar-card">
                <div class="calendar-topbar">
                    <div class="calendar-actions">
                        <a class="cal-btn" href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>"
                            aria-label="Previous Month">
                            <i class="fa-solid fa-chevron-left"></i>
                            Prev
                        </a>
                        <a class="cal-btn" href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>"
                            aria-label="Next Month">
                            Next
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>

                    <h2 class="calendar-title"><?php echo htmlspecialchars($monthLabel); ?></h2>

                    <div class="calendar-actions">
                        <button id="openPrintModal" class="cal-btn primary" type="button">
                            <i class="fa-solid fa-print"></i>
                            Print
                        </button>
                    </div>
                </div>

                <div class="calendar" role="grid" aria-label="Attendance Calendar">
                    <?php
                    $dowLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    foreach ($dowLabels as $l) {
                        echo '<div class="calendar-header" role="columnheader">' . htmlspecialchars($l) . '</div>';
                    }

                    for ($i = 0; $i < $firstDow; $i++) {
                        echo '<div class="calendar-day" aria-hidden="true"></div>';
                    }

                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $dateKey = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $day);
                        $status = isset($attendance[$dateKey]) ? $attendance[$dateKey]['status'] : null;
                        $isPresent = ($status === 'present');
                        $isExcused = ($status === 'excused');
                        $hasExcuse = isset($attendance[$dateKey]['has_excuse']) && $attendance[$dateKey]['has_excuse'];

                        $classes = 'calendar-day';
                        if ($status)
                            $classes .= ' ' . $status;
                        if ($hasExcuse)
                            $classes .= ' has-excuse';
                        if ($isPresent || $isExcused)
                            $classes .= ' clickable';

                        $blocks = isset($attendance[$dateKey]) ? $attendance[$dateKey]['blocks'] : [];
                        $details = isset($attendance[$dateKey]) ? $attendance[$dateKey]['details'] : [];

                        // Get excuse data if available
                        $excuseReason = isset($attendance[$dateKey]['excuse_reason']) ? $attendance[$dateKey]['excuse_reason'] : '';
                        $excuseHours = isset($attendance[$dateKey]['excuse_hours']) ? $attendance[$dateKey]['excuse_hours'] : 0;

                        echo '<div class="' . htmlspecialchars($classes) . '"'
                            . ' data-date="' . htmlspecialchars($dateKey) . '"'
                            . ' data-status="' . htmlspecialchars((string) $status) . '"'
                            . ' data-blocks="' . htmlspecialchars(json_encode($blocks)) . '"'
                            . ' data-details="' . htmlspecialchars(json_encode($details)) . '"'
                            . ' data-excuse-reason="' . htmlspecialchars($excuseReason) . '"'
                            . ' data-excuse-hours="' . htmlspecialchars((string) $excuseHours) . '"'
                            . ' data-has-excuse="' . ($hasExcuse ? 'true' : 'false') . '"'
                            . ' role="gridcell" tabindex="0">';

                        $isDoubleHours = in_array($dateKey, $doubleHoursDates);

                        if ($isDoubleHours) {
                            echo '<span class="dh-badge" title="Double Hours">2X</span>';
                        }

                        if ($status === 'present') {
                            echo (int) $day . '<br><small>Present</small>';
                        } elseif ($status === 'absent') {
                            echo (int) $day . '<br><small>Absent</small>';
                        } elseif ($status === 'excused') {
                            echo (int) $day . '<br><small>Excused</small>';
                        } else {
                            echo (int) $day;
                        }

                        echo '</div>';
                    }
                    ?>
                </div>

                <div class="legend" aria-label="Legend">
                    <div class="legend-item"><span class="legend-box present"></span><span>Present</span></div>
                    <div class="legend-item"><span class="legend-box excused"></span><span>Excused</span></div>
                    <div class="legend-item"><span style="color: #4da6ff; font-size: 1.1rem;">★</span><span> Present &
                            Excuse </span></div>
                    <div class="legend-item"><span
                            style="color: #f59e0b; font-weight: 800; font-size: 0.85rem;">2×</span><span>Double
                            Hours</span></div>
                </div>
            </div>
        </div>
    </main>

    <!-- Attendance Photos Modal -->
    <div id="attendanceModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="attendanceModalTitle">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="attendanceModalTitle" class="modal-title">Attendance Photos</h3>
                <button type="button" class="modal-close" data-close-modal="attendanceModal" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="block-grid" id="blockGridContainer">
                    <!-- Block cards are dynamically generated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Print Modal -->
    <div id="printModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="printModalTitle">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="printModalTitle" class="modal-title">Print Monthly Attendance</h3>
                <button type="button" class="modal-close" data-close-modal="printModal" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="print-row">
                    <div class="form-field">
                        <label for="printMonth">Select Month</label>
                        <select id="printMonth">
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $label = date('F', strtotime(sprintf('2025-%02d-01', $m)));
                                $selected = ($m === $selectedMonth) ? 'selected' : '';
                                echo '<option value="' . $m . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="printYear">Year</label>
                        <select id="printYear">
                            <?php
                            $years = [$selectedYear - 1, $selectedYear, $selectedYear + 1];
                            foreach ($years as $y) {
                                $selected = ((int) $y === (int) $selectedYear) ? 'selected' : '';
                                echo '<option value="' . (int) $y . '" ' . $selected . '>' . (int) $y . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button id="printMonthlyBtn" class="cal-btn primary" type="button">
                        <i class="fa-solid fa-print"></i>
                        Print Monthly
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const attendanceData = <?php echo json_encode($attendance, JSON_UNESCAPED_SLASHES); ?>;
        const studentInfo = <?php echo json_encode($studentInfo, JSON_UNESCAPED_SLASHES); ?>;
        const allExcusedDates = <?php echo json_encode($allExcusedDates, JSON_UNESCAPED_SLASHES); ?>;

        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        function createImageOrPlaceholder(src) {
            if (!src) {
                const empty = document.createElement('div');
                empty.className = 'no-photo';
                empty.textContent = 'No photo available';
                return empty;
            }
            const img = document.createElement('img');
            img.className = 'block-img';
            img.alt = 'Attendance photo';
            img.src = src;
            img.onerror = function () {
                const parent = img.parentElement;
                if (!parent) return;
                const empty = document.createElement('div');
                empty.className = 'no-photo';
                empty.textContent = 'No photo available';
                parent.replaceChildren(empty);
            };
            return img;
        }

        // ---- Date helpers -------------------------------------------------
        // DB values look like "2026-02-01 22:05:00" (already in the workplace's
        // local time). Parse them by hand: new Date("YYYY-MM-DD HH:MM:SS") is
        // Invalid Date on iPhone/Safari and shifts with the viewer's timezone.
        function parseDbDateTime(v) {
            const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/.exec(v || '');
            if (!m) return null;
            return {
                date: `${m[1]}-${m[2]}-${m[3]}`,
                y: +m[1], mo: +m[2], d: +m[3],
                h: +(m[4] || 0), mi: +(m[5] || 0), s: +(m[6] || 0)
            };
        }

        // Seconds since epoch treating the wall time as UTC - only for differences
        function dbToSeconds(p) {
            return Date.UTC(p.y, p.mo - 1, p.d, p.h, p.mi, p.s) / 1000;
        }

        // "10:05 PM"
        function fmtClock12(p, withPeriod = true) {
            if (!p) return '';
            const h12 = p.h % 12 || 12;
            const mm = String(p.mi).padStart(2, '0');
            return withPeriod ? `${h12}:${mm} ${p.h >= 12 ? 'PM' : 'AM'}` : `${h12}:${mm}`;
        }

        // True when the time falls on a later calendar day than the shift date (night shift)
        function isNextDay(p, shiftDate) {
            return !!(p && shiftDate && p.date > shiftDate);
        }

        // hours (decimal) -> "8:30"
        function fmtHrsMins(hours) {
            const totalMin = Math.round((Number(hours) || 0) * 60);
            return `${Math.floor(totalMin / 60)}:${String(totalMin % 60).padStart(2, '0')}`;
        }

        const blockLabels = {
            'regular': 'Regular Hours',
            'overtime': 'Overtime',
            'morning': 'Morning Attendance',
            'afternoon': 'Afternoon Attendance'
        };

        function openAttendancePhotos(dateKey, blocks, details) {
            const title = document.getElementById('attendanceModalTitle');
            if (title) title.textContent = `Attendance Details - ${dateKey}`;

            const container = document.getElementById('blockGridContainer');
            if (!container) return;
            container.innerHTML = '';

            // Helper function to format time details
            function formatDetails(blockDetails) {
                if (!blockDetails) return '<em>No attendance record</em>';

                const inP = parseDbDateTime(blockDetails.time_in);
                const outP = parseDbDateTime(blockDetails.time_out);
                const timeIn = inP ? fmtClock12(inP) + (isNextDay(inP, dateKey) ? ' (next day)' : '') : '-';
                const timeOut = outP ? fmtClock12(outP) + (isNextDay(outP, dateKey) ? ' (next day)' : '') : '-';
                const hours = blockDetails.hours ? formatHours(blockDetails.hours, 2) : '-';
                const status = blockDetails.status || '-';

                return `
                    <div style="line-height: 1.6;">
                        <div><strong>Time In:</strong> ${timeIn}</div>
                        <div><strong>Time Out:</strong> ${timeOut}</div>
                        ${blockDetails.break_start && blockDetails.break_end ? `<div><strong>Break:</strong> ${fmtClock12(parseDbDateTime(blockDetails.break_start))} - ${fmtClock12(parseDbDateTime(blockDetails.break_end))} (not counted)</div>` : ''}
                        <div><strong>Hours:</strong> ${hours} hrs</div>
                        <div><strong>Status:</strong> <span style="text-transform: capitalize;">${status}</span></div>
                    </div>
                `;
            }

            // Determine which blocks to show
            const blockTypes = Object.keys(blocks || {}).length > 0 ? Object.keys(blocks) : (Object.keys(details || {}).length > 0 ? Object.keys(details) : ['regular', 'overtime']);

            // Create a card for each block
            const uniqueBlocks = [...new Set(blockTypes)];
            uniqueBlocks.forEach(blockType => {
                const card = document.createElement('div');
                card.className = 'block-card';

                const label = blockLabels[blockType] || (blockType.charAt(0).toUpperCase() + blockType.slice(1) + ' Attendance');
                const h4 = document.createElement('h4');
                h4.textContent = label;
                card.appendChild(h4);

                const imgWrap = document.createElement('div');
                imgWrap.className = 'block-img-wrap';
                imgWrap.appendChild(createImageOrPlaceholder(blocks ? blocks[blockType] : null));
                card.appendChild(imgWrap);

                const detailsDiv = document.createElement('div');
                detailsDiv.className = 'block-details';
                detailsDiv.style.cssText = 'padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--secondary-text-clr);';
                detailsDiv.innerHTML = formatDetails(details ? details[blockType] : null);
                card.appendChild(detailsDiv);

                // --- Late badge logic ---
                const blockDetails = details ? details[blockType] : null;
                let lateMinutes = 0;
                if (blockDetails && blockDetails.time_in && studentInfo.schedule_start_time) {
                    // Only check lateness for the first block of the day (regular / morning)
                    if (blockType === 'regular' || blockType === 'morning') {
                        try {
                            // Compare against the scheduled start ON THE SHIFT DATE (dateKey), so a
                            // night-shift time-in after midnight is still counted correctly
                            const schedStart = parseDbDateTime(dateKey + ' ' + studentInfo.schedule_start_time);
                            const timeInP = parseDbDateTime(blockDetails.time_in);
                            if (schedStart && timeInP) {
                                const diffSec = dbToSeconds(timeInP) - dbToSeconds(schedStart);
                                if (diffSec > 60) lateMinutes = Math.round(diffSec / 60);
                            }
                        } catch (e) { }
                    }
                }

                if (lateMinutes > 0) {
                    const badge = document.createElement('div');
                    badge.className = 'late-badge';
                    const hrs = Math.floor(lateMinutes / 60);
                    const mins = lateMinutes % 60;
                    badge.textContent = hrs > 0
                        ? `Late ${hrs}h ${mins}m`
                        : `Late ${mins} min`;
                    imgWrap.appendChild(badge);
                }
                // --- end late badge ---

                container.appendChild(card);
            });

            openModal('attendanceModal');
        }

        document.addEventListener('click', (e) => {
            const closeBtn = e.target.closest('[data-close-modal]');
            if (closeBtn) {
                closeModal(closeBtn.getAttribute('data-close-modal'));
                return;
            }

            if (e.target.id === 'attendanceModal') {
                closeModal('attendanceModal');
                return;
            }
            if (e.target.id === 'printModal') {
                closeModal('printModal');
                return;
            }

            const dayEl = e.target.closest('.calendar-day.clickable');
            if (dayEl) {
                const dateKey = dayEl.getAttribute('data-date');
                const status = dayEl.getAttribute('data-status');

                if (status === 'excused') {
                    // Show excuse details
                    const excuseReason = dayEl.getAttribute('data-excuse-reason');
                    const excuseHours = dayEl.getAttribute('data-excuse-hours');

                    const title = document.getElementById('attendanceModalTitle');
                    if (title) title.textContent = `Excused Absence - ${dateKey}`;

                    // Clear container for excuse view
                    const container = document.getElementById('blockGridContainer');
                    if (container) {
                        container.innerHTML = `
                            <div class="block-card">
                                <h4>Excused Absence</h4>
                                <div class="block-img-wrap"><div class="no-photo">Excused Absence</div></div>
                                <div class="block-details" style="padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--secondary-text-clr);">
                                    <div style="line-height: 1.8; background: rgba(77, 166, 255, 0.1); padding: 1rem; border-radius: 8px; border: 1px solid rgba(77, 166, 255, 0.3);">
                                        <div style="font-size: 1rem; margin-bottom: 0.75rem; color: #4da6ff; font-weight: 600;">
                                            <i class="fas fa-calendar-check"></i> Excused by Instructor
                                        </div>
                                        <div><strong>Hours Credited:</strong> ${formatHours(excuseHours, 2)} hours</div>
                                        <div><strong>Reason:</strong> ${excuseReason}</div>
                                        <div style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--secondary-text-clr);">
                                            <i class="fas fa-info-circle"></i> This absence was approved and hours were automatically credited to your OJT record.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    openModal('attendanceModal');
                } else if (status === 'present') {
                    // Show attendance photos
                    const row = attendanceData[dateKey];
                    if (!row || row.status !== 'present') return;

                    const hasExcuse = dayEl.getAttribute('data-has-excuse') === 'true';
                    const excuseReason = dayEl.getAttribute('data-excuse-reason');
                    const excuseHours = dayEl.getAttribute('data-excuse-hours');

                    // If date has both attendance and excuse, show both
                    if (hasExcuse && excuseReason) {
                        const title = document.getElementById('attendanceModalTitle');
                        if (title) title.textContent = `Attendance Details - ${dateKey} ★ Also Excused`;

                        openAttendancePhotos(
                            dateKey,
                            row.blocks || {},
                            row.details || {}
                        );

                        // Add excuse banner at the top of the first block details
                        const firstDetails = document.querySelector('#blockGridContainer .block-details');
                        if (firstDetails) {
                            const excuseBanner = `
                                <div style="line-height: 1.6; background: rgba(77, 166, 255, 0.15); padding: 0.75rem; border-radius: 6px; border: 1px solid rgba(77, 166, 255, 0.4); margin-bottom: 0.75rem;">
                                    <div style="font-size: 0.9rem; color: #4da6ff; font-weight: 600; margin-bottom: 0.25rem;">
                                        <i class="fas fa-star"></i> This date was also marked as excused
                                    </div>
                                    <div style="font-size: 0.85rem;"><strong>Hours Credited:</strong> ${formatHours(excuseHours, 2)} hours</div>
                                    <div style="font-size: 0.85rem;"><strong>Reason:</strong> ${excuseReason}</div>
                                </div>
                            `;
                            firstDetails.innerHTML = excuseBanner + firstDetails.innerHTML;
                        }
                    } else {
                        // Regular attendance without excuse
                        openAttendancePhotos(
                            dateKey,
                            row.blocks || {},
                            row.details || {}
                        );
                    }
                }
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal('attendanceModal');
                closeModal('printModal');
            }

            if (e.key === 'Enter') {
                const focusedDay = document.activeElement;
                if (focusedDay && focusedDay.classList && focusedDay.classList.contains('calendar-day') && focusedDay.classList.contains('present') && focusedDay.classList.contains('clickable')) {
                    focusedDay.click();
                }
            }
        });

        document.getElementById('openPrintModal')?.addEventListener('click', () => {
            openModal('printModal');
        });

        function escapeHtml(v) {
            return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        // "KIZHA MAE M. BERMUDEZ" / "ALEX S. AMAR, JR" -> { first, mi, last }
        function splitFullName(full) {
            let name = String(full || '').trim().replace(/\s+/g, ' ');
            let suffix = '';
            const comma = name.indexOf(',');
            if (comma !== -1) {
                suffix = name.slice(comma + 1).trim();
                name = name.slice(0, comma).trim();
            }
            const parts = name.split(' ').filter(Boolean);
            if (parts.length === 0) return { first: '', mi: '', last: '' };
            if (parts.length === 1) return { first: parts[0], mi: '', last: suffix };
            const miIdx = parts.findIndex((p, i) => i > 0 && i < parts.length - 1 && /^[A-Za-z]\.?$/.test(p));
            let first, mi = '', last;
            if (miIdx !== -1) {
                first = parts.slice(0, miIdx).join(' ');
                mi = parts[miIdx].replace('.', '') + '.';
                last = parts.slice(miIdx + 1).join(' ');
            } else {
                first = parts.slice(0, -1).join(' ');
                last = parts[parts.length - 1];
            }
            return { first, mi, last: suffix ? `${last} ${suffix}` : last };
        }

        const programNames = {
            'BSIT': 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY',
            'BSIS': 'BACHELOR OF SCIENCE IN INFORMATION SYSTEMS',
            'BSCS': 'BACHELOR OF SCIENCE IN COMPUTER SCIENCE'
        };

        function buildPrintHtml(year, month) {
            const monthNames = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
            const monthLabel = `${monthNames[month - 1]} ${year}`;
            const daysInMonth = new Date(year, month, 0).getDate();
            const pad = n => String(n).padStart(2, '0');

            // One row per day. A shift is listed on the date it STARTED; each time goes
            // in the AM or PM column by its own clock time, so a night shift reads
            // PM IN 10:00 -> AM OUT 6:00 (+1).
            let totalActual = 0;
            let totalCounted = 0;
            let hasNextDay = false;
            let rows = '';

            for (let day = 1; day <= daysInMonth; day++) {
                const dateKey = `${year}-${pad(month)}-${pad(day)}`;
                const item = attendanceData[dateKey];
                const excused = (allExcusedDates || []).find(e => String(e.excused_date).slice(0, 10) === dateKey);

                const cell = { amIn: '', amOut: '', pmIn: '', pmOut: '', otIn: '', otOut: '' };
                let actual = 0;
                let counted = 0;
                const remarks = [];

                const timeCell = (p) => {
                    if (!p) return '';
                    const next = isNextDay(p, dateKey);
                    if (next) hasNextDay = true;
                    return escapeHtml(fmtClock12(p, false)) + (next ? '<sup class="nd">+1</sup>' : '');
                };
                const done = (rec) => rec && (rec.status === 'completed' || rec.status === 'approved');
                const elapsedHours = (inP, outP) => (inP && outP) ? Math.max(0, (dbToSeconds(outP) - dbToSeconds(inP)) / 3600) : 0;

                if (excused) {
                    counted += safeParseHours(excused.hours_added || 0, 24);
                    remarks.push(excused.reason || 'Excused');
                } else if (item && item.details) {
                    const d = item.details;

                    // Regular shift (current system): place each time by AM/PM
                    if (done(d.regular)) {
                        const inP = parseDbDateTime(d.regular.time_in);
                        const outP = parseDbDateTime(d.regular.time_out);
                        const bS = parseDbDateTime(d.regular.break_start);
                        const bE = parseDbDateTime(d.regular.break_end);
                        const fullBreakInside = inP && outP && bS && bE &&
                            dbToSeconds(bS) > dbToSeconds(inP) && dbToSeconds(bE) < dbToSeconds(outP);

                        // Only when it really is "morning, break, afternoon" on the same day,
                        // so AM/PM columns never hold the wrong half of the day
                        const dayShiftWithBreak = fullBreakInside && inP.h < 12 && bE.h >= 12 && outP.h >= 12 &&
                            !isNextDay(outP, inP.date);
                        if (dayShiftWithBreak) {
                            // Day shift with a break: standard 4-punch DTR
                            // AM IN = time in | AM OUT = break start | PM IN = break end | PM OUT = time out
                            cell.amIn = timeCell(inP);
                            cell.amOut = timeCell(bS);
                            cell.pmIn = timeCell(bE);
                            cell.pmOut = timeCell(outP);
                        } else {
                            // Each time in its own AM/PM column (night / afternoon shifts)
                            if (inP) cell[inP.h < 12 ? 'amIn' : 'pmIn'] = timeCell(inP);
                            if (outP) cell[outP.h < 12 ? 'amOut' : 'pmOut'] = timeCell(outP);
                            if (bS && bE) {
                                remarks.push(`Break ${fmtClock12(bS)}-${fmtClock12(bE)} not counted`);
                            }
                        }
                        actual += elapsedHours(inP, outP);
                        counted += d.regular.hours ? safeParseHours(d.regular.hours, 12) : 0;
                        if (isNextDay(outP, dateKey)) remarks.unshift('Night shift');
                    }

                    // Legacy morning / afternoon blocks (old records)
                    if (done(d.morning)) {
                        const inP = parseDbDateTime(d.morning.time_in), outP = parseDbDateTime(d.morning.time_out);
                        cell.amIn = timeCell(inP); cell.amOut = timeCell(outP);
                        actual += elapsedHours(inP, outP);
                        counted += d.morning.hours ? safeParseHours(d.morning.hours, 12) : 0;
                    }
                    if (done(d.afternoon)) {
                        const inP = parseDbDateTime(d.afternoon.time_in), outP = parseDbDateTime(d.afternoon.time_out);
                        cell.pmIn = timeCell(inP); cell.pmOut = timeCell(outP);
                        actual += elapsedHours(inP, outP);
                        counted += d.afternoon.hours ? safeParseHours(d.afternoon.hours, 12) : 0;
                    }

                    if (done(d.overtime)) {
                        const inP = parseDbDateTime(d.overtime.time_in), outP = parseDbDateTime(d.overtime.time_out);
                        cell.otIn = timeCell(inP); cell.otOut = timeCell(outP);
                        actual += elapsedHours(inP, outP);
                        counted += d.overtime.hours ? safeParseHours(d.overtime.hours, 12) : 0;
                    }

                    if (item.has_excuse && item.excuse_reason) remarks.push(item.excuse_reason);
                }

                totalActual += actual;
                totalCounted += counted;

                const timeCols = excused
                    ? `<td colspan="4" class="excused">EXCUSED</td>`
                    : `<td>${cell.amIn}</td><td>${cell.amOut}</td><td>${cell.pmIn}</td><td>${cell.pmOut}</td>`;

                rows += `
                    <tr${excused ? ' class="excused-row"' : ''}>
                        <td class="day">${day}</td>
                        ${timeCols}
                        <td>${cell.otIn}</td>
                        <td>${cell.otOut}</td>
                        <td class="hrs">${fmtHrsMins(actual)}</td>
                        <td class="hrs">${fmtHrsMins(counted)}</td>
                        <td class="remarks">${escapeHtml(remarks.join('; '))}</td>
                    </tr>`;
            }

            const name = splitFullName(studentInfo.full_name);
            const sectionCode = String(studentInfo.section_code || '');
            const yrSec = /^(\d)([A-Za-z])$/.test(sectionCode) ? sectionCode.replace(/^(\d)([A-Za-z])$/, '$1-$2').toUpperCase() : (sectionCode || studentInfo.section_name || '');
            const programCode = String(studentInfo.section_name || '').split(/[-\s]/)[0].toUpperCase();
            const program = programNames[programCode] || programCode;

            const notes = [];
            if (hasNextDay) notes.push('<sup>+1</sup> = time recorded on the following day (night shift). Each shift is listed on the date it started.');
            if (studentInfo.abroad) notes.push(`All times are in the host training establishment's local time (${escapeHtml(studentInfo.timezone)}, ${escapeHtml(studentInfo.timezone_abbr)}).`);

            const field = (label, value, cls = '') => `
                <div class="f ${cls}"><span class="fl">${label}</span><span class="fv">${escapeHtml(value)}</span></div>`;

            return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>DTR - ${escapeHtml(studentInfo.full_name || '')} - ${monthLabel}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; padding: 16px; }
    .header-img { display: block; width: 100%; max-height: 110px; object-fit: contain; margin-bottom: 8px; }
    .info { border: 2px solid #000; display: grid; grid-template-columns: 1.1fr 1.1fr 1fr; padding: 10px 12px; margin-bottom: 14px; }
    .info .col { padding: 0 10px; display: flex; flex-direction: column; gap: 9px; }
    .info .col + .col { border-left: 1px solid #000; }
    .f { display: flex; align-items: flex-end; gap: 6px; font-weight: bold; font-size: 9px; }
    .fl { white-space: nowrap; }
    .fv { flex: 1; border-bottom: 1px dotted #000; font-weight: normal; min-height: 11px; padding-left: 4px; }
    .f.plain .fv { border-bottom: none; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; text-align: center; vertical-align: middle; height: 15px; padding: 1px 2px; }
    th { font-weight: normal; color: #888; font-size: 7.5px; }
    td.day { font-weight: bold; width: 5.5%; }
    td.hrs { font-weight: bold; width: 9%; }
    td.remarks { font-size: 7px; text-align: left; width: 17%; }
    td.excused { font-weight: bold; letter-spacing: 1px; }
    tr.excused-row td { background: #eef5ff; }
    sup.nd { font-size: 6px; font-weight: bold; margin-left: 1px; }
    tfoot td { font-weight: bold; }
    .notes { margin-top: 5px; font-size: 7.5px; line-height: 1.5; }
    .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; margin-top: 26px; font-size: 9px; }
    .sign .who { font-weight: bold; margin-bottom: 36px; }
    .sign .line { border-top: 1px solid #000; text-align: center; color: #888; font-size: 7px; padding-top: 2px; }
    .sign .printed { border-bottom: 1px dotted #000; text-align: center; padding: 3px 0 2px; min-height: 16px; }
    .sign .date { text-align: center; margin-top: 10px; }
    @media print {
        @page { size: A4 portrait; margin: 0.4in; }
        body { padding: 0; }
        tr { page-break-inside: avoid; }
        tr.excused-row td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
    <img src="../../storage/uploads/header.png" alt="" class="header-img" onerror="this.style.display='none'">

    <div class="info">
        <div class="col">
            ${field('Surname:', name.last)}
            ${field('CHMSU COLLEGE OF COMPUTER STUDIES', '', 'plain')}
            ${field('Campus: ALIJIS CAMPUS', '', 'plain')}
            ${field('Host Training Establishment (HTE):', studentInfo.company_name || '')}
            ${field('OJT Supervisor:', studentInfo.company_head || '')}
        </div>
        <div class="col">
            ${field('First Name:', name.first)}
            ${field('Program:', program)}
            ${field('Department Assigned To:', '')}
            ${field('Designation:', studentInfo.position_title || '')}
        </div>
        <div class="col">
            ${field('M.I.:', name.mi)}
            ${field('Yr./Sec.:', yrSec)}
            ${field('For the Month of:', monthNames[month - 1])}
            ${field('Year:', String(year))}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">DATE</th>
                <th colspan="2">AM</th>
                <th colspan="2">PM</th>
                <th colspan="2">OVERTIME</th>
                <th colspan="2">TOTAL DUTY HOURS</th>
                <th rowspan="2">REMARKS</th>
            </tr>
            <tr>
                <th>AM<br>IN</th><th>AM<br>OUT</th>
                <th>PM<br>IN</th><th>PM<br>OUT</th>
                <th>IN</th><th>OUT</th>
                <th>ACTUAL HRS:MINS</th><th>COUNTED HRS:MINS</th>
            </tr>
        </thead>
        <tbody>${rows}</tbody>
        <tfoot>
            <tr>
                <td colspan="7" style="text-align: right; padding-right: 8px;">TOTAL:</td>
                <td>${fmtHrsMins(totalActual)}</td>
                <td>${fmtHrsMins(totalCounted)}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    ${notes.length ? `<div class="notes">${notes.map(n => '&bull; ' + n).join('<br>')}</div>` : ''}

    <div class="sign">
        <div>
            <div class="who">Prepared by:</div>
            <div class="line">Signature over Printed Name</div>
            <div class="printed">${escapeHtml(studentInfo.full_name || '')}</div>
            <div class="date">Date: ____________________</div>
        </div>
        <div>
            <div class="who">Reviewed by:</div>
            <div class="line">Signature over Printed Name</div>
            <div class="printed">${escapeHtml(studentInfo.company_head || '')}</div>
            <div class="date">Date: ____________________</div>
        </div>
    </div>
</body>
</html>`;
        }

        function openPrintWindow(year, month) {
            const win = window.open('', '_blank');
            if (!win) {
                alert('Please allow pop-ups to print your DTR.');
                return;
            }
            win.document.open();
            win.document.write(buildPrintHtml(year, month));
            win.document.close();
            win.focus();
            // Print once the header image has loaded (fallback after 1.5s)
            let printed = false;
            const doPrint = () => { if (!printed) { printed = true; win.print(); } };
            win.onload = doPrint;
            setTimeout(doPrint, 1500);
        }

        const viewedYear = <?php echo (int) $selectedYear; ?>;
        const viewedMonth = <?php echo (int) $selectedMonth; ?>;

        document.getElementById('printMonthlyBtn')?.addEventListener('click', () => {
            const month = parseInt(document.getElementById('printMonth')?.value || String(viewedMonth), 10);
            const year = parseInt(document.getElementById('printYear')?.value || String(viewedYear), 10);

            // Only the viewed month's records are loaded on this page - load the
            // chosen month first (it used to print an empty DTR for other months)
            if (month !== viewedMonth || year !== viewedYear) {
                window.location.href = `?year=${year}&month=${month}&print=1`;
                return;
            }
            openPrintWindow(year, month);
        });

        // Arrived from the print button for another month: print right away
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            const url = new URL(window.location.href);
            url.searchParams.delete('print');
            history.replaceState(null, '', url);
            openPrintWindow(viewedYear, viewedMonth);
        }
    </script>
</body>

</html>