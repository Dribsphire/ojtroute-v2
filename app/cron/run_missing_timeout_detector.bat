@echo off
REM =====================================================
REM Windows Task Scheduler Script
REM Run Missing Timeout Detection (schedule it every hour)
REM =====================================================

REM Set PHP executable path (adjust if needed)
SET PHP_PATH=C:\xampp\php\php.exe

REM Paths are relative to this .bat file, so it works whatever the project folder is called
SET SCRIPT_PATH=%~dp0detect_missing_timeouts.php
SET LOG_DIR=%~dp0..\..\storage\logs

REM Run the script
"%PHP_PATH%" "%SCRIPT_PATH%"

REM Optional: Log the execution
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"
echo [%date% %time%] Missing timeout detection executed >> "%LOG_DIR%\cron_execution.log"
