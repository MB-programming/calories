<?php
/**
 * Internationalization (i18n) System
 * Supported languages: ar (Arabic), en (English), de (German)
 */

$GLOBALS['_lang'] = [];

function i18n_init(?Database $db = null): void {
    // Priority: URL param > session > DB preference > default (ar)
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en', 'de'])) {
        $_SESSION['lang'] = $_GET['lang'];
        // Save to DB if logged in
        if ($db && !empty($_SESSION['user_id'])) {
            $db->query(
                "INSERT INTO user_preferences (user_id, language) VALUES (?, ?)
                 ON CONFLICT(user_id) DO UPDATE SET language=excluded.language",
                [$_SESSION['user_id'], $_GET['lang']]
            );
        }
    }

    if (empty($_SESSION['lang'])) {
        // Load from DB if logged in
        if ($db && !empty($_SESSION['user_id'])) {
            $pref = $db->fetch("SELECT language FROM user_preferences WHERE user_id=?", [$_SESSION['user_id']]);
            $_SESSION['lang'] = $pref['language'] ?? 'ar';
        } else {
            $_SESSION['lang'] = 'ar';
        }
    }

    $lang = $_SESSION['lang'];
    $file = __DIR__ . "/../lang/{$lang}.php";
    if (!file_exists($file)) $file = __DIR__ . '/../lang/ar.php';

    $GLOBALS['_lang'] = require $file;
}

/** Translate a key, with optional variable replacement */
function t(string $key, array $vars = []): string {
    $val = $GLOBALS['_lang'][$key] ?? $key;
    foreach ($vars as $k => $v) {
        $val = str_replace(":$k", $v, $val);
    }
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}

/** Get raw translation (no escaping) */
function tr(string $key, array $vars = []): string {
    $val = $GLOBALS['_lang'][$key] ?? $key;
    foreach ($vars as $k => $v) {
        $val = str_replace(":$k", $v, $val);
    }
    return $val;
}

function currentLang(): string {
    return $_SESSION['lang'] ?? 'ar';
}

function isRtl(): bool {
    return currentLang() === 'ar';
}

function langDir(): string {
    return isRtl() ? 'rtl' : 'ltr';
}

/** Output JS translations for frontend use */
function jsTranslations(): string {
    $keys = [
        'loading','saving','saved','error','success','confirm_delete',
        'add','edit','delete','cancel','close','search',
        'ai_thinking','ai_unavailable','no_data',
        'calories','protein','carbs','fat',
        'meal_breakfast','meal_lunch','meal_dinner','meal_snack','meal_other',
        'today','weekly','monthly',
    ];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = $GLOBALS['_lang'][$k] ?? $k;
    }
    return '<script>window.i18n = ' . json_encode($out, JSON_UNESCAPED_UNICODE) . ';</script>';
}
