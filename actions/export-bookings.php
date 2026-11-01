<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Export Customer Bookings to CSV
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$date   = trim($_GET['date'] ?? '');

$sql = "
    SELECT b.*, s.slot_number
    FROM bookings b
    JOIN parking_slots s ON b.slot_id = s.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (b.booking_id LIKE ? OR b.vehicle_number LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $sql .= " AND b.booking_status = ?";
    $params[] = $status;
}

if (!empty($date)) {
    $sql .= " AND b.booking_date = ?";
    $params[] = $date;
}

$sql .= " ORDER BY b.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$filename = "smartpark_online_bookings_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Booking ID',
    'Customer Name',
    'Customer Phone',
    'Vehicle Number',
    'Vehicle Category',
    'Reserved Bay',
    'Booking Date',
    'Expected Arrival',
    'Expected Departure',
    'Actual Check-In',
    'Actual Check-Out',
    'Estimated Price (INR)',
    'Final Paid Price (INR)',
    'Status'
]);

foreach ($bookings as $b) {
    fputcsv($output, [
        $b['booking_id'],
        $b['customer_name'],
        $b['customer_phone'],
        $b['vehicle_number'],
        $b['vehicle_type'],
        $b['slot_number'],
        $b['booking_date'],
        $b['expected_entry_time'],
        $b['expected_exit_time'],
        $b['actual_entry_time'] ?? 'N/A',
        $b['actual_exit_time'] ?? 'N/A',
        number_format($b['estimated_amount'], 2),
        $b['final_amount'] !== null ? number_format($b['final_amount'], 2) : 'Pending',
        $b['booking_status']
    ]);
}

fclose($output);
exit;
