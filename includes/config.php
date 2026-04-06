<?php
// Database Configuration
if ($dbUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL')) {
    $parts = parse_url($dbUrl);
    define('DB_HOST', $parts['host']);
    define('DB_PORT', $parts['port'] ?? '3306');
    define('DB_NAME', ltrim($parts['path'], '/'));
    define('DB_USER', $parts['user']);
    define('DB_PASS', $parts['pass']);
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_NAME', getenv('DB_NAME') ?: 'sadik_app');
    define('DB_USER', getenv('DB_USER') ?: 'school_user');
    define('DB_PASS', getenv('DB_PASS') ?: 'school_pass');
}

// Application Configuration
define('APP_NAME', 'TGCS School Management System');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/schoolmis');
define('school_name', 'Topspring Gems Comprehensive School');
// Recordings Configuration
define('RECORDINGS_DIR', dirname(__DIR__) . '/recordings/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB

// Security Configuration
define('SECURE_SESSION', true);
define('SESSION_LIFETIME', 7 * 24 * 3600); // 7 days in seconds
define('SESSION_COOKIE_NAME', 'APP_SESSION');
define('REMEMBER_ME_DURATION', 30 * 24 * 3600); // 30 days for remember me

// Timezone Configuration
define('APP_TIMEZONE', 'Africa/Lagos'); // Nigerian timezone
date_default_timezone_set(APP_TIMEZONE);

// Date/Time Format Configuration
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'g:i A'); // 12-hour format with AM/PM
define('DATETIME_FORMAT', 'Y-m-d g:i A');

?>
