<?php
define('APP_NAME', 'FitTrack AI');
define('APP_VERSION', '1.0');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '');

// Gemini AI (Free tier - get key from https://aistudio.google.com/app/apikey)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'YOUR_GEMINI_API_KEY_HERE');
define('GEMINI_MODEL', 'gemini-2.0-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');

// Upload settings
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Session
define('SESSION_LIFETIME', 86400 * 7); // 7 days

session_set_cookie_params(SESSION_LIFETIME);
session_start();
