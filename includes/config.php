<?php
// =============================================================
// GREAT SOLOMON MANPOWER SERVICES INC. — CORE TRANSACTION 4
// config.php — Centralised runtime configuration.
// All secrets come from environment variables; no hard-coded
// credentials exist in this file (HostForge resilience rule).
// =============================================================

// --- Load .env file (local/dev only; production uses real env vars) ---
function gsms_load_env_file(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $file = dirname(__DIR__).'/.env';
    if (!is_file($file) || !is_readable($file)) return;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, " \"'");
        if ($name !== '' && getenv($name) === false) {
            putenv($name.'='.$value);
        }
    }
}
gsms_load_env_file();

// --- Ephemeral directory bootstrap (HostForge ephemeral containers) ---
// Auto-create write-dependent directories so the app survives cold starts.
(function (): void {
    $base = dirname(__DIR__);
    foreach (['storage/logs', 'storage/exports', 'storage/reports'] as $dir) {
        $path = $base.'/'.$dir;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }
})();

// --- Database: support both individual DB_* vars and DATABASE_URL ---
// HostForge managed MySQL injects the individual DB_* variables.
// External connections may use a single DATABASE_URL string.
(function (): void {
    $url = getenv('DATABASE_URL');
    if ($url && !getenv('GSMS_DB_HOST')) {
        $p = parse_url($url);
        if ($p !== false) {
            if (!empty($p['host']))     putenv('GSMS_DB_HOST='.urldecode($p['host']));
            if (!empty($p['port']))     putenv('GSMS_DB_PORT='.$p['port']);
            if (!empty($p['user']))     putenv('GSMS_DB_USER='.urldecode($p['user']));
            if (!empty($p['pass']))     putenv('GSMS_DB_PASS='.urldecode($p['pass']));
            if (!empty($p['path']))     putenv('GSMS_DB_NAME='.ltrim(urldecode($p['path']), '/'));
        }
    }
    // HostForge managed MySQL also injects DB_HOST / DB_DATABASE etc.
    // Fall back to those if project-prefixed vars are missing.
    if (!getenv('GSMS_DB_HOST') && getenv('DB_HOST'))     putenv('GSMS_DB_HOST='.getenv('DB_HOST'));
    if (!getenv('GSMS_DB_PORT') && getenv('DB_PORT'))     putenv('GSMS_DB_PORT='.getenv('DB_PORT'));
    if (!getenv('GSMS_DB_NAME') && getenv('DB_DATABASE')) putenv('GSMS_DB_NAME='.getenv('DB_DATABASE'));
    if (!getenv('GSMS_DB_USER') && getenv('DB_USERNAME')) putenv('GSMS_DB_USER='.getenv('DB_USERNAME'));
    if (!getenv('GSMS_DB_PASS') && getenv('DB_PASSWORD')) putenv('GSMS_DB_PASS='.getenv('DB_PASSWORD'));
})();

// --- Application Environment & Debugging ---
define('APP_ENV',   getenv('APP_ENV') ?: (getenv('ENVIRONMENT') ?: 'production'));
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: (APP_ENV !== 'production' ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN));

if (APP_ENV === 'production' && !APP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// --- Resolved DB constants ---
define('DB_HOST', getenv('GSMS_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('GSMS_DB_PORT') ?: 3306));
define('DB_NAME', getenv('GSMS_DB_NAME') ?: 'great_solomon_ct4');
define('DB_USER', getenv('GSMS_DB_USER') ?: 'root');
define('DB_PASS', getenv('GSMS_DB_PASS') ?: '');

// --- Gmail SMTP ---
define('MAIL_HOST',       getenv('GSMS_MAIL_HOST')       ?: 'smtp.gmail.com');
define('MAIL_PORT',  (int)(getenv('GSMS_MAIL_PORT')      ?: 587));
define('MAIL_USERNAME',   getenv('GSMS_MAIL_USERNAME')   ?: 'governancesafety21@gmail.com');
define('MAIL_PASSWORD',   getenv('GSMS_MAIL_PASSWORD')   ?: '');
define('MAIL_FROM_EMAIL', getenv('GSMS_MAIL_FROM_EMAIL') ?: 'governancesafety21@gmail.com');
define('MAIL_FROM_NAME',  getenv('GSMS_MAIL_FROM_NAME')  ?: 'Great Solomon Manpower Services Inc. Core Transaction 4');
define('OTP_SENDER_EMAIL',getenv('GSMS_OTP_SENDER_EMAIL')?: 'governancesafety21@gmail.com');
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_MAX_ATTEMPTS',    5);

// --- Gemini AI ---
// Never expose this to browser JavaScript.
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_API_KEY') ?: '');
define('GEMINI_MODEL',   getenv('GEMINI_MODEL')   ?: 'gemini-2.0-flash');

// --- HTTPS / Secure-cookie detection (HostForge edge proxy terminates TLS) ---
define('APP_HTTPS', (
    (!empty($_SERVER['HTTPS'])                    && $_SERVER['HTTPS']                    !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])   && $_SERVER['HTTP_X_FORWARDED_PROTO']   === 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])     && $_SERVER['HTTP_X_FORWARDED_SSL']     === 'on') ||
    ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
));

// Apply secure session cookie settings on first include.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => APP_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
