<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Export Analytics & Slot Utilization Report to CSV
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

$pdo = get_db_connection();

// Top Used Slots Report
$slotsReportSql = "
    SELECT s.slot_number, s.vehicle_type, s.status,
           COUNT(r.id) as total_sessions,
           COALESCE(SUM(r.duration_minutes), 0) as total_minutes_used,
           COALESCE(SUM(r.total_fee), 0) as total_revenue_generated
    FROM parking_slots s
    LEFT JOIN parking_records r ON s.id = r.slot_id
    GROUP BY s.id, s.slot_number, s.vehicle_type, s.status
    ORDER BY total_sessions DESC, total_revenue_generated DESC
";
$slots = $pdo->query($slotsReportSql)->fetchAll();

$filename = "smartpark_slot_utilization_report_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Slot Number',
    'Category',
    'Current Status',
    'Total Completed Sessions',
    'Total Duration (Mins)',
    'Total Revenue Generated (INR)'
]);

foreach ($slots as $s) {
    fputcsv($output, [
        $s['slot_number'],
        $s['vehicle_type'],
        $s['status'],
        $s['total_sessions'],
        $s['total_minutes_used'],
        number_format($s['total_revenue_generated'], 2)
    ]);
}

fclose($output);
exit;
