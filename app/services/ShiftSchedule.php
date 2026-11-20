<?php

namespace App\Services;

/**
 * One place for all "which shift is it right now?" logic.
 *
 * A student's schedule (start/end time) is a wall-clock time in the
 * WORKPLACE's timezone. A shift that ends at or before it starts (e.g.
 * 22:00 -> 06:00) crosses midnight and belongs to the date it STARTED on
 * (its "shift date" = attendance_records.attendance_date).
 *
 * Timestamps in the database (time_in / time_out) are always stored in
 * Philippine time (STORAGE_TZ) so existing data, reports and SQL math keep
 * working. Use toStorage() / fromStorage() to convert.
 */
class ShiftSchedule
{
    const STORAGE_TZ = 'Asia/Manila';
    const OVERTIME_HOURS = 4;

    /** @var string HH:MM:SS */
    private $startTime;
    /** @var string HH:MM:SS */
    private $endTime;
    /** @var \DateTimeZone */
    private $tz;
    /** @var string|null HH:MM:SS - unpaid break start (workplace local time) */
    private $breakStart;
    /** @var int unpaid break length in minutes (0 = no break) */
    private $breakMinutes;

    public function __construct($startTime, $endTime, $timezone = null, $breakStart = null, $breakMinutes = 0)
    {
        $this->startTime = self::normalizeTime($startTime);
        $this->endTime = self::normalizeTime($endTime);
        $this->tz = new \DateTimeZone(self::isValidTimezone($timezone) ? $timezone : self::STORAGE_TZ);
        $this->breakMinutes = max(0, (int) $breakMinutes);
        $this->breakStart = ($breakStart && $this->breakMinutes > 0) ? self::normalizeTime($breakStart) : null;
        if (!$this->breakStart) {
            $this->breakMinutes = 0;
        }
    }

    public function hasBreak()
    {
        return $this->breakStart !== null;
    }

    public function breakMinutes()
    {
        return $this->breakMinutes;
    }

    /** @return string|null HH:MM:SS */
    public function breakStartTime()
    {
        return $this->breakStart;
    }

    /**
     * Check that a break lies completely inside the shift.
     * @return string|null error message, or null when valid / no break
     */
    public static function validateBreak($startTime, $endTime, $breakStart, $breakMinutes)
    {
        if (!$breakStart || (int) $breakMinutes <= 0) {
            return null;
        }
        $probe = new self($startTime, $endTime, null, $breakStart, $breakMinutes);
        $w = $probe->windowFor('2000-01-03');
        if ($w['break_start'] < $w['start'] || $w['break_end'] > $w['end']) {
            return 'Your break must be within your working hours.';
        }
        return null;
    }

    private static function imm(\DateTimeInterface $dt)
    {
        return $dt instanceof \DateTimeImmutable ? $dt : \DateTimeImmutable::createFromMutable($dt);
    }

    public static function storageTz()
    {
        return new \DateTimeZone(self::STORAGE_TZ);
    }

    public static function isValidTimezone($tz)
    {
        return is_string($tz) && $tz !== '' && in_array($tz, \DateTimeZone::listIdentifiers(), true);
    }

    private static function normalizeTime($t)
    {
        $parts = array_map('intval', explode(':', (string) $t));
        return sprintf('%02d:%02d:%02d', $parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0);
    }

    public function timezone()
    {
        return $this->tz;
    }

    public function timezoneName()
    {
        return $this->tz->getName();
    }

    public function isCrossDay()
    {
        return $this->endTime <= $this->startTime;
    }

    /**
     * Start / end / overtime-end of the shift that starts on $shiftDate (local Y-m-d).
     * @return array{shift_date:string,start:\DateTimeImmutable,end:\DateTimeImmutable,overtime_end:\DateTimeImmutable}
     */
    public function windowFor($shiftDate)
    {
        $start = new \DateTimeImmutable($shiftDate . ' ' . $this->startTime, $this->tz);
        $end = new \DateTimeImmutable($shiftDate . ' ' . $this->endTime, $this->tz);
        if ($this->isCrossDay()) {
            $end = $end->modify('+1 day');
        }

        // Break: on the shift date, or the next day when it falls after midnight
        // in a night shift (e.g. shift 22:00-06:00, break 02:00)
        $breakStart = $breakEnd = null;
        if ($this->breakStart) {
            $breakStart = new \DateTimeImmutable($shiftDate . ' ' . $this->breakStart, $this->tz);
            if ($breakStart < $start) {
                $breakStart = $breakStart->modify('+1 day');
            }
            $breakEnd = $breakStart->modify('+' . $this->breakMinutes . ' minutes');
        }

        return [
            'shift_date' => $shiftDate,
            'start' => $start,
            'end' => $end,
            'overtime_end' => $end->modify('+' . self::OVERTIME_HOURS . ' hours'),
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
        ];
    }

