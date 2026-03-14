<?php
define('APP_NAME', 'FitTrack AI');
define('APP_VERSION', '1.1');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '');

// Upload settings
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Session
define('SESSION_LIFETIME', 86400 * 7); // 7 days

session_set_cookie_params(SESSION_LIFETIME);
session_start();

// Load i18n (must be after session_start)
require_once __DIR__ . '/i18n.php';
// Load AI providers
require_once __DIR__ . '/ai_providers.php';
