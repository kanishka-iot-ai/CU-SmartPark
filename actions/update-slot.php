<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Update Parking Slot
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/parking-slots.php");
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (Invalid CSRF token).');
    header("Location: ../pages/parking-slots.php");
    exit;
}

$id          = (int)($_POST['id'] ?? 0);
$slotNumber  = strtoupper(trim($_POST['slot_number'] ?? ''));
$vehicleType = trim($_POST['vehicle_type'] ?? '');
$status      = trim($_POST['status'] ?? '');

$allowedTypes = ['Two Wheeler', 'Car', 'SUV', 'Other'];
$allowedStatuses = ['Available', 'Occupied', 'Reserved'];

if ($id <= 0 || empty($slotNumber)) {
    set_flash('danger', 'Invalid slot data submitted.');
    header("Location: ../pages/parking-slots.php");
    exit;
}

if (!in_array($vehicleType, $allowedTypes) || !in_array($status, $allowedStatuses)) {
    set_flash('danger', 'Invalid vehicle type or status specified.');
    header("Location: ../pages/parking-slots.php");
    exit;
}

$pdo = get_db_connection();

try {
    // Retrieve current slot details
    $currentStmt = $pdo->prepare("SELECT * FROM parking_slots WHERE id = ?");
    $currentStmt->execute([$id]);
    $current = $currentStmt->fetch();

    if (!$current) {
        set_flash('danger', 'Parking slot not found.');
        header("Location: ../pages/parking-slots.php");
        exit;
    }

    // Check if changing to Available/Reserved when active parked car exists
    if ($current['status'] === 'Occupied' && $status !== 'Occupied') {
        $activeCheck = $pdo->prepare("SELECT id FROM parking_records WHERE slot_id = ? AND status = 'Parked'");
        $activeCheck->execute([$id]);
        if ($activeCheck->fetch()) {
            set_flash('danger', "Cannot set slot '{$current['slot_number']}' to {$status} because a vehicle is currently parked in it. Complete the vehicle exit first.");
            header("Location: ../pages/parking-slots.php");
            exit;
        }
    }

    // Check for duplicate slot number on other slots
    $dupStmt = $pdo->prepare("SELECT id FROM parking_slots WHERE slot_number = ? AND id != ?");
    $dupStmt->execute([$slotNumber, $id]);
    if ($dupStmt->fetch()) {
        set_flash('danger', "Slot number '{$slotNumber}' is already used by another slot.");
        header("Location: ../pages/parking-slots.php");
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE parking_slots SET slot_number = ?, vehicle_type = ?, status = ? WHERE id = ?");
    $updateStmt->execute([$slotNumber, $vehicleType, $status, $id]);

    set_flash('success', "Parking slot '{$slotNumber}' updated successfully.");
} catch (Exception $e) {
    set_flash('danger', 'Database error: ' . $e->getMessage());
}

header("Location: ../pages/parking-slots.php");
exit;
