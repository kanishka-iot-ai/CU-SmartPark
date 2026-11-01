<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Customer Online Parking Reservation
 * 
 * Uses strict transactions and anti-overlap verification to ensure concurrency safety.
 */

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../booking.php");
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (Invalid CSRF token). Please try booking again.');
    header("Location: ../booking.php");
    exit;
}

$customerName  = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$vehicleNumber = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim($_POST['vehicle_number'] ?? '')));
$vehicleType   = trim($_POST['vehicle_type'] ?? 'Car');
$bookingDate   = trim($_POST['booking_date'] ?? date('Y-m-d'));
$entryTime     = trim($_POST['entry_time'] ?? '10:00');
$exitTime      = trim($_POST['exit_time'] ?? '12:00');
$slotId        = (int)($_POST['slot_id'] ?? 0);

$allowedTypes = ['Two Wheeler', 'Car', 'SUV', 'Other'];

// 1. Validation
if (empty($customerName)) {
    set_flash('danger', 'Please provide your full name.');
    header("Location: ../booking.php");
    exit;
}

if (empty($customerPhone) || strlen($customerPhone) < 7) {
    set_flash('danger', 'Please enter a valid phone number so you can track your reservation.');
    header("Location: ../booking.php");
    exit;
}

if (empty($vehicleNumber) || strlen($vehicleNumber) < 4) {
    set_flash('danger', 'Please enter a valid vehicle registration number.');
    header("Location: ../booking.php");
    exit;
}

if (!in_array($vehicleType, $allowedTypes)) {
    set_flash('danger', 'Invalid vehicle category selected.');
    header("Location: ../booking.php");
    exit;
}

$entryDatetime = $bookingDate . ' ' . $entryTime . ':00';
$exitDatetime  = $bookingDate . ' ' . $exitTime . ':00';

$entryTs = strtotime($entryDatetime);
$exitTs  = strtotime($exitDatetime);

if (!$entryTs || !$exitTs || $exitTs <= $entryTs) {
    set_flash('danger', 'Expected departure time must be later than arrival time.');
    header("Location: ../booking.php");
    exit;
}

if ($slotId <= 0) {
    set_flash('danger', 'Please select an available parking bay from the list.');
    header("Location: ../booking.php");
    exit;
}

$pdo = get_db_connection();

try {
    // Begin Atomic Database Transaction
    $pdo->beginTransaction();

    // 2. Verify selected slot existence and vehicle category match
    $slotStmt = $pdo->prepare("SELECT id, slot_number, vehicle_type, status FROM parking_slots WHERE id = ?");
    $slotStmt->execute([$slotId]);
    $slot = $slotStmt->fetch();

    if (!$slot || $slot['vehicle_type'] !== $vehicleType) {
        $pdo->rollBack();
        set_flash('danger', 'Selected parking slot does not match the requested vehicle category.');
        header("Location: ../booking.php");
        exit;
    }

    // 3. Concurrency Protection: Check for overlapping reservations on this exact slot
    $overlapStmt = $pdo->prepare("
        SELECT booking_id 
        FROM bookings
        WHERE slot_id = ?
          AND booking_status IN ('Confirmed', 'Checked In')
          AND expected_entry_time < ?
          AND expected_exit_time > ?
        LIMIT 1
    ");
    $overlapStmt->execute([$slotId, $exitDatetime, $entryDatetime]);
    $conflict = $overlapStmt->fetch();

    if ($conflict) {
        $pdo->rollBack();
        set_flash('danger', "Slot {$slot['slot_number']} was just reserved by another customer for this time slot (Booking #{$conflict['booking_id']}). Please select another available bay.");
        header("Location: ../booking.php");
        exit;
    }

    // 4. Calculate estimated charges
    $calc = calculate_parking_fee($entryDatetime, $exitDatetime, $vehicleType);
    $estimatedAmount = $calc['total_fee'];

    // 5. Generate Unique Booking ID
    $bookingId = generate_booking_id();

    // 6. Register or update vehicle in `vehicles` table
    $vehStmt = $pdo->prepare("SELECT id FROM vehicles WHERE registration_number = ?");
    $vehStmt->execute([$vehicleNumber]);
    $veh = $vehStmt->fetch();
    if ($veh) {
        $vehId = (int)$veh['id'];
        $updateVeh = $pdo->prepare("UPDATE vehicles SET owner_name = ?, owner_phone = ?, vehicle_type = ? WHERE id = ?");
        $updateVeh->execute([$customerName, $customerPhone, $vehicleType, $vehId]);
    } else {
        $insVeh = $pdo->prepare("INSERT INTO vehicles (registration_number, owner_name, owner_phone, vehicle_type, created_at) VALUES (?, ?, ?, ?, ?)");
        $insVeh->execute([$vehicleNumber, $customerName, $customerPhone, $vehicleType, date('Y-m-d H:i:s')]);
    }

    // 7. Insert reservation into `bookings` table
    $insBooking = $pdo->prepare("
        INSERT INTO bookings (
            booking_id, customer_name, customer_phone, vehicle_number, vehicle_type,
            slot_id, booking_date, expected_entry_time, expected_exit_time,
            estimated_amount, booking_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed', ?)
    ");
    $insBooking->execute([
        $bookingId,
        $customerName,
        $customerPhone,
        $vehicleNumber,
        $vehicleType,
        $slotId,
        $bookingDate,
        $entryDatetime,
        $exitDatetime,
        $estimatedAmount,
        date('Y-m-d H:i:s')
    ]);

    // Commit Transaction
    $pdo->commit();

    // Store in session for immediate confirmation view
    $_SESSION['customer_booking_confirmation'] = [
        'booking_id'       => $bookingId,
        'customer_name'    => $customerName,
        'customer_phone'   => $customerPhone,
        'vehicle_number'   => $vehicleNumber,
        'vehicle_type'     => $vehicleType,
        'slot_number'      => $slot['slot_number'],
        'booking_date'     => date('D, d M Y', strtotime($bookingDate)),
        'arrival_time'     => date('h:i A', $entryTs),
        'departure_time'   => date('h:i A', $exitTs),
        'duration_text'    => $calc['duration_text'],
        'billable_hours'   => $calc['billable_hours'],
        'hourly_rate'      => $calc['rate_per_hour'],
        'estimated_price'  => $estimatedAmount,
        'booking_status'   => 'Confirmed',
        'parking_lot'      => get_setting('parking_lot_name', 'SmartPark Central')
    ];

    header("Location: ../booking-confirmation.php?booking_id=" . urlencode($bookingId));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', 'Booking failed: ' . $e->getMessage());
    header("Location: ../booking.php");
    exit;
}
