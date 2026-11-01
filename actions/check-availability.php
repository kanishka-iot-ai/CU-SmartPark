<?php
/**
 * SmartPark – Vehicle Parking Management System
 * AJAX Action: Check Parking Availability & Estimate Tariff
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$vehicleType = trim($_GET['vehicle_type'] ?? 'Car');
$bookingDate = trim($_GET['booking_date'] ?? date('Y-m-d'));
$entryTime   = trim($_GET['entry_time'] ?? '10:00');
$exitTime    = trim($_GET['exit_time'] ?? '12:00');

// Validate date and time formats
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking date format.']);
    exit;
}

$entryDatetime = $bookingDate . ' ' . $entryTime . ':00';
$exitDatetime  = $bookingDate . ' ' . $exitTime . ':00';

$entryTs = strtotime($entryDatetime);
$exitTs  = strtotime($exitDatetime);

if (!$entryTs || !$exitTs || $exitTs <= $entryTs) {
    echo json_encode(['success' => false, 'error' => 'Departure time must be later than arrival time.']);
    exit;
}

// Calculate duration and estimated fee
$calc = calculate_parking_fee($entryDatetime, $exitDatetime, $vehicleType);

// Fetch slots available for this exact time window
$availableSlots = get_available_slots_for_window($vehicleType, $entryDatetime, $exitDatetime);

echo json_encode([
    'success'          => true,
    'vehicle_type'     => $vehicleType,
    'entry_datetime'   => $entryDatetime,
    'exit_datetime'    => $exitDatetime,
    'duration_text'    => $calc['duration_text'],
    'billable_hours'   => $calc['billable_hours'],
    'hourly_rate'      => $calc['rate_per_hour'],
    'estimated_amount' => $calc['total_fee'],
    'formatted_amount' => format_currency($calc['total_fee']),
    'available_count'  => count($availableSlots),
    'slots'            => $availableSlots
]);
