<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Action: Delete Parking Slot
 */

require_once __DIR__ . '/../includes/functions.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/parking-slots.php");
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed (Invalid CSRF token).');
    header("Location: ../pages/parking-slots.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid slot identifier.');
    header("Location: ../pages/parking-slots.php");
    exit;
}

$pdo = get_db_connection();

try {
    // 1. Fetch current slot
    $stmt = $pdo->prepare("SELECT * FROM parking_slots WHERE id = ?");
    $stmt->execute([$id]);
    $slot = $stmt->fetch();

    if (!$slot) {
        set_flash('danger', 'Parking slot not found.');
        header("Location: ../pages/parking-slots.php");
        exit;
    }

    // 2. Prevent deletion of occupied parking slot (Prompt Requirement)
    if ($slot['status'] === 'Occupied') {
        set_flash('danger', "Cannot delete parking slot '{$slot['slot_number']}' because it is currently OCCUPIED by a vehicle. Please process vehicle checkout first.");
        header("Location: ../pages/parking-slots.php");
        exit;
    }

    // 3. Check if there are active or historical parking records referencing this slot
    $historyCheck = $pdo->prepare("SELECT COUNT(*) FROM parking_records WHERE slot_id = ?");
    $historyCheck->execute([$id]);
    $recordCount = (int)$historyCheck->fetchColumn();

    if ($recordCount > 0) {
        // Relational DBMS Integrity Rule:
        // In DBMS, deleting a master record that has child foreign key records will violate foreign key constraints.
        // We notify the admin clearly about DBMS referential integrity!
        set_flash('warning', "Cannot delete parking slot '{$slot['slot_number']}' because {$recordCount} historical parking record(s) reference it. In relational DBMS, foreign key constraints prevent deleting referenced records to preserve audit integrity. Consider marking it 'Reserved' instead.");
        header("Location: ../pages/parking-slots.php");
        exit;
    }

    // 4. Safe deletion
    $delStmt = $pdo->prepare("DELETE FROM parking_slots WHERE id = ?");
    $delStmt->execute([$id]);

    set_flash('success', "Parking slot '{$slot['slot_number']}' has been permanently deleted.");
} catch (Exception $e) {
    set_flash('danger', 'Error deleting slot: ' . $e->getMessage());
}

header("Location: ../pages/parking-slots.php");
exit;
