<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Customer Online Booking Cancellation
 */

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../booking-lookup.php");
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (Invalid CSRF token).');
    header("Location: ../booking-lookup.php");
    exit;
}

$bookingId = trim($_POST['booking_id'] ?? '');
$phone     = trim($_POST['customer_phone'] ?? '');

if (empty($bookingId) || empty($phone)) {
    set_flash('danger', 'Please provide both Booking ID and Phone number to verify cancellation authorization.');
    header("Location: ../booking-lookup.php");
    exit;
}

$pdo = get_db_connection();

try {
    // Look up matching booking
    $stmt = $pdo->prepare("SELECT id, booking_id, booking_status FROM bookings WHERE booking_id = ? AND customer_phone = ?");
    $stmt->execute([$bookingId, $phone]);
    $booking = $stmt->fetch();

    if (!$booking) {
        set_flash('danger', 'No reservation found matching the provided Booking ID and phone number.');
        header("Location: ../booking-lookup.php");
        exit;
    }

    if ($booking['booking_status'] !== 'Confirmed') {
        set_flash('danger', "Cannot cancel this reservation because its status is already '{$booking['booking_status']}'.");
        header("Location: ../booking-lookup.php?booking_id=" . urlencode($bookingId) . "&phone=" . urlencode($phone));
        exit;
    }

    // Update status to Cancelled
    $updateStmt = $pdo->prepare("UPDATE bookings SET booking_status = 'Cancelled' WHERE id = ?");
    $updateStmt->execute([$booking['id']]);

    set_flash('success', "Your reservation #{$bookingId} has been successfully cancelled. The parking bay has been released.");
    header("Location: ../booking-lookup.php?booking_id=" . urlencode($bookingId) . "&phone=" . urlencode($phone));
    exit;

} catch (Exception $e) {
    set_flash('danger', 'Error cancelling reservation: ' . $e->getMessage());
    header("Location: ../booking-lookup.php");
    exit;
}
