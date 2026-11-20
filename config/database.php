<?php

require_once __DIR__ . '/env.php';

// The whole system runs on Philippine time. Set it here because every page
// loads this file: PHP date()/strtotime() and MySQL NOW()/CURDATE() then agree
// even when the hosting server's clock is UTC.
date_default_timezone_set('Asia/Manila');

// Credentials come from .env (gitignored). See .env.example.
return [
    'host' => env('DB_HOST', 'localhost'),
    'dbname' => env('DB_NAME', 'ojt_db'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASS', ''),
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
    ]
];
