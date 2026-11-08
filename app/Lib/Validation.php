<?php
/**
 * Validation — Ports the 26 Zod schemas from src/lib/validation.ts.
 * Uses Valitron as the engine, plus custom regexes for username/email/phone/uuid.
 *
 * Usage:
 *   $v = Validation::make($data, Validation::SCHEMA_CREATE_ITEM);
 *   if ($v->fail()) { ... $v->errors() ... }
 *   $clean = $v->validated();
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

use Valitron\Validator;

class Validation
{
    // ---- Schema name constants ----
    public const SCHEMA_LOGIN                 = 'login';
    public const SCHEMA_CHANGE_PASSWORD       = 'change_password';
    public const SCHEMA_CREATE_USER           = 'create_user';
    public const SCHEMA_UPDATE_USER           = 'update_user';
    public const SCHEMA_CREATE_CATEGORY       = 'create_category';
    public const SCHEMA_UPDATE_CATEGORY       = 'update_category';
    public const SCHEMA_CREATE_ITEM           = 'create_item';
    public const SCHEMA_UPDATE_ITEM           = 'update_item';
    public const SCHEMA_CREATE_VARIANT        = 'create_variant';
    public const SCHEMA_UPDATE_VARIANT        = 'update_variant';
    public const SCHEMA_CREATE_SUB_ITEM       = 'create_sub_item';
    public const SCHEMA_UPDATE_SUB_ITEM       = 'update_sub_item';
    public const SCHEMA_CREATE_CUSTOMER       = 'create_customer';
    public const SCHEMA_UPDATE_CUSTOMER       = 'update_customer';
    public const SCHEMA_CREATE_BILL           = 'create_bill';
    public const SCHEMA_RETURN_BILL           = 'return_bill';
    public const SCHEMA_CANCEL_BILL           = 'cancel_bill';
    public const SCHEMA_SEND_BILL             = 'send_bill';
    public const SCHEMA_CREATE_PROMOTION      = 'create_promotion';
    public const SCHEMA_UPDATE_PROMOTION      = 'update_promotion';
    public const SCHEMA_STOCK_MOVEMENT        = 'stock_movement';
    public const SCHEMA_CREATE_WARRANTY_CLAIM = 'create_warranty_claim';
    public const SCHEMA_REVIEW_WARRANTY_CLAIM = 'review_warranty_claim';
    public const SCHEMA_CREATE_REPAIR_JOB     = 'create_repair_job';
    public const SCHEMA_UPDATE_REPAIR_JOB     = 'update_repair_job';
    public const SCHEMA_REPAIR_PART           = 'repair_part';
    public const SCHEMA_CREATE_EMAIL_TEMPLATE = 'create_email_template';
    public const SCHEMA_UPDATE_EMAIL_TEMPLATE = 'update_email_template';
    public const SCHEMA_CREATE_CAMPAIGN       = 'create_campaign';
    public const SCHEMA_UPDATE_CAMPAIGN       = 'update_campaign';
    public const SCHEMA_CREATE_TERMS_TEMPLATE = 'create_terms_template';
    public const SCHEMA_UPDATE_TERMS_TEMPLATE = 'update_terms_template';
    public const SCHEMA_UPDATE_SETTINGS       = 'update_settings';

    // ---- Regex patterns (ported from validation.ts) ----
    public const UUID_REGEX  = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    public const PHONE_REGEX = '/^\+?(\d{1,4})?[-. ]?\(?\d{1,4}?\)?[-. ]?\d{1,4}[-. ]?\d{1,9}$/';
    public const USERNAME_REGEX = '/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/';

    // ---- Email validation (custom; mirrors the Zod refine in validation.ts) ----
    public static function isValidEmail(?string $email): bool
    {
        if ($email === null || $email === '') return false;
        if (strlen($email) > 254) return false;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        $parts = explode('@', $email);
        if (count($parts) !== 2) return false;
        [$local, $domain] = $parts;
        if (strlen($local) > 64) return false;
        if (str_contains($local, '..')) return false;
        $domainLabels = explode('.', $domain);
        foreach ($domainLabels as $label) {
            if ($label === '' || strlen($label) > 63) return false;
        }
        return true;
    }

    public static function isValidUuid(?string $value): bool
    {
        if ($value === null || $value === '') return false;
        return (bool)preg_match(self::UUID_REGEX, $value);
    }

    public static function isValidPhone(?string $value): bool
    {
        if ($value === null || $value === '') return false;
        return (bool)preg_match(self::PHONE_REGEX, $value);
    }

    public static function isValidUsername(?string $value): bool
    {
        if ($value === null) return false;
        $lower = strtolower(trim($value));
        if (strlen($lower) < 3 || strlen($lower) > 32) return false;
        return (bool)preg_match(self::USERNAME_REGEX, $lower);
    }

    // ---- Sanitizers ----
    public static function sanitizeString(mixed $input): string
    {
        return Util::sanitizeString($input);
    }

    public static function sanitizeTemplateHtml(string $html): string
    {
        return Util::sanitizeTemplateHtml($html);
    }

    // ---- Builder ----

    /**
     * Validate $data against a known schema. Returns a result object with:
     *   - success(bool)
     *   - errors(array<string,string[]>)
     *   - data(array) — sanitized/normalized values
     */
    public static function validate(string $schema, array $data): array
    {
        $v = new Validator($data);
        // Common rule presets
        self::applyRules($v, $schema);

        if (!$v->validate()) {
            return ['success' => false, 'errors' => $v->errors(), 'data' => null];
        }
        $clean = self::normalize($schema, $data);
        return ['success' => true, 'errors' => null, 'data' => $clean];
    }

    private static function applyRules(Validator $v, string $schema): void
    {
        switch ($schema) {
            case self::SCHEMA_LOGIN:
                $v->rule('required', ['username', 'password']);
                $v->rule('string', ['username', 'password']);
                $v->rule('lengthMax', 'password', 30);
                $v->rule(fn($field, $value) => self::isValidUsername($value), 'username')->message('Username format is invalid');
                break;

            case self::SCHEMA_CHANGE_PASSWORD:
                $v->rule('required', ['current_password', 'new_password', 'confirm_password']);
                $v->rule('lengthMin', 'new_password', 12);
                $v->rule('lengthMax', 'new_password', 30);
                $v->rule(fn($f, $value, $params, $fields) => $value === $fields['confirm_password'], 'new_password')->message('New password and confirmation do not match');
                break;

            case self::SCHEMA_CREATE_USER:
                $v->rule('required', ['username', 'email', 'password', 'role', 'first_name', 'last_name']);
                $v->rule('in', 'role', ['admin', 'cashier', 'supervisor']);
                $v->rule('lengthMin', 'password', 12);
                $v->rule('lengthMax', 'password', 30);
                $v->rule(fn($f, $value) => self::isValidUsername($value), 'username')->message('Invalid username');
                $v->rule(fn($f, $value) => self::isValidEmail($value), 'email')->message('Invalid email');
                $v->rule('lengthMax', 'first_name', 100);
                $v->rule('lengthMax', 'last_name', 100);
                break;

            case self::SCHEMA_UPDATE_USER:
                $v->rule('optional', ['username', 'email', 'password', 'role', 'first_name', 'last_name', 'is_active', 'locked_until']);
                $v->rule('in', 'role', ['admin', 'cashier', 'supervisor']);
                if (!empty($data['password'])) {
                    $v->rule('lengthMin', 'password', 12);
                    $v->rule('lengthMax', 'password', 30);
                }
                break;

            case self::SCHEMA_CREATE_CATEGORY:
                $v->rule('required', ['name']);
                $v->rule('lengthMin', 'name', 1);
                $v->rule('lengthMax', 'name', 255);
                $v->rule('optional', 'description');
                $v->rule('lengthMax', 'description', 1000);
                break;

            case self::SCHEMA_UPDATE_CATEGORY:
                $v->rule('optional', ['name', 'description']);
                $v->rule('lengthMax', 'name', 255);
                $v->rule('lengthMax', 'description', 1000);
                break;

            case self::SCHEMA_CREATE_ITEM:
                $v->rule('required', ['plu_code', 'name', 'category_id', 'price', 'cost_price']);
                $v->rule('lengthMax', 'plu_code', 50);
                $v->rule('lengthMax', 'name', 255);
                $v->rule('lengthMax', 'description', 2000);
                $v->rule(fn($f, $value) => self::isValidUuid($value), 'category_id')->message('Invalid category_id');
                $v->rule('numeric', ['price', 'cost_price', 'stock_quantity', 'low_stock_threshold', 'discount_percentage']);
                $v->rule('min', 'price', 0);
                $v->rule('min', 'cost_price', 0);
                $v->rule('min', 'stock_quantity', 0);
                $v->rule('min', 'discount_percentage', 0);
                $v->rule('max', 'discount_percentage', 100);
                $v->rule('integer', 'warranty_days');
                $v->rule('min', 'warranty_days', 0);
                $v->rule('max', 'warranty_days', 3650);
                $v->rule('in', 'item_type', ['product', 'service', 'composite']);
                $v->rule('lengthMax', 'barcode', 100);
                $v->rule('lengthMax', 'qr_code', 100);
                $v->rule('lengthMax', 'unit', 50);
                break;

            case self::SCHEMA_UPDATE_ITEM:
                $v->rule('optional', ['plu_code', 'name', 'description', 'category_id', 'barcode', 'qr_code', 'price', 'cost_price', 'unit', 'stock_quantity', 'low_stock_threshold', 'has_discount', 'discount_percentage', 'warranty_days', 'item_type', 'is_active']);
                if (!empty($data['category_id'])) {
                    $v->rule(fn($f, $value) => self::isValidUuid($value), 'category_id')->message('Invalid category_id');
                }
                $v->rule('in', 'item_type', ['product', 'service', 'composite']);
                $v->rule('numeric', ['price', 'cost_price', 'stock_quantity', 'low_stock_threshold', 'discount_percentage']);
                $v->rule('integer', 'warranty_days');
                break;

            case self::SCHEMA_CREATE_VARIANT:
                $v->rule('required', ['item_id', 'sku', 'name']);
                $v->rule(fn($f, $value) => self::isValidUuid($value), 'item_id')->message('Invalid item_id');
                $v->rule('lengthMax', 'sku', 100);
                $v->rule('lengthMax', 'name', 255);
                $v->rule('numeric', ['price_adjustment', 'stock_quantity']);
                $v->rule('min', 'price_adjustment', 0);
                $v->rule('min', 'stock_quantity', 0);
                $v->rule('integer', 'warranty_days');
                $v->rule('min', 'warranty_days', 0);
                $v->rule('max', 'warranty_days', 3650);
                break;

            case self::SCHEMA_UPDATE_VARIANT:
                $v->rule('optional', ['sku', 'name', 'price_adjustment', 'stock_quantity', 'is_active', 'specs', 'warranty_days']);
                $v->rule('numeric', ['price_adjustment', 'stock_quantity']);
                break;

            case self::SCHEMA_CREATE_SUB_ITEM:
                $v->rule('required', ['item_id', 'name', 'base_price']);
                $v->rule(fn($f, $value) => self::isValidUuid($value), 'item_id')->message('Invalid item_id');
                $v->rule('lengthMax', 'name', 255);
                $v->rule('numeric', ['base_price', 'revision_price', 'estimated_hours']);
                $v->rule('integer', 'revisions_included');
                $v->rule('integer', 'sort_order');
                break;

            case self::SCHEMA_UPDATE_SUB_ITEM:
                $v->rule('optional', ['name', 'description', 'base_price', 'revisions_included', 'revision_price', 'estimated_hours', 'is_active', 'sort_order']);
                $v->rule('numeric', ['base_price', 'revision_price', 'estimated_hours']);
                break;

            case self::SCHEMA_CREATE_CUSTOMER:
                $v->rule('required', ['name']);
                $v->rule('lengthMax', 'name', 255);
                $v->rule('lengthMax', 'address', 500);
                if (!empty($data['phone'])) {
                    $v->rule(fn($f, $value) => self::isValidPhone($value), 'phone')->message('Invalid phone');
                }
                if (!empty($data['email'])) {
                    $v->rule(fn($f, $value) => self::isValidEmail($value), 'email')->message('Invalid email');
                }
                $v->rule('integer', 'loyalty_points');
                $v->rule('min', 'loyalty_points', 0);
                break;

            case self::SCHEMA_UPDATE_CUSTOMER:
                $v->rule('optional', ['name', 'phone', 'email', 'address', 'loyalty_points', 'marketing_opt_in']);
                if (!empty($data['phone'])) {
                    $v->rule(fn($f, $value) => self::isValidPhone($value), 'phone')->message('Invalid phone');
                }
                if (!empty($data['email'])) {
                    $v->rule(fn($f, $value) => self::isValidEmail($value), 'email')->message('Invalid email');
                }
                break;

            case self::SCHEMA_CREATE_BILL:
                $v->rule('required', ['items', 'paid_amount']);
                $v->rule('array', 'items');
                $v->rule('min', 'items', 1);
                $v->rule('numeric', 'paid_amount');
                $v->rule('min', 'paid_amount', 0);
                $v->rule('integer', 'points_redeemed');
                $v->rule('min', 'points_redeemed', 0);
                $v->rule('in', 'print_format', ['thermal', 'a4']);
                if (!empty($data['customer_email'])) {
                    $v->rule(fn($f, $value) => self::isValidEmail($value), 'customer_email')->message('Invalid customer email');
                }
                // Validate each item
                if (is_array($data['items'] ?? null)) {
                    foreach ($data['items'] as $i => $item) {
                        $v->rule(fn($f, $value) => self::isValidUuid($value), "items.{$i}.item_id")->message("Item {$i}: invalid item_id");
                        $v->rule('numeric', "items.{$i}.quantity");
                        $v->rule('min', "items.{$i}.quantity", 0.001);
                        $v->rule('integer', "items.{$i}.revisions_purchased");
                        $v->rule('min', "items.{$i}.revisions_purchased", 0);
                        $v->rule('max', "items.{$i}.revisions_purchased", 100);
                    }
                }
                break;

            case self::SCHEMA_SEND_BILL:
                $v->rule('required', ['channel']);
                $v->rule('in', 'channel', ['whatsapp_text', 'whatsapp_pdf', 'email_text', 'email_pdf']);
                if (!empty($data['recipient_email'])) {
                    $v->rule(fn($f, $value) => self::isValidEmail($value), 'recipient_email')->message('Invalid recipient email');
                }
                $v->rule('lengthMax', 'custom_message', 2000);
                if (!empty($data['template_id'])) {
                    $v->rule(fn($f, $value) => self::isValidUuid($value), 'template_id')->message('Invalid template_id');
                }
                break;

            case self::SCHEMA_CREATE_WARRANTY_CLAIM:
                $v->rule('required', ['warranty_id', 'issue_description']);
                $v->rule(fn($f, $value) => self::isValidUuid($value), 'warranty_id')->message('Invalid warranty_id');
                $v->rule('lengthMin', 'issue_description', 1);
                $v->rule('lengthMax', 'issue_description', 2000);
                $v->rule('in', 'fault_category', ['hardware', 'software', 'power', 'display', 'battery', 'network', 'other']);
                break;

            case self::SCHEMA_REVIEW_WARRANTY_CLAIM:
                $v->rule('required', ['status']);
                $v->rule('in', 'status', ['under_review', 'approved', 'rejected', 'repaired', 'replaced', 'closed']);
                $v->rule('optional', ['assessment_notes', 'resolution_notes', 'resolution_type']);
                $v->rule('in', 'resolution_type', ['repair', 'replace', 'refund', 'rejected']);
                break;

            case self::SCHEMA_CREATE_REPAIR_JOB:
                $v->rule('required', ['customer_name', 'item_description', 'fault_description']);
                $v->rule('lengthMax', 'customer_name', 255);
                $v->rule('lengthMax', 'item_description', 500);
                $v->rule('lengthMax', 'fault_description', 2000);
                $v->rule('numeric', ['estimated_cost', 'estimated_hours', 'labor_cost']);
                $v->rule('min', 'estimated_cost', 0);
                $v->rule('integer', 'repair_warranty_days');
                $v->rule('min', 'repair_warranty_days', 0);
                $v->rule('max', 'repair_warranty_days', 3650);
                if (!empty($data['customer_email'])) {
                    $v->rule(fn($f, $value) => self::isValidEmail($value), 'customer_email')->message('Invalid email');
                }
                if (!empty($data['item_id'])) {
                    $v->rule(fn($f, $value) => self::isValidUuid($value), 'item_id')->message('Invalid item_id');
                }
                if (!empty($data['technician_id'])) {
                    $v->rule(fn($f, $value) => self::isValidUuid($value), 'technician_id')->message('Invalid technician_id');
                }
                break;

            case self::SCHEMA_UPDATE_REPAIR_JOB:
                $v->rule('optional', ['status', 'technician_id', 'estimated_cost', 'final_cost', 'estimated_hours', 'actual_hours', 'labor_cost', 'parts_cost', 'total_cost', 'started_at', 'completed_at', 'delivered_at', 'notes', 'assessment_notes']);
                $v->rule('in', 'status', ['received', 'in_progress', 'awaiting_parts', 'ready_for_pickup', 'completed', 'cancelled', 'returned_unrepaired']);
                break;

            case self::SCHEMA_REPAIR_PART:
                $v->rule('required', ['part_name']);
                $v->rule('lengthMax', 'part_name', 255);
                $v->rule('numeric', ['quantity', 'unit_price']);
                $v->rule('min', 'quantity', 0.001);
                $v->rule('min', 'unit_price', 0);
                break;

            case self::SCHEMA_CREATE_EMAIL_TEMPLATE:
                $v->rule('required', ['name', 'subject', 'html_body']);
                $v->rule('lengthMax', 'name', 255);
                $v->rule('lengthMax', 'subject', 200);
                $v->rule(fn($f, $value) => !str_contains($value, "\n") && !str_contains($value, "\r"), 'subject')->message('Subject must not contain newlines');
                $v->rule('lengthMin', 'html_body', 1);
                $v->rule('lengthMax', 'html_body', 50000);
                $v->rule('in', 'type', ['bill', 'marketing', 'warranty', 'repair']);
                if (!empty($data['text_body'])) {
                    $v->rule('lengthMax', 'text_body', 20000);
                }
                if (!empty($data['default_variables'])) {
                    $v->rule('lengthMax', 'default_variables', 5000);
                }
                break;

            case self::SCHEMA_UPDATE_EMAIL_TEMPLATE:
                $v->rule('optional', ['name', 'subject', 'html_body', 'text_body', 'default_variables', 'is_active', 'type']);
                $v->rule('in', 'type', ['bill', 'marketing', 'warranty', 'repair']);
                break;

            case self::SCHEMA_CREATE_CAMPAIGN:
                $v->rule('required', ['name', 'template_id']);
                $v->rule('lengthMax', 'name', 255);
                $v->rule(fn($f, $value) => self::isValidUuid($value), 'template_id')->message('Invalid template_id');
                $v->rule('in', 'audience_type', ['single', 'bulk']);
                if (!empty($data['recipient_email'])) {
                    $v->rule(fn($f, $value) => self::isValidEmail($value), 'recipient_email')->message('Invalid recipient_email');
                }
                if (!empty($data['subject_override'])) {
                    $v->rule(fn($f, $value) => !str_contains($value, "\n"), 'subject_override')->message('Subject must not contain newlines');
                }
                $v->rule('lengthMax', 'audience_filter', 5000);
                $v->rule('lengthMax', 'custom_variables', 5000);
                break;

            case self::SCHEMA_UPDATE_CAMPAIGN:
                $v->rule('optional', ['name', 'template_id', 'audience_type', 'audience_filter', 'recipient_email', 'recipient_name', 'subject_override', 'custom_variables', 'scheduled_at']);
                $v->rule('in', 'audience_type', ['single', 'bulk']);
                break;

            case self::SCHEMA_CREATE_TERMS_TEMPLATE:
                $v->rule('required', ['name', 'content']);
                $v->rule('lengthMax', 'name', 255);
                $v->rule('lengthMin', 'content', 1);
                $v->rule('lengthMax', 'content', 50000);
                $v->rule('in', 'format', ['html', 'markdown']);
                break;

            case self::SCHEMA_UPDATE_TERMS_TEMPLATE:
                $v->rule('optional', ['name', 'format', 'content', 'applies_to', 'is_active']);
                $v->rule('in', 'format', ['html', 'markdown']);
                break;

            case self::SCHEMA_UPDATE_SETTINGS:
                // No required keys; values must be string|int|float|bool
                foreach ($data as $key => $value) {
                    if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
                        $v->error($key, "Value must be string, number, or boolean");
                    }
                }
                break;

            case self::SCHEMA_RETURN_BILL:
            case self::SCHEMA_CANCEL_BILL:
                $v->rule('required', ['reason', 'authorizer_username', 'authorizer_password']);
                $v->rule('lengthMin', 'reason', 1);
                $v->rule('lengthMax', 'reason', 1000);
                break;

            case self::SCHEMA_STOCK_MOVEMENT:
                $v->rule('required', ['item_id', 'quantity']);
                $v->rule(fn($f, $value) => self::isValidUuid($value), 'item_id')->message('Invalid item_id');
                $v->rule('numeric', 'quantity');
                $v->rule('min', 'quantity', 0.001);
                break;

            case self::SCHEMA_CREATE_PROMOTION:
            case self::SCHEMA_UPDATE_PROMOTION:
                $v->rule('required', ['name', 'discount_type', 'discount_value', 'start_date', 'end_date']);
                $v->rule('in', 'discount_type', ['percentage', 'fixed']);
                $v->rule('numeric', 'discount_value');
                $v->rule('min', 'discount_value', 0);
                $v->rule('integer', 'min_quantity');
                $v->rule('min', 'min_quantity', 1);
                break;

            default:
                // Unknown schema — no rules
                break;
        }
    }

    private static function normalize(string $schema, array $data): array
    {
        $clean = [];
        // Apply sanitization to known string fields
        $sanitizeFields = match ($schema) {
            self::SCHEMA_CREATE_CATEGORY, self::SCHEMA_UPDATE_CATEGORY => ['name', 'description'],
            self::SCHEMA_CREATE_ITEM, self::SCHEMA_UPDATE_ITEM => ['name', 'description', 'unit'],
            self::SCHEMA_CREATE_VARIANT, self::SCHEMA_UPDATE_VARIANT => ['sku', 'name'],
            self::SCHEMA_CREATE_SUB_ITEM, self::SCHEMA_UPDATE_SUB_ITEM => ['name', 'description'],
            self::SCHEMA_CREATE_CUSTOMER, self::SCHEMA_UPDATE_CUSTOMER => ['name', 'address'],
            self::SCHEMA_CREATE_REPAIR_JOB, self::SCHEMA_UPDATE_REPAIR_JOB => ['customer_name', 'item_description', 'fault_description', 'access_password', 'accessories', 'condition_notes', 'scope_of_work', 'notes'],
            self::SCHEMA_CREATE_WARRANTY_CLAIM => ['issue_description'],
            self::SCHEMA_CREATE_EMAIL_TEMPLATE, self::SCHEMA_UPDATE_EMAIL_TEMPLATE => ['name', 'subject', 'text_body', 'default_variables'],
            self::SCHEMA_CREATE_CAMPAIGN, self::SCHEMA_UPDATE_CAMPAIGN => ['name', 'recipient_name', 'subject_override', 'audience_filter', 'custom_variables'],
            self::SCHEMA_CREATE_TERMS_TEMPLATE, self::SCHEMA_UPDATE_TERMS_TEMPLATE => ['name', 'content'],
            default => [],
        };
        foreach ($sanitizeFields as $f) {
            if (isset($data[$f]) && is_string($data[$f])) {
                $data[$f] = self::sanitizeString($data[$f]);
            }
        }
        // Sanitize template HTML
        if ($schema === self::SCHEMA_CREATE_EMAIL_TEMPLATE || $schema === self::SCHEMA_UPDATE_EMAIL_TEMPLATE) {
            if (!empty($data['html_body'])) {
                $data['html_body'] = self::sanitizeTemplateHtml($data['html_body']);
            }
        }
        if (($schema === self::SCHEMA_CREATE_TERMS_TEMPLATE || $schema === self::SCHEMA_UPDATE_TERMS_TEMPLATE)
            && !empty($data['format']) && $data['format'] === 'html' && !empty($data['content'])) {
            $data['content'] = self::sanitizeTemplateHtml($data['content']);
        }
        return $data;
    }

    /** Helper: format Valitron errors into a flat {field: [messages]} object. */
    public static function formatErrors(array $errors): array
    {
        $flat = [];
        foreach ($errors as $field => $messages) {
            $flat[$field] = is_array($messages) ? $messages : [$messages];
        }
        return $flat;
    }
}
