<?php
/**
 * INSOPHINIA POS v7 — Configuration
 * ----------------------------------------------------------------------------
 * Copy this file to config.php and fill in real values.
 * NEVER commit config.php to version control.
 */

return [
    // ---- App ----
    'app_name'      => 'INSOPHINIA POS v7',
    'app_env'       => 'production',       // 'production' or 'development'
    'debug'         => false,              // Show detailed errors (dev only)
    'app_url'       => 'http://localhost:8080',
    'app_timezone'  => 'Asia/Colombo',

    // ---- Database (MySQL 8.0+) ----
    'db' => [
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'name'      => 'insophinia_pos',
        'user'      => 'root',
        'pass'      => '',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        // PDO options
        'options'   => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ],
    ],

    // ---- JWT (HS256) ----
    // CHANGE THIS IN PRODUCTION. Use a 64+ character random string.
    // Generate one with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'jwt_secret'        => 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_KEY_AT_LEAST_64_CHARS_LONG_xxxxxxxxxxxxx',
    'jwt_expiration'    => 3600,          // seconds (1 hour)
    'jwt_algorithm'     => 'HS256',

    // ---- Bcrypt ----
    'bcrypt_rounds'     => 12,

    // ---- Password policy ----
    'password_min_len'  => 12,
    'password_max_len'  => 30,

    // ---- Cookies ----
    'cookie_secure'     => false,         // true in production (HTTPS only)
    'cookie_samesite'   => 'Strict',
    'cookie_domain'     => '',
    'cookie_path'       => '/',

    // ---- CSRF ----
    'csrf_token_length' => 32,            // bytes
    'csrf_cookie_name'  => 'csrf_token',
    'csrf_header_name'  => 'X-CSRF-Token',
    'csrf_lifetime'     => 3600,          // seconds

    // ---- Rate Limiting (persistent layer via security_events table) ----
    'rate_limit' => [
        'default_max'      => 100,
        'default_window_ms'=> 60000,
        'endpoints' => [
            'login'           => ['max' => 5,  'window_ms' => 15 * 60 * 1000, 'event' => 'LOGIN_FAILED'],
            'password_change' => ['max' => 3,  'window_ms' => 15 * 60 * 1000, 'event' => 'SUSPICIOUS_ACTIVITY'],
            'marketing_send'  => ['max' => 5,  'window_ms' => 60 * 60 * 1000, 'event' => 'SUSPICIOUS_ACTIVITY'],
            'warranty_claim'  => ['max' => 10, 'window_ms' => 60 * 60 * 1000, 'event' => 'SUSPICIOUS_ACTIVITY'],
        ],
    ],

    // ---- Body size ----
    'max_request_body_size' => 1048576,   // 1MB

    // ---- Session ----
    'session_lifetime'  => 3600,          // 1 hour
    'session_throttle_activity_ms' => 60000,  // update last_activity_at most once per minute

    // ---- Token rotation ----
    'token_rotation_age_seconds' => 1800, // rotate JWT after 30 min

    // ---- Account lockout ----
    'lockout_threshold'     => 5,
    'lockout_duration_sec'  => 900,       // 15 min

    // ---- Email (PHPMailer / SMTP) ----
    'email' => [
        'enabled'   => false,             // Master switch
        'host'      => 'smtp.example.com',
        'port'      => 587,
        'username'  => '',
        'password'  => '',
        'secure'    => 'tls',             // 'tls', 'ssl', or ''
        'from'      => 'INSOPHINIA <noreply@insophinia.lk>',
        'timeout_connection' => 10,
        'timeout_greeting'   => 10,
        'timeout_socket'     => 15,
    ],

    // ---- WhatsApp (click-to-chat, no API key needed) ----
    'whatsapp_enabled' => true,

    // ---- Storage paths (relative to project root) ----
    'storage' => [
        'temp_pdfs' => __DIR__ . '/../storage/temp_pdfs',
        'logs'      => __DIR__ . '/../storage/logs',
        'cache'     => __DIR__ . '/../storage/cache',
    ],

    // ---- Temp PDF cache ----
    'temp_pdf' => [
        'ttl_seconds'   => 600,           // 10 minutes
        'max_entries'   => 100,
        'key_length'    => 16,            // bytes
    ],

    // ---- Marketing bulk send cap ----
    'marketing_bulk_cap' => 500,

    // ---- Settings whitelisted prefixes (PUT /api/settings) ----
    'settings_allowed_prefixes' => ['shop_', 'bill_', 'email_', 'whatsapp_', 'vat_', 'sscl_', 'loyalty_'],
    'settings_blocked_key_regex'=> '/password|secret|api[_-]?key/i',

    // ---- Default seed credentials (used by seed.php) ----
    'seed' => [
        'admin_username' => 'master',
        'admin_password' => 'Master@Admin123!',
        'admin_email'    => 'master@insophinia.lk',
    ],
];
