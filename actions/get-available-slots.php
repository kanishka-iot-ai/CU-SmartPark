<?php
/**
 * SmartPark – Vehicle Parking Management System
 * AJAX Action: Get Available Slots by Vehicle Type
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

header('Content-Type: application/json');

$type = trim($_GET['type'] ?? '');
$pdo = get_db_connection();

try {
    if (!empty($type)) {
        $stmt = $pdo->prepare("SELECT id, slot_number, vehicle_type FROM parking_slots WHERE vehicle_type = ? AND status = 'Available' ORDER BY slot_number ASC");
        $stmt->execute([$type]);
    } else {
        $stmt = $pdo->query("SELECT id, slot_number, vehicle_type FROM parking_slots WHERE status = 'Available' ORDER BY slot_number ASC");
    }
    
    $slots = $stmt->fetchAll();
    echo json_encode(['success' => true, 'slots' => $slots]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
