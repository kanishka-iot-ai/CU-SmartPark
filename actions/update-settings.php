<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Update Settings & Administrator Profile
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/settings.php");
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (Invalid CSRF token).');
    header("Location: ../pages/settings.php");
    exit;
}

$actionType = trim($_POST['action_type'] ?? '');
$pdo = get_db_connection();

try {
    // ----------------------------------------------------
    // 1. Update Hourly Parking Rates
    // ----------------------------------------------------
    if ($actionType === 'update_rates') {
        $rates = $_POST['rates'] ?? [];
        $stmt = $pdo->prepare("UPDATE parking_rates SET hourly_rate = ? WHERE vehicle_type = ?");
        
        foreach ($rates as $type => $rate) {
            $numRate = max(0, (float)$rate);
            $stmt->execute([$numRate, $type]);
        }
        
        set_flash('success', 'Hourly parking tariffs updated successfully.');
    }

    // ----------------------------------------------------
    // 2. Update Facility Info & System Settings
    // ----------------------------------------------------
    elseif ($actionType === 'update_facility') {
        $lotName  = trim($_POST['parking_lot_name'] ?? 'SmartPark Central');
        $phone    = trim($_POST['contact_phone'] ?? '');
        $email    = trim($_POST['contact_email'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $currency = trim($_POST['currency_symbol'] ?? '₹');

        $settingsToUpdate = [
            'parking_lot_name' => $lotName,
            'contact_phone'    => $phone,
            'contact_email'    => $email,
            'address'          => $address,
            'currency_symbol'  => $currency
        ];

        // Check if settings table exists or insert/update
        $upsertStmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        // If driver is sqlite fallback:
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $upsertStmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value
            ");
        }

        foreach ($settingsToUpdate as $key => $val) {
            $upsertStmt->execute([$key, $val]);
        }

        set_flash('success', 'Facility profile & system settings saved successfully.');
    }

    // ----------------------------------------------------
    // 3. Update Admin Profile (Name & Email)
    // ----------------------------------------------------
    elseif ($actionType === 'update_profile') {
        $adminId   = (int)($_SESSION['admin_id'] ?? 1);
        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if (empty($fullName)) {
            set_flash('danger', 'Administrator full name cannot be blank.');
            header("Location: ../pages/settings.php");
            exit;
        }

        $stmt = $pdo->prepare("UPDATE admins SET full_name = ?, email = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $adminId]);

        $_SESSION['admin_name']  = $fullName;
        $_SESSION['admin_email'] = $email;

        set_flash('success', 'Admin account profile updated successfully.');
    }

    // ----------------------------------------------------
    // 4. Update Admin Password
    // ----------------------------------------------------
    elseif ($actionType === 'update_password') {
        $adminId     = (int)($_SESSION['admin_id'] ?? 1);
        $currentPass = trim($_POST['current_password'] ?? '');
        $newPass     = trim($_POST['new_password'] ?? '');
        $confirmPass = trim($_POST['confirm_password'] ?? '');

        if (empty($currentPass) || empty($newPass)) {
            set_flash('danger', 'Please fill in all password fields.');
            header("Location: ../pages/settings.php");
            exit;
        }

        if (strlen($newPass) < 6) {
            set_flash('danger', 'New password must be at least 6 characters long.');
            header("Location: ../pages/settings.php");
            exit;
        }

        if ($newPass !== $confirmPass) {
            set_flash('danger', 'New password and confirmation do not match.');
            header("Location: ../pages/settings.php");
            exit;
        }

        // Verify current password
        $checkStmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
        $checkStmt->execute([$adminId]);
        $row = $checkStmt->fetch();

        if (!$row || !password_verify($currentPass, $row['password'])) {
            set_flash('danger', 'Current password is incorrect. Authorization denied.');
            header("Location: ../pages/settings.php");
            exit;
        }

        // Hash new password using BCRYPT
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $updateStmt->execute([$newHash, $adminId]);

        set_flash('success', 'Administrator password changed successfully.');
    }

} catch (Exception $e) {
    set_flash('danger', 'Error updating settings: ' . $e->getMessage());
}

header("Location: ../pages/settings.php");
exit;
