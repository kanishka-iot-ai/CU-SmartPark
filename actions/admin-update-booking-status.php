<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Admin Update Booking Status (Confirm / Cancel)
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
$newStatus = trim($_POST['new_status'] ?? '');

$allowedStatuses = ['Confirmed', 'Cancelled'];

if (empty($bookingId) || !in_array($newStatus, $allowedStatuses)) {
    set_flash('danger', 'Invalid booking status update parameters.');
    header("Location: ../pages/bookings.php");
    exit;
}

$pdo = get_db_connection();

try {
    $stmt = $pdo->prepare("SELECT id, booking_id, slot_id, booking_status FROM bookings WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        set_flash('danger', 'Booking not found.');
        header("Location: ../pages/bookings.php");
        exit;
    }

    if ($booking['booking_status'] === 'Checked In' && $newStatus === 'Cancelled') {
        set_flash('danger', 'Cannot cancel a booking that has already checked into the bay. Please process vehicle exit.');
        header("Location: ../pages/bookings.php");
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $booking['id']]);

    set_flash('success', "Booking #{$bookingId} status updated to '{$newStatus}'.");
} catch (Exception $e) {
    set_flash('danger', 'Error updating status: ' . $e->getMessage());
}

header("Location: ../pages/bookings.php");
exit;
