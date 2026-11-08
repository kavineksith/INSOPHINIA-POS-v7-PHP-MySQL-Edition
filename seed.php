<?php
/**
 * INSOPHINIA POS v7 — Seeder
 * ----------------------------------------------------------------------------
 * Run from CLI:  php seed.php
 *
 * Idempotent — safe to run multiple times. Will:
 *   1. Create the master_admin user (if not exists)
 *   2. Insert 24 default settings (if not exist)
 *   3. Insert 5 sample categories (if not exist)
 *   4. Initialize system_state row (deadman switch)
 *   5. Create the default Terms & Conditions template (if not exists)
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Insophinia\POS\Core\Database;
use Insophinia\POS\Lib\Util;

$config = require __DIR__ . '/config/config.php';
date_default_timezone_set($config['app_timezone'] ?? 'Asia/Colombo');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

echo "🌱 Seeding INSOPHINIA POS v7...\n\n";

try {
    $db = Database::getInstance($config);
    $pdo = $db->getConnection();

    $adminUsername = $config['seed']['admin_username'] ?? 'master';
    $adminPassword = $config['seed']['admin_password'] ?? 'Master@Admin123!';
    $adminEmail    = $config['seed']['admin_email']    ?? 'master@insophinia.lk';

    // ---- 1. Master admin ----
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$adminUsername]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $id = Util::uuid();
        $hash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => $config['bcrypt_rounds']]);
        $stmt = $pdo->prepare(
            'INSERT INTO users (id, username, email, password, role, first_name, last_name, is_active, must_change_password, password_changed_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NOW(), NOW())'
        );
        $stmt->execute([$id, $adminUsername, $adminEmail, $hash, 'master_admin', 'Master', 'Admin']);
        echo "  ✓ Created master_admin: {$adminUsername} / {$adminPassword}\n";
    } else {
        echo "  • Master admin already exists ({$adminUsername})\n";
    }

    // ---- 2. Default settings ----
    $defaultSettings = [
        ['shop_name', 'INSOPHINIA', 'string', 'Shop name shown on bills'],
        ['shop_address', '123 Main Street, Colombo, Sri Lanka', 'string', 'Shop address shown on bills'],
        ['shop_phone', '+94 11 234 5678', 'string', 'Shop phone shown on bills'],
        ['shop_email', 'info@insophinia.lk', 'string', 'Shop email shown on bills'],
        ['shop_website', 'www.insophinia.lk', 'string', 'Shop website shown on bills'],
        ['loyalty_point_value', '100', 'integer', 'Rs. spent per loyalty point earned'],
        ['vat_enabled', 'false', 'boolean', 'Enable 18% VAT on bills'],
        ['vat_rate', '18', 'integer', 'VAT percentage'],
        ['sscl_enabled', 'false', 'boolean', 'Enable 2.5% SSCL tax'],
        ['sscl_rate', '2.5', 'integer', 'SSCL percentage'],
        ['email_enabled', 'false', 'boolean', 'Enable email sending globally'],
        ['email_from', 'INSOPHINIA <noreply@insophinia.lk>', 'string', 'Default From: address'],
        ['bill_a4_show_variants', 'true', 'boolean', 'Show variant specs on A4 bills'],
        ['bill_a4_show_subitems', 'true', 'boolean', 'Show sub-items breakdown on A4 bills'],
        ['bill_a4_show_warranty', 'true', 'boolean', 'Show warranty info on A4 bills'],
        ['bill_a4_show_revisions', 'true', 'boolean', 'Show revisions pricing on A4 bills'],
        ['bill_a4_show_terms', 'true', 'boolean', 'Append terms & conditions to A4 bills'],
        ['bill_a4_active_terms_id', '', 'string', 'ID of the active terms template'],
        ['bill_thermal_show_variants', 'false', 'boolean', 'Show variants on thermal bills'],
        ['bill_thermal_show_subitems', 'false', 'boolean', 'Show sub-items on thermal bills'],
        ['bill_thermal_show_warranty', 'false', 'boolean', 'Show warranty on thermal bills'],
        ['bill_thermal_show_terms', 'false', 'boolean', 'Append T&C to thermal bills'],
        ['whatsapp_enabled', 'true', 'boolean', 'Enable WhatsApp bill sharing'],
        ['whatsapp_default_message', 'Thank you for your purchase! Here is your receipt from {{shop_name}}.', 'string', 'Default WhatsApp message template'],
    ];

    $stmtCheck = $pdo->prepare('SELECT id FROM settings WHERE `key` = ?');
    $stmtInsert = $pdo->prepare(
        'INSERT INTO settings (id, `key`, `value`, `type`, `description`, `created_at`, `updated_at`)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
    );

    $inserted = 0;
    foreach ($defaultSettings as $s) {
        [$key, $value, $type, $desc] = $s;
        $stmtCheck->execute([$key]);
        if (!$stmtCheck->fetch()) {
            $stmtInsert->execute([Util::uuid(), $key, $value, $type, $desc]);
            $inserted++;
        }
    }
    echo "  ✓ Settings verified ({$inserted} new, " . (count($defaultSettings) - $inserted) . " existing)\n";

    // ---- 3. Sample categories ----
    $categories = [
        ['Computer Hardware', 'Laptops, desktops, and components'],
        ['Peripherals', 'Mouse, keyboard, monitor, etc.'],
        ['Networking', 'Routers, switches, cables'],
        ['Software', 'Operating systems, applications, licenses'],
        ['Freelance Services', 'Web development, design, and other freelance deliverables'],
    ];

    $stmtCheck = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
    $stmtInsert = $pdo->prepare(
        'INSERT INTO categories (id, name, description, is_active, `created_at`, `updated_at`)
         VALUES (?, ?, ?, 1, NOW(), NOW())'
    );

    $catInserted = 0;
    foreach ($categories as $c) {
        $stmtCheck->execute([$c[0]]);
        if (!$stmtCheck->fetch()) {
            $stmtInsert->execute([Util::uuid(), $c[0], $c[1]]);
            $catInserted++;
        }
    }
    echo "  ✓ Categories verified ({$catInserted} new)\n";

    // ---- 4. System state ----
    $stmt = $pdo->query('SELECT COUNT(*) FROM system_state');
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO system_state (id, is_locked, deadman_days, last_checkin_at, updated_at) VALUES (?, 0, 72, NOW(), NOW())')
            ->execute([Util::uuid()]);
        echo "  ✓ System state initialized\n";
    } else {
        echo "  • System state already initialized\n";
    }

    // ---- 5. Default terms template ----
    $stmt = $pdo->query('SELECT COUNT(*) FROM terms_templates');
    if ((int)$stmt->fetchColumn() === 0) {
        $termsContent = '<h3>Terms &amp; Conditions</h3>
<ol>
  <li>All goods sold are subject to the manufacturer\'s warranty terms.</li>
  <li>Returns are accepted within 7 days of purchase with original packaging and receipt.</li>
  <li>Software licenses are non-refundable once activated.</li>
  <li>Repair services carry a 30-day workmanship warranty unless otherwise stated.</li>
  <li>Prices are in Sri Lankan Rupees (LKR) and include applicable taxes where noted.</li>
  <li>The shop reserves the right to refuse service in case of suspected fraud or abuse.</li>
</ol>';
        $pdo->prepare(
            'INSERT INTO terms_templates (id, name, format, content, applies_to, is_active, version, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?, ?, 1, 1, NOW(), NOW())'
        )->execute([
            Util::uuid(),
            'Standard Terms & Conditions',
            'html',
            $termsContent,
            '["a4"]'
        ]);
        echo "  ✓ Default terms template created\n";
    } else {
        echo "  • Terms template already exists\n";
    }

    echo "\n✅ Seed complete.\n\n";
    echo "  Login: {$adminUsername} / {$adminPassword}\n";
    echo "  ⚠  Change these credentials in production immediately.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "\n❌ Seed failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
