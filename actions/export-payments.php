<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Export Payments to CSV
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$method = trim($_GET['method'] ?? '');
$date   = trim($_GET['date'] ?? '');

$sql = "
    SELECT p.id as payment_id, p.amount, p.payment_method, p.payment_status, p.payment_date,
           r.ticket_id, v.registration_number, v.owner_name, v.vehicle_type, s.slot_number
    FROM payments p
    JOIN parking_records r ON p.parking_record_id = r.id
    JOIN vehicles v ON r.vehicle_id = v.id
    JOIN parking_slots s ON r.slot_id = s.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (v.registration_number LIKE ? OR r.ticket_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($method)) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $method;
}

if (!empty($date)) {
    $sql .= " AND p.payment_date >= ? AND p.payment_date <= ?";
    $params[] = $date . " 00:00:00";
    $params[] = $date . " 23:59:59";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$filename = "smartpark_payments_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Payment ID',
    'Ticket ID',
    'Vehicle Number',
    'Category',
    'Owner',
    'Bay',
    'Amount (INR)',
    'Payment Method',
    'Payment Status',
    'Payment Timestamp'
]);

foreach ($payments as $p) {
    fputcsv($output, [
        '#PAY-' . str_pad($p['payment_id'], 4, '0', STR_PAD_LEFT),
        $p['ticket_id'],
        $p['registration_number'],
        $p['vehicle_type'],
        $p['owner_name'],
        $p['slot_number'],
        number_format($p['amount'], 2),
        $p['payment_method'],
        $p['payment_status'],
        $p['payment_date']
    ]);
}

fclose($output);
exit;
