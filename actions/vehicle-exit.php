<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Process Vehicle Exit & Settle Payment
 * 
 * Uses strict transactions to update records, free slots, and record payments.
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/vehicle-exit.php");
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (Invalid CSRF token).');
    header("Location: ../pages/vehicle-exit.php");
    exit;
}

$recordId      = (int)($_POST['record_id'] ?? 0);
$paymentMethod = trim($_POST['payment_method'] ?? 'Cash');

$allowedMethods = ['Cash', 'UPI', 'Card'];
if (!in_array($paymentMethod, $allowedMethods)) {
    $paymentMethod = 'Cash';
}

if ($recordId <= 0) {
    set_flash('danger', 'Invalid parking record reference.');
    header("Location: ../pages/vehicle-exit.php");
    exit;
}

$pdo = get_db_connection();

try {
    // Begin Atomic Database Transaction
    $pdo->beginTransaction();

    // 1. Retrieve the active parking record with JOIN
    $stmt = $pdo->prepare("
        SELECT r.id, r.ticket_id, r.booking_id, r.vehicle_id, r.slot_id, r.entry_time, r.status,
               v.registration_number, v.owner_name, v.owner_phone, v.vehicle_type,
               s.slot_number
        FROM parking_records r
        JOIN vehicles v ON r.vehicle_id = v.id
        JOIN parking_slots s ON r.slot_id = s.id
        WHERE r.id = ?
    ");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch();

    if (!$record) {
        $pdo->rollBack();
        set_flash('danger', 'Parking record not found.');
        header("Location: ../pages/vehicle-exit.php");
        exit;
    }

    // 2. Prevent duplicate checkout transactions
    if ($record['status'] !== 'Parked') {
        $pdo->rollBack();
        set_flash('danger', "This parking session (Ticket #{$record['ticket_id']}) has already been completed. Duplicate checkout prevented.");
        header("Location: ../pages/vehicle-exit.php");
        exit;
    }

    // 3. Compute accurate parking duration and fees
    $exitTimestamp = date('Y-m-d H:i:s');
    $calculation   = calculate_parking_fee($record['entry_time'], $exitTimestamp, $record['vehicle_type']);

    $durationMinutes = $calculation['duration_minutes'];
    $totalFee        = $calculation['total_fee'];

    // 4. Update `parking_records` table
    $updateRec = $pdo->prepare("
        UPDATE parking_records 
        SET exit_time = ?, duration_minutes = ?, total_fee = ?, status = 'Completed' 
        WHERE id = ?
    ");
    $updateRec->execute([$exitTimestamp, $durationMinutes, $totalFee, $recordId]);

    // 5. Update `parking_slots` table to set slot status back to 'Available'
    $updateSlot = $pdo->prepare("UPDATE parking_slots SET status = 'Available' WHERE id = ?");
    $updateSlot->execute([$record['slot_id']]);

    // 6. Record payment in `payments` table
    $insertPay = $pdo->prepare("
        INSERT INTO payments (parking_record_id, amount, payment_method, payment_status, payment_date) 
        VALUES (?, ?, ?, 'Paid', ?)
    ");
    $insertPay->execute([$recordId, $totalFee, $paymentMethod, $exitTimestamp]);

    // 7. If linked to an online reservation, update `bookings` table as Completed
    $estimatedPrice = null;
    if (!empty($record['booking_id'])) {
        $bookingStmt = $pdo->prepare("SELECT estimated_amount FROM bookings WHERE booking_id = ?");
        $bookingStmt->execute([$record['booking_id']]);
        $bk = $bookingStmt->fetch();
        if ($bk) {
            $estimatedPrice = (float)$bk['estimated_amount'];
        }

        $updateBooking = $pdo->prepare("
            UPDATE bookings 
            SET actual_exit_time = ?, final_amount = ?, booking_status = 'Completed' 
            WHERE booking_id = ?
        ");
        $updateBooking->execute([$exitTimestamp, $totalFee, $record['booking_id']]);
    }

    // Commit Transaction
    $pdo->commit();

    // Store exit receipt in session
    $_SESSION['exit_receipt'] = [
        'ticket_id'        => $record['ticket_id'],
        'booking_id'       => $record['booking_id'],
        'estimated_price'  => $estimatedPrice,
        'reg_number'       => $record['registration_number'],
        'vehicle_type'     => $record['vehicle_type'],
        'owner_name'       => $record['owner_name'],
        'slot_number'      => $record['slot_number'],
        'entry_time'       => date('d M Y, h:i A', strtotime($record['entry_time'])),
        'exit_time'        => date('d M Y, h:i A', strtotime($exitTimestamp)),
        'duration_text'    => $calculation['duration_text'],
        'billable_hours'   => $calculation['billable_hours'],
        'rate_per_hour'    => $calculation['rate_per_hour'],
        'total_fee'        => $totalFee,
        'payment_method'   => $paymentMethod,
        'parking_lot'      => get_setting('parking_lot_name', 'SmartPark Central')
    ];

    set_flash('success', "Vehicle {$record['registration_number']} checked out successfully! Payment of " . format_currency($totalFee) . " recorded via {$paymentMethod}. Bay {$record['slot_number']} is now Available.");
    header("Location: ../pages/vehicle-exit.php?completed=" . urlencode($record['ticket_id']));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', 'Exit transaction failed: ' . $e->getMessage());
    header("Location: ../pages/vehicle-exit.php");
    exit;
}
