<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Export Vehicle Records to CSV
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$type   = trim($_GET['type'] ?? '');
$date   = trim($_GET['date'] ?? '');

$sql = "
    SELECT r.ticket_id, v.registration_number, v.owner_name, v.owner_phone, v.vehicle_type,
           s.slot_number, r.entry_time, r.exit_time, r.duration_minutes, r.total_fee, r.status
    FROM parking_records r
    JOIN vehicles v ON r.vehicle_id = v.id
    JOIN parking_slots s ON r.slot_id = s.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (v.registration_number LIKE ? OR r.ticket_id LIKE ? OR v.owner_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}

if (!empty($type)) {
    $sql .= " AND v.vehicle_type = ?";
    $params[] = $type;
}

if (!empty($date)) {
    $sql .= " AND r.entry_time >= ? AND r.entry_time <= ?";
    $params[] = $date . " 00:00:00";
    $params[] = $date . " 23:59:59";
}

$sql .= " ORDER BY r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Set HTTP CSV headers
$filename = "smartpark_records_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Write CSV Header row
fputcsv($output, [
    'Ticket ID',
    'Vehicle Number',
    'Owner Name',
    'Owner Phone',
    'Vehicle Category',
    'Parking Bay',
    'Entry Time',
    'Exit Time',
    'Duration (Mins)',
    'Fee (INR)',
    'Status'
]);

// Write Data rows
foreach ($records as $row) {
    fputcsv($output, [
        $row['ticket_id'],
        $row['registration_number'],
        $row['owner_name'],
        $row['owner_phone'],
        $row['vehicle_type'],
        $row['slot_number'],
        $row['entry_time'],
        $row['exit_time'] ?? 'N/A',
        $row['duration_minutes'] ?? 'Active',
        $row['total_fee'] !== null ? number_format($row['total_fee'], 2) : 'Active',
        $row['status']
    ]);
}

fclose($output);
exit;
