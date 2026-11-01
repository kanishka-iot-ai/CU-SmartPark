<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Admin Check-In Online Booking Customer
 * 
 * Verifies booking, marks booking as 'Checked In', turns slot to 'Occupied',
 * and creates an active ticket in `parking_records`.
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/bookings.php");
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (Invalid CSRF token).');
    header("Location: ../pages/bookings.php");
    exit;
}

$bookingId = trim($_POST['booking_id'] ?? '');

if (empty($bookingId)) {
    set_flash('danger', 'Please provide a valid Booking ID.');
    header("Location: ../pages/bookings.php");
    exit;
}

$pdo = get_db_connection();

try {
    // Begin Atomic Database Transaction
    $pdo->beginTransaction();

    // 1. Fetch booking with slot details
    $stmt = $pdo->prepare("
        SELECT b.*, s.slot_number, s.status as slot_status
        FROM bookings b
        JOIN parking_slots s ON b.slot_id = s.id
        WHERE b.booking_id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        set_flash('danger', 'Booking reference not found in database.');
        header("Location: ../pages/bookings.php");
        exit;
    }

    // 2. Validate current booking status
    if ($booking['booking_status'] === 'Checked In') {
        $pdo->rollBack();
        set_flash('warning', "Customer with Booking #{$bookingId} is ALREADY checked in.");
        header("Location: ../pages/bookings.php");
        exit;
    }

    if ($booking['booking_status'] === 'Cancelled') {
        $pdo->rollBack();
        set_flash('danger', "Cannot check in: Booking #{$bookingId} was CANCELLED.");
        header("Location: ../pages/bookings.php");
        exit;
    }

    if ($booking['booking_status'] === 'Completed') {
        $pdo->rollBack();
        set_flash('warning', "Cannot check in: Booking #{$bookingId} has already been completed and settled.");
        header("Location: ../pages/bookings.php");
        exit;
    }

    // 3. Check or register vehicle in `vehicles` table
    $vehStmt = $pdo->prepare("SELECT id FROM vehicles WHERE registration_number = ?");
    $vehStmt->execute([$booking['vehicle_number']]);
    $veh = $vehStmt->fetch();

    if ($veh) {
        $vehicleId = (int)$veh['id'];
    } else {
        $insVeh = $pdo->prepare("INSERT INTO vehicles (registration_number, owner_name, owner_phone, vehicle_type, created_at) VALUES (?, ?, ?, ?, ?)");
        $insVeh->execute([$booking['vehicle_number'], $booking['customer_name'], $booking['customer_phone'], $booking['vehicle_type'], date('Y-m-d H:i:s')]);
        $vehicleId = (int)$pdo->lastInsertId();
    }

    // 4. Ensure vehicle is not already parked in another bay
    $activeCheck = $pdo->prepare("SELECT ticket_id, slot_id FROM parking_records WHERE vehicle_id = ? AND status = 'Parked'");
    $activeCheck->execute([$vehicleId]);
    $existing = $activeCheck->fetch();
    if ($existing) {
        $pdo->rollBack();
        set_flash('danger', "Vehicle {$booking['vehicle_number']} already has an active parking session with Ticket #{$existing['ticket_id']}.");
        header("Location: ../pages/bookings.php");
        exit;
    }

    $actualEntryTime = date('Y-m-d H:i:s');
    $ticketId = generate_ticket_id();

    // 5. Update booking status
    $updateBooking = $pdo->prepare("
        UPDATE bookings 
        SET booking_status = 'Checked In', actual_entry_time = ? 
        WHERE id = ?
    ");
    $updateBooking->execute([$actualEntryTime, $booking['id']]);

    // 6. Update parking slot status to 'Occupied'
    $updateSlot = $pdo->prepare("UPDATE parking_slots SET status = 'Occupied' WHERE id = ?");
    $updateSlot->execute([$booking['slot_id']]);

    // 7. Insert into `parking_records` linked with `booking_id`
    $insRecord = $pdo->prepare("
        INSERT INTO parking_records (
            ticket_id, booking_id, vehicle_id, slot_id, entry_time, status, created_at
        ) VALUES (?, ?, ?, ?, ?, 'Parked', ?)
    ");
    $insRecord->execute([
        $ticketId,
        $bookingId,
        $vehicleId,
        $booking['slot_id'],
        $actualEntryTime,
        $actualEntryTime
    ]);

    // Commit Transaction
    $pdo->commit();

    set_flash('success', "Customer {$booking['customer_name']} (Booking #{$bookingId}) checked in successfully! Assigned Bay: {$booking['slot_number']}. Active Ticket #{$ticketId} generated.");
    header("Location: ../pages/bookings.php?filter_status=Checked In");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', 'Check-in failed: ' . $e->getMessage());
    header("Location: ../pages/bookings.php");
    exit;
}
