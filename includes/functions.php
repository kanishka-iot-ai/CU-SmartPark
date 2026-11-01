<?php
/**
 * SmartPark - Helper Functions and Utilities
 */

if (session_status() === PHP_SESSION_NONE) {
    if ((getenv('VERCEL') !== false && getenv('VERCEL') !== '') || isset($_SERVER['VERCEL'])) {
        $tempDir = is_dir('/tmp') ? '/tmp' : (getenv('TMP') ?: getenv('TEMP') ?: sys_get_temp_dir());
        $sessDir = rtrim($tempDir, '/\\') . DIRECTORY_SEPARATOR . 'sessions';
        if (!is_dir($sessDir)) {
            @mkdir($sessDir, 0777, true);
        }
        if (is_dir($sessDir) && is_writable($sessDir)) {
            @session_save_path($sessDir);
        }
    }
    // Secure session cookies
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Sanitize user input for safe HTML display.
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or retrieve CSRF token.
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from POST request.
 */
function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if the current user is authenticated.
 */
function is_authenticated() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Enforce authentication for protected pages.
 */
function require_auth() {
    if (!is_authenticated()) {
        header("Location: ../login.php?error=" . urlencode("Please sign in to access this page."));
        exit;
    }
}

/**
 * Set flash alert message.
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash alert message.
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Get setting value from database.
 */
function get_setting($key, $default = '') {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Retrieve current hourly rate for vehicle type.
 */
function get_rate_for_type($vehicle_type) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT hourly_rate FROM parking_rates WHERE vehicle_type = ?");
        $stmt->execute([$vehicle_type]);
        $row = $stmt->fetch();
        if ($row) {
            return (float)$row['hourly_rate'];
        }
    } catch (Exception $e) {}

    // Fallbacks
    $defaults = [
        'Two Wheeler' => 10.00,
        'Car'         => 20.00,
        'SUV'         => 30.00,
        'Other'       => 25.00
    ];
    return $defaults[$vehicle_type] ?? 20.00;
}

/**
 * Calculate parking duration and fee.
 * 
 * Rules:
 * - Minimum charge: 1 hour.
 * - Partial hours rounded up (ceil).
 * - Total fee = billable hours * rate.
 * 
 * @param string|int $entry_time
 * @param string|int $exit_time
 * @param string $vehicle_type
 * @return array
 */
function calculate_parking_fee($entry_time, $exit_time = null, $vehicle_type = 'Car') {
    $entryTs = is_numeric($entry_time) ? (int)$entry_time : strtotime($entry_time);
    $exitTs  = $exit_time ? (is_numeric($exit_time) ? (int)$exit_time : strtotime($exit_time)) : time();

    // Prevent negative duration if system clocks drift
    $durationSeconds = max(60, $exitTs - $entryTs);
    $durationMinutes = (int)ceil($durationSeconds / 60);

    // Minimum charge is 1 hour; partial hours rounded up
    $billableHours = (int)ceil($durationMinutes / 60);
    if ($billableHours < 1) {
        $billableHours = 1;
    }

    $ratePerHour = get_rate_for_type($vehicle_type);
    $totalFee    = $billableHours * $ratePerHour;

    $hours = floor($durationMinutes / 60);
    $minutes = $durationMinutes % 60;
    $durationText = ($hours > 0 ? $hours . " hr " : "") . $minutes . " min";

    return [
        'duration_minutes' => $durationMinutes,
        'duration_text'    => $durationText,
        'billable_hours'   => $billableHours,
        'rate_per_hour'    => $ratePerHour,
        'total_fee'        => $totalFee,
        'entry_formatted'  => date('d M Y, h:i A', $entryTs),
        'exit_formatted'   => date('d M Y, h:i A', $exitTs)
    ];
}

/**
 * Format currency with symbol.
 */
function format_currency($amount) {
    $symbol = get_setting('currency_symbol', '₹');
    return $symbol . number_format((float)$amount, 2);
}

/**
 * Generate a unique, professional ticket ID.
 */
function generate_ticket_id() {
    return 'SP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

/**
 * Generate a unique, professional online booking ID.
 */
function generate_booking_id() {
    return 'BK-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

/**
 * Retrieve available parking slots for a specific vehicle category and time window.
 * 
 * Enforces concurrency & anti-double-booking rules:
 * 1. Checks that slot matches the vehicle type.
 * 2. Ensures the slot is not currently occupied by an active walk-in/checked-in vehicle.
 * 3. Ensures the slot has no overlapping confirmed/checked-in reservations:
 *    (expected_entry_time < requested_exit AND expected_exit_time > requested_entry)
 * 
 * @param string $vehicleType
 * @param string $entryDatetime ('YYYY-MM-DD HH:MM:SS')
 * @param string $exitDatetime ('YYYY-MM-DD HH:MM:SS')
 * @param string|null $excludeBookingId
 * @return array
 */
function get_available_slots_for_window($vehicleType, $entryDatetime, $exitDatetime, $excludeBookingId = null) {
    $pdo = get_db_connection();

    $sql = "
        SELECT s.id, s.slot_number, s.vehicle_type, s.status
        FROM parking_slots s
        WHERE s.vehicle_type = ?
          AND s.id NOT IN (
              -- 1. Exclude slots with overlapping online reservations
              SELECT b.slot_id 
              FROM bookings b
              WHERE b.booking_status IN ('Confirmed', 'Checked In')
                AND b.expected_entry_time < ?
                AND b.expected_exit_time > ?
    ";
    
    $params = [$vehicleType, $exitDatetime, $entryDatetime];

    if (!empty($excludeBookingId)) {
        $sql .= " AND b.booking_id != ? ";
        $params[] = $excludeBookingId;
    }

    $sql .= "
          )
          AND s.id NOT IN (
              -- 2. Exclude slots currently occupied by active walk-in sessions if booking overlaps with current time
              SELECT r.slot_id
              FROM parking_records r
              WHERE r.status = 'Parked'
          )
        ORDER BY s.slot_number ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

