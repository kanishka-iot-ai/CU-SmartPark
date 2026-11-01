<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Add Parking Slot
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

$slotNumber  = strtoupper(trim($_POST['slot_number'] ?? ''));
$vehicleType = trim($_POST['vehicle_type'] ?? '');
$status      = trim($_POST['status'] ?? 'Available');

$allowedTypes = ['Two Wheeler', 'Car', 'SUV', 'Other'];
$allowedStatuses = ['Available', 'Reserved']; // New slots cannot start as Occupied

if (empty($slotNumber)) {
    set_flash('danger', 'Please provide a valid slot number (e.g. CR-09).');
    header("Location: ../pages/parking-slots.php");
    exit;
}

if (!in_array($vehicleType, $allowedTypes)) {
    set_flash('danger', 'Invalid vehicle type selected.');
    header("Location: ../pages/parking-slots.php");
    exit;
}

if (!in_array($status, $allowedStatuses)) {
    $status = 'Available';
}

$pdo = get_db_connection();

try {
    // Check if slot number is already taken
    $checkStmt = $pdo->prepare("SELECT id FROM parking_slots WHERE slot_number = ?");
    $checkStmt->execute([$slotNumber]);
    if ($checkStmt->fetch()) {
        set_flash('danger', "Slot number '{$slotNumber}' already exists. Please choose a unique slot number.");
        header("Location: ../pages/parking-slots.php");
        exit;
    }

    $insertStmt = $pdo->prepare("INSERT INTO parking_slots (slot_number, vehicle_type, status, created_at) VALUES (?, ?, ?, ?)");
    $insertStmt->execute([$slotNumber, $vehicleType, $status, date('Y-m-d H:i:s')]);

    set_flash('success', "Parking slot '{$slotNumber}' added successfully.");
} catch (Exception $e) {
    set_flash('danger', 'Database error: ' . $e->getMessage());
}

header("Location: ../pages/parking-slots.php");
exit;
