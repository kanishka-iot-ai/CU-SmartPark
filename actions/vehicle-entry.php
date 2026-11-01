<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Vehicle Entry & Check-In
 * 
 * Uses strict database transactions to guarantee relational integrity.
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/vehicle-entry.php");
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (Invalid CSRF token).');
    header("Location: ../pages/vehicle-entry.php");
    exit;
}

// 1. Retrieve and sanitize inputs
$regNumber = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim($_POST['registration_number'] ?? '')));
$vehicleType = trim($_POST['vehicle_type'] ?? '');
$ownerName = trim($_POST['owner_name'] ?? '');
$ownerPhone = trim($_POST['owner_phone'] ?? '');
$slotId = (int)($_POST['slot_id'] ?? 0);

$allowedTypes = ['Two Wheeler', 'Car', 'SUV', 'Other'];

// 2. Comprehensive Input Validation
if (empty($regNumber) || strlen($regNumber) < 4) {
    set_flash('danger', 'Please enter a valid vehicle registration number (e.g. KA-01-AB-1234).');
    header("Location: ../pages/vehicle-entry.php");
    exit;
}

if (!in_array($vehicleType, $allowedTypes)) {
    set_flash('danger', 'Please select a valid vehicle category.');
    header("Location: ../pages/vehicle-entry.php");
    exit;
}

if (empty($ownerName)) {
    set_flash('danger', "Please enter the vehicle owner's full name.");
    header("Location: ../pages/vehicle-entry.php");
    exit;
}

if (empty($ownerPhone) || strlen($ownerPhone) < 7) {
    set_flash('danger', 'Please enter a valid contact phone number.');
    header("Location: ../pages/vehicle-entry.php");
    exit;
}

if ($slotId <= 0) {
    set_flash('danger', 'Please choose an available parking slot.');
    header("Location: ../pages/vehicle-entry.php");
    exit;
}

$pdo = get_db_connection();

try {
    // Begin Atomic Database Transaction
    $pdo->beginTransaction();

    // 3. Find or register vehicle in `vehicles` master table
    $vehStmt = $pdo->prepare("SELECT id FROM vehicles WHERE registration_number = ?");
    $vehStmt->execute([$regNumber]);
    $vehicle = $vehStmt->fetch();

    if ($vehicle) {
        $vehicleId = (int)$vehicle['id'];
        // Update contact info and category if changed
        $updateVeh = $pdo->prepare("UPDATE vehicles SET owner_name = ?, owner_phone = ?, vehicle_type = ? WHERE id = ?");
        $updateVeh->execute([$ownerName, $ownerPhone, $vehicleType, $vehicleId]);
    } else {
        $insertVeh = $pdo->prepare("INSERT INTO vehicles (registration_number, owner_name, owner_phone, vehicle_type, created_at) VALUES (?, ?, ?, ?, ?)");
        $insertVeh->execute([$regNumber, $ownerName, $ownerPhone, $vehicleType, date('Y-m-d H:i:s')]);
        $vehicleId = (int)$pdo->lastInsertId();
    }

    // 4. Critical DBMS Check: Ensure vehicle is NOT already parked
    $activeCheck = $pdo->prepare("SELECT ticket_id, slot_id FROM parking_records WHERE vehicle_id = ? AND status = 'Parked'");
    $activeCheck->execute([$vehicleId]);
    $activeRecord = $activeCheck->fetch();

    if ($activeRecord) {
        $pdo->rollBack();
        set_flash('danger', "Vehicle {$regNumber} is already parked in the facility with Ticket #{$activeRecord['ticket_id']}. Cannot create duplicate entry.");
        header("Location: ../pages/vehicle-entry.php");
        exit;
    }

    // 5. Critical DBMS Check: Ensure selected slot is currently Available and valid for vehicle type
    $slotStmt = $pdo->prepare("SELECT id, slot_number, vehicle_type, status FROM parking_slots WHERE id = ?");
    $slotStmt->execute([$slotId]);
    $slot = $slotStmt->fetch();

    if (!$slot) {
        $pdo->rollBack();
        set_flash('danger', 'The selected parking slot does not exist.');
        header("Location: ../pages/vehicle-entry.php");
        exit;
    }

    if ($slot['status'] !== 'Available') {
        $pdo->rollBack();
        set_flash('danger', "Parking slot {$slot['slot_number']} is currently {$slot['status']}. Please choose another available slot.");
        header("Location: ../pages/vehicle-entry.php");
        exit;
    }

    // 6. Generate Unique Ticket ID
    $ticketId = generate_ticket_id();
    $entryTimestamp = date('Y-m-d H:i:s');

    // 7. Update parking slot status to 'Occupied'
    $updateSlot = $pdo->prepare("UPDATE parking_slots SET status = 'Occupied' WHERE id = ?");
    $updateSlot->execute([$slotId]);

    // 8. Insert record into `parking_records`
    $recStmt = $pdo->prepare("INSERT INTO parking_records (ticket_id, vehicle_id, slot_id, entry_time, status, created_at) VALUES (?, ?, ?, ?, 'Parked', ?)");
    $recStmt->execute([$ticketId, $vehicleId, $slotId, $entryTimestamp, $entryTimestamp]);

    // Commit Transaction
    $pdo->commit();

    // Store receipt details in session for immediate ticket popup/view
    $_SESSION['last_ticket'] = [
        'ticket_id'      => $ticketId,
        'reg_number'     => $regNumber,
        'vehicle_type'   => $vehicleType,
        'owner_name'     => $ownerName,
        'owner_phone'    => $ownerPhone,
        'slot_number'    => $slot['slot_number'],
        'entry_time'     => date('d M Y, h:i A', strtotime($entryTimestamp)),
        'parking_lot'    => get_setting('parking_lot_name', 'SmartPark Central'),
        'hourly_rate'    => get_rate_for_type($vehicleType)
    ];

    set_flash('success', "Vehicle {$regNumber} successfully parked in bay {$slot['slot_number']}! Ticket #{$ticketId} issued.");
    header("Location: ../pages/vehicle-entry.php?ticket=" . urlencode($ticketId));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', 'Transaction failed: ' . $e->getMessage());
    header("Location: ../pages/vehicle-entry.php");
    exit;
}