    /**
     * The part of this shift's break that falls between $in and $out.
     * @return array{start:\DateTimeImmutable,end:\DateTimeImmutable,seconds:int}|null
     *         null when there is no break or the student wasn't working during it
     */
    public function breakOverlap(\DateTimeInterface $in, \DateTimeInterface $out, $shiftDate)
    {
        $w = $this->windowFor($shiftDate);
        if (!$w['break_start']) {
            return null;
        }
        $in = self::imm($in);
        $out = self::imm($out);
        $from = $in > $w['break_start'] ? $in : $w['break_start'];
        $to = $out < $w['break_end'] ? $out : $w['break_end'];
        $seconds = $to->getTimestamp() - $from->getTimestamp();
        if ($seconds <= 0) {
            return null;
        }
        return ['start' => $from, 'end' => $to, 'seconds' => $seconds];
    }

    /**
     * Hours that count toward the DTR for a regular-shift record:
     * time worked minus whatever part of the break it covered.
     * (Overtime has no break - pass $deductBreak = false.)
     */
    public function countedHours(\DateTimeInterface $in, \DateTimeInterface $out, $shiftDate, $deductBreak = true)
    {
        $seconds = max(0, $out->getTimestamp() - $in->getTimestamp());
        if ($deductBreak) {
            $overlap = $this->breakOverlap($in, $out, $shiftDate);
            if ($overlap) {
                $seconds -= $overlap['seconds'];
            }
        }
        return round(max(0, $seconds) / 3600, 2);
    }

    /**
     * The shift that is happening now, or the next one if none is.
     * A shift "is happening" from its start until its overtime window closes.
     *
     * phase: 'before'   - shift has not started yet
     *        'regular'  - inside regular hours
     *        'overtime' - regular hours over, overtime window still open
     */
    public function currentWindow(\DateTimeInterface $now = null)
    {
        $now = $now ? self::imm($now) : new \DateTimeImmutable('now');
        $localToday = $now->setTimezone($this->tz)->setTime(0, 0);

        foreach (['-1 day', '+0 day', '+1 day'] as $offset) {
            $w = $this->windowFor($localToday->modify($offset)->format('Y-m-d'));
            if ($now < $w['overtime_end']) {
                if ($now < $w['start']) {
                    $w['phase'] = 'before';
                } elseif ($now < $w['end']) {
                    $w['phase'] = 'regular';
                } else {
                    $w['phase'] = 'overtime';
                }
                return $w;
            }
        }
        // Unreachable: tomorrow's window always ends in the future.
        throw new \LogicException('Could not resolve shift window');
    }

    /** Local date of $now in the workplace timezone (Y-m-d). */
    public function localDate(\DateTimeInterface $now = null)
    {
        $now = $now ? self::imm($now) : new \DateTimeImmutable('now');
        return $now->setTimezone($this->tz)->format('Y-m-d');
    }

    /** Convert an instant to the DB storage string (Philippine time). */
    public static function toStorage(\DateTimeInterface $dt)
    {
        return self::imm($dt)->setTimezone(self::storageTz())->format('Y-m-d H:i:s');
    }

    /** Parse a DB datetime string (Philippine time) into an instant shown in $tz. */
    public static function fromStorage($value, \DateTimeZone $tz = null)
    {
        $dt = new \DateTimeImmutable($value, self::storageTz());
        return $tz ? $dt->setTimezone($tz) : $dt;
    }

    /** Convert a DB datetime string to 'Y-m-d H:i:s' wall time in this workplace's timezone. */
    public function storageToLocalString($value)
    {
        if (!$value) {
            return $value;
        }
        return self::fromStorage($value, $this->tz)->format('Y-m-d H:i:s');
    }

    /**
     * "10:00 PM", or "6:00 AM (+1)" when $dt falls on the day after $shiftDate.
     */
    public function formatClock(\DateTimeInterface $dt, $shiftDate = null)
    {
        $local = self::imm($dt)->setTimezone($this->tz);
        $label = $local->format('g:i A');
        if ($shiftDate !== null && $local->format('Y-m-d') > $shiftDate) {
            $label .= ' (+1)';
        }
        return $label;
    }

    /** Short timezone label, e.g. "PHT", "JST", "GMT+4". */
    public function timezoneAbbr(\DateTimeInterface $at = null)
    {
        $at = $at ? self::imm($at) : new \DateTimeImmutable('now');
        $abbr = $at->setTimezone($this->tz)->format('T');
        if ($this->tz->getName() === 'Asia/Manila') {
            return 'PHT';
        }
        // PHP returns "+04" for zones without an abbreviation
        return preg_match('/^[+-]\d/', $abbr) ? 'GMT' . $abbr : $abbr;
    }

    public function isPhilippineTime()
    {
        $probe = new \DateTimeImmutable('now');
        return $this->tz->getOffset($probe) === self::storageTz()->getOffset($probe);
    }
}
