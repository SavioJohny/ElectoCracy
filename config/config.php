<?php
// Application configuration
define('APP_NAME', 'ElectoCracy');
define('APP_VERSION', '1.0');
define('BASE_URL', 'http://localhost/electo%202.0/');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 8);

// File upload settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_DIR', 'uploads/');
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
