<?php
/**
 * FitTrack AI - Setup & Test Script
 * Run this once to verify the installation
 * Access: http://localhost/calories/setup.php
 */
echo '<pre style="font-family:monospace;font-size:14px;padding:20px">';
echo "=== FitTrack AI Setup Check ===\n\n";

// PHP Version
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
echo ($phpOk ? '✅' : '❌') . " PHP: " . PHP_VERSION . " (need 8.0+)\n";

// Extensions
foreach (['pdo', 'pdo_sqlite', 'curl', 'fileinfo', 'json'] as $ext) {
    $ok = extension_loaded($ext);
    echo ($ok ? '✅' : '❌') . " Extension: $ext\n";
}

// Database
echo "\n--- Database ---\n";
$dbDir  = __DIR__ . '/database';
$dbFile = $dbDir . '/fittrack.db';

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
    echo "📁 Created database/ directory\n";
}

try {
    require_once 'config/database.php';
    $db = Database::getInstance();
    echo "✅ Database connection OK\n";

    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
    echo "✅ Tables: " . implode(', ', array_column($tables, 'name')) . "\n";

    $admin = $db->fetch("SELECT email FROM users WHERE role='admin'");
    echo "✅ Admin user: " . ($admin['email'] ?? 'not found') . "\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Upload directory
echo "\n--- Uploads ---\n";
$uploadDir = __DIR__ . '/assets/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    echo "📁 Created uploads/ directory\n";
} else {
    echo "✅ Uploads directory exists\n";
}

if (is_writable($uploadDir)) {
    echo "✅ Uploads directory is writable\n";
} else {
    echo "⚠️  Uploads directory not writable. Run: chmod 755 assets/uploads/\n";
}

// Gemini AI
echo "\n--- AI Configuration ---\n";
require_once 'config/config.php';
if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
    echo "⚠️  Gemini AI not configured.\n";
    echo "   Get a free key: https://aistudio.google.com/app/apikey\n";
    echo "   Set it in config/config.php or as environment variable: GEMINI_API_KEY\n";
} else {
    echo "✅ Gemini AI key configured\n";
}

echo "\n=== Summary ===\n";
echo "🌐 Login: http://localhost/calories/\n";
echo "👤 Admin: admin@fittrack.com / admin123\n";
echo "🔒 Change admin password after first login!\n";

echo "\n✅ Setup complete! Visit the app: <a href='index.php'>Go to FitTrack AI</a>\n";
echo '</pre>';
