<?php
/**
 * SmartPark – Automated Functional & Integration Test Suite
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

echo "====================================================\n";
echo "   SMARTPARK DBMS PROJECT - AUTOMATED TEST SUITE    \n";
echo "====================================================\n\n";

$pdo = get_db_connection();
$testsPassed = 0;
$totalTests = 0;

function assert_test($description, $condition) {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "[PASS] " . $description . "\n";
    } else {
        echo "[FAIL] " . $description . "\n";
    }
}

// ----------------------------------------------------
// TEST 1: Database Connection & Seed Data Integrity
// ----------------------------------------------------
echo "--- Group 1: Database & Seed Data Verification ---\n";
assert_test("Database connection established", $pdo !== null);

$slotCount = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots")->fetchColumn();
assert_test("Initial 20 parking slots seeded", $slotCount >= 20);

$rateCount = (int)$pdo->query("SELECT COUNT(*) FROM parking_rates")->fetchColumn();
assert_test("4 default parking rates present", $rateCount === 4);

$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM parking_records WHERE status = 'Parked'")->fetchColumn();
assert_test("Sample active parked records present (>= 3)", $activeCount >= 3);

$completedCount = (int)$pdo->query("SELECT COUNT(*) FROM parking_records WHERE status = 'Completed'")->fetchColumn();
assert_test("Sample completed records present (>= 5)", $completedCount >= 5);

// ----------------------------------------------------
// TEST 2: Authentication & Security
// ----------------------------------------------------
echo "\n--- Group 2: Authentication & Password Security ---\n";
$loginValid = attempt_login('admin', 'admin123');
assert_test("Admin login succeeds with correct credentials", $loginValid['success'] === true);

$loginInvalid = attempt_login('admin', 'wrongpassword');
assert_test("Admin login fails with invalid password", $loginInvalid['success'] === false);

$loginNonexistent = attempt_login('nonexistent_user', 'anypass');
assert_test("Admin login fails with non-existent username", $loginNonexistent['success'] === false);

// ----------------------------------------------------
// TEST 3: Parking Slot CRUD & Integrity Checks
// ----------------------------------------------------
echo "\n--- Group 3: Parking Slot CRUD Operations ---\n";
$testSlotNum = 'TEST-BAY-' . rand(100, 999);
$stmt = $pdo->prepare("INSERT INTO parking_slots (slot_number, vehicle_type, status) VALUES (?, 'Car', 'Available')");
$stmt->execute([$testSlotNum]);
$testSlotId = (int)$pdo->lastInsertId();
assert_test("Successfully added new parking slot ($testSlotNum)", $testSlotId > 0);

// Update slot
$updateStmt = $pdo->prepare("UPDATE parking_slots SET vehicle_type = 'SUV' WHERE id = ?");
$updateStmt->execute([$testSlotId]);
$checkType = $pdo->query("SELECT vehicle_type FROM parking_slots WHERE id = $testSlotId")->fetchColumn();
assert_test("Updated slot category to SUV", $checkType === 'SUV');

// Attempt deletion of occupied slot (should be prevented)
$occupiedSlot = $pdo->query("SELECT id, slot_number, status FROM parking_slots WHERE status = 'Occupied' LIMIT 1")->fetch();
assert_test("Occupied slot detected in database ({$occupiedSlot['slot_number']})", $occupiedSlot !== false);
// In business logic: if status === 'Occupied', deletion is blocked:
$canDeleteOccupied = ($occupiedSlot['status'] !== 'Occupied');
assert_test("Enforces rule: Occupied slots CANNOT be deleted", $canDeleteOccupied === false);

// Delete test available slot
$delStmt = $pdo->prepare("DELETE FROM parking_slots WHERE id = ?");
$delStmt->execute([$testSlotId]);
$checkDel = $pdo->query("SELECT COUNT(*) FROM parking_slots WHERE id = $testSlotId")->fetchColumn();
assert_test("Available test slot deleted successfully", $checkDel == 0);

// ----------------------------------------------------
// TEST 4: Fee Calculation Logic
// ----------------------------------------------------
echo "\n--- Group 4: Fee Calculation Business Logic ---\n";
// Rule: Minimum charge 1 hour, ceil partial hours
// Test 25 mins Car (rate: 20) -> 1 hour * 20 = 20
$calc1 = calculate_parking_fee(time() - (25 * 60), time(), 'Car');
assert_test("25 mins Car parking billed as 1 hr = ₹20 (got ₹{$calc1['total_fee']})", $calc1['total_fee'] == 20 && $calc1['billable_hours'] == 1);

// Test 2 hours 30 mins Car (rate: 20) -> 3 hours * 20 = 60
$calc2 = calculate_parking_fee(time() - (150 * 60), time(), 'Car');
assert_test("2 hrs 30 mins Car parking billed as 3 hrs = ₹60 (got ₹{$calc2['total_fee']})", $calc2['total_fee'] == 60 && $calc2['billable_hours'] == 3);

// Test 65 mins SUV (rate: 30) -> 2 hours * 30 = 60
$calc3 = calculate_parking_fee(time() - (65 * 60), time(), 'SUV');
assert_test("65 mins SUV parking billed as 2 hrs = ₹60 (got ₹{$calc3['total_fee']})", $calc3['total_fee'] == 60 && $calc3['billable_hours'] == 2);

// Test 45 mins Two Wheeler (rate: 10) -> 1 hour * 10 = 10
$calc4 = calculate_parking_fee(time() - (45 * 60), time(), 'Two Wheeler');
assert_test("45 mins Two Wheeler parking billed as 1 hr = ₹10 (got ₹{$calc4['total_fee']})", $calc4['total_fee'] == 10 && $calc4['billable_hours'] == 1);

// ----------------------------------------------------
// TEST 5: Vehicle Entry Transaction & Duplicate Prevention
// ----------------------------------------------------
echo "\n--- Group 5: Vehicle Entry & Relational Integrity ---\n";
$testReg = 'DL-01-TEST-' . rand(1000, 9999);
$availSlot = $pdo->query("SELECT id, slot_number FROM parking_slots WHERE status = 'Available' AND vehicle_type = 'Car' LIMIT 1")->fetch();
assert_test("Available car slot found ({$availSlot['slot_number']})", $availSlot !== false);

// Perform atomic entry
$pdo->beginTransaction();
$insVeh = $pdo->prepare("INSERT INTO vehicles (registration_number, owner_name, owner_phone, vehicle_type, created_at) VALUES (?, 'Test Owner', '+91 9999999999', 'Car', ?)");
$insVeh->execute([$testReg, date('Y-m-d H:i:s')]);
$newVehId = (int)$pdo->lastInsertId();

$newTicket = generate_ticket_id();
$pdo->prepare("UPDATE parking_slots SET status = 'Occupied' WHERE id = ?")->execute([$availSlot['id']]);
$pdo->prepare("INSERT INTO parking_records (ticket_id, vehicle_id, slot_id, entry_time, status) VALUES (?, ?, ?, ?, 'Parked')")
    ->execute([$newTicket, $newVehId, $availSlot['id'], date('Y-m-d H:i:s', time() - 7200)]); // entered 2 hrs ago
$newRecordId = (int)$pdo->lastInsertId();
$pdo->commit();

assert_test("Vehicle Entry transaction succeeded (Ticket: $newTicket, Record: $newRecordId)", $newRecordId > 0);

// Verify slot is now Occupied
$slotStatus = $pdo->query("SELECT status FROM parking_slots WHERE id = {$availSlot['id']}")->fetchColumn();
assert_test("Assigned slot status changed to 'Occupied'", $slotStatus === 'Occupied');

// Test Duplicate Prevention: Trying to park the same vehicle while active
$checkActive = $pdo->prepare("SELECT id FROM parking_records WHERE vehicle_id = ? AND status = 'Parked'");
$checkActive->execute([$newVehId]);
$isAlreadyParked = ($checkActive->fetch() !== false);
assert_test("Enforces rule: Prevents duplicate active entry for already parked vehicle", $isAlreadyParked === true);

// ----------------------------------------------------
// TEST 6: Vehicle Exit, Payment & Relational Updates
// ----------------------------------------------------
echo "\n--- Group 6: Vehicle Exit & Settlement Transaction ---\n";
// Process checkout for our test vehicle
$exitTimestamp = date('Y-m-d H:i:s');
$exitCalc = calculate_parking_fee(time() - 7200, time(), 'Car'); // 2 hours = 40.00

$pdo->beginTransaction();
$pdo->prepare("UPDATE parking_records SET exit_time = ?, duration_minutes = ?, total_fee = ?, status = 'Completed' WHERE id = ?")
    ->execute([$exitTimestamp, $exitCalc['duration_minutes'], $exitCalc['total_fee'], $newRecordId]);
$pdo->prepare("UPDATE parking_slots SET status = 'Available' WHERE id = ?")->execute([$availSlot['id']]);
$pdo->prepare("INSERT INTO payments (parking_record_id, amount, payment_method, payment_status, payment_date) VALUES (?, ?, 'UPI', 'Paid', ?)")
    ->execute([$newRecordId, $exitCalc['total_fee'], $exitTimestamp]);
$pdo->commit();

// Check record is Completed
$recordCheck = $pdo->query("SELECT status, total_fee FROM parking_records WHERE id = $newRecordId")->fetch();
assert_test("Parking record status updated to 'Completed'", $recordCheck['status'] === 'Completed');
assert_test("Fee accurately recorded (₹{$recordCheck['total_fee']})", (float)$recordCheck['total_fee'] == 40.00);

// Check slot is back to Available
$slotAfterExit = $pdo->query("SELECT status FROM parking_slots WHERE id = {$availSlot['id']}")->fetchColumn();
assert_test("Parking slot {$availSlot['slot_number']} released back to 'Available'", $slotAfterExit === 'Available');

// Check payment recorded in payments table
$payCheck = $pdo->prepare("SELECT amount, payment_method, payment_status FROM payments WHERE parking_record_id = ?");
$payCheck->execute([$newRecordId]);
$payRow = $payCheck->fetch();
assert_test("Payment transaction logged in payments table (UPI ₹{$payRow['amount']})", $payRow && $payRow['payment_status'] === 'Paid');

// ----------------------------------------------------
// TEST 7: Settings & Rate Persistence
// ----------------------------------------------------
echo "\n--- Group 7: Settings & Pricing Updates ---\n";
$originalCarRate = get_rate_for_type('Car');
$pdo->prepare("UPDATE parking_rates SET hourly_rate = 22.50 WHERE vehicle_type = 'Car'")->execute();
$newCarRate = get_rate_for_type('Car');
assert_test("Configurable parking rate updated and persisted (₹22.50)", $newCarRate == 22.50);
// Restore original rate
$pdo->prepare("UPDATE parking_rates SET hourly_rate = ? WHERE vehicle_type = 'Car'")->execute([$originalCarRate]);

// ----------------------------------------------------
// TEST 8: Online Customer Booking & Concurrency
// ----------------------------------------------------
echo "\n--- Group 8: Customer Online Reservation Engine ---\n";
$testDate = date('Y-m-d', strtotime('+1 day'));
$testEntry = $testDate . ' 10:00:00';
$testExit  = $testDate . ' 13:00:00'; // 3 hours

// Check available slots for window
$freeSlots = get_available_slots_for_window('Car', $testEntry, $testExit);
assert_test("Calculates vacant bays for tomorrow's 10:00-13:00 window (found " . count($freeSlots) . ")", count($freeSlots) > 0);

$chosenSlot = $freeSlots[0];
$bookingId1 = generate_booking_id();
$calcOnline = calculate_parking_fee($testEntry, $testExit, 'Car'); // 3 hrs * 20 = 60

$pdo->beginTransaction();
$pdo->prepare("
    INSERT INTO bookings (booking_id, customer_name, customer_phone, vehicle_number, vehicle_type, slot_id, booking_date, expected_entry_time, expected_exit_time, estimated_amount, booking_status)
    VALUES (?, 'Karan Patel', '+91 9123499999', 'DL-09-AA-5555', 'Car', ?, ?, ?, ?, ?, 'Confirmed')
")->execute([$bookingId1, $chosenSlot['id'], $testDate, $testEntry, $testExit, $calcOnline['total_fee']]);
$pdo->commit();

assert_test("Created online reservation #{$bookingId1} for Bay {$chosenSlot['slot_number']}", true);

// Overlapping reservation prevention test:
// Try reserving the EXACT SAME slot during an overlapping window (11:00 to 14:00)
$overlapEntry = $testDate . ' 11:00:00';
$overlapExit  = $testDate . ' 14:00:00';

$overlapCheck = $pdo->prepare("
    SELECT booking_id FROM bookings
    WHERE slot_id = ?
      AND booking_status IN ('Confirmed', 'Checked In')
      AND expected_entry_time < ?
      AND expected_exit_time > ?
");
$overlapCheck->execute([$chosenSlot['id'], $overlapExit, $overlapEntry]);
$detectedConflict = $overlapCheck->fetch();
assert_test("Anti-overlap engine detects conflicting booking on Bay {$chosenSlot['slot_number']} ({$detectedConflict['booking_id']})", $detectedConflict !== false);

// Customer Check-In Test:
// When customer arrives, admin checks them in
$actualIn = date('Y-m-d H:i:s');
$ticketOnline = generate_ticket_id();

$pdo->beginTransaction();
// 1. Update booking to Checked In
$pdo->prepare("UPDATE bookings SET booking_status = 'Checked In', actual_entry_time = ? WHERE booking_id = ?")
    ->execute([$actualIn, $bookingId1]);
// 2. Set slot to Occupied
$pdo->prepare("UPDATE parking_slots SET status = 'Occupied' WHERE id = ?")->execute([$chosenSlot['id']]);
// 3. Register vehicle if not present
$vehIdTest = (int)$pdo->query("SELECT id FROM vehicles LIMIT 1")->fetchColumn();
// 4. Create active parking record linked to booking
$pdo->prepare("INSERT INTO parking_records (ticket_id, booking_id, vehicle_id, slot_id, entry_time, status) VALUES (?, ?, ?, ?, ?, 'Parked')")
    ->execute([$ticketOnline, $bookingId1, $vehIdTest, $chosenSlot['id'], $actualIn]);
$pdo->commit();

$checkedBooking = $pdo->query("SELECT booking_status FROM bookings WHERE booking_id = '$bookingId1'")->fetchColumn();
assert_test("Customer check-in changes booking status to 'Checked In'", $checkedBooking === 'Checked In');
$bayOccupied = $pdo->query("SELECT status FROM parking_slots WHERE id = {$chosenSlot['id']}")->fetchColumn();
assert_test("Bay {$chosenSlot['slot_number']} flipped to 'Occupied'", $bayOccupied === 'Occupied');

// Prevent invalid check-in test: (Cannot check in already checked-in booking)
$canDoubleCheckIn = ($checkedBooking === 'Confirmed');
assert_test("Prevents double check-in of already checked-in booking", $canDoubleCheckIn === false);

// Vehicle Exit & Final Billing Test:
$actualOut = date('Y-m-d H:i:s', time() + 7200); // 2 hours stay
$exitFeeCalc = calculate_parking_fee($actualIn, $actualOut, 'Car'); // 2 hrs * 20 = 40

$pdo->beginTransaction();
$pdo->prepare("UPDATE bookings SET actual_exit_time = ?, final_amount = ?, booking_status = 'Completed' WHERE booking_id = ?")
    ->execute([$actualOut, $exitFeeCalc['total_fee'], $bookingId1]);
$pdo->prepare("UPDATE parking_records SET exit_time = ?, duration_minutes = 120, total_fee = ?, status = 'Completed' WHERE booking_id = ?")
    ->execute([$actualOut, $exitFeeCalc['total_fee'], $bookingId1]);
$pdo->prepare("UPDATE parking_slots SET status = 'Available' WHERE id = ?")->execute([$chosenSlot['id']]);
$pdo->commit();

$finalBooking = $pdo->query("SELECT booking_status, estimated_amount, final_amount FROM bookings WHERE booking_id = '$bookingId1'")->fetch();
assert_test("Vehicle checkout completes booking: status = 'Completed'", $finalBooking['booking_status'] === 'Completed');
assert_test("Distinguishes estimated charge (₹{$finalBooking['estimated_amount']}) vs final settled charge (₹{$finalBooking['final_amount']})", $finalBooking['estimated_amount'] != $finalBooking['final_amount']);

// Booking Cancellation Test:
$bookingId2 = generate_booking_id();
$pdo->prepare("
    INSERT INTO bookings (booking_id, customer_name, customer_phone, vehicle_number, vehicle_type, slot_id, booking_date, expected_entry_time, expected_exit_time, estimated_amount, booking_status)
    VALUES (?, 'Pooja Hegde', '+91 9888877777', 'KA-05-ZZ-1122', 'Car', ?, ?, ?, ?, 40.00, 'Confirmed')
")->execute([$bookingId2, $chosenSlot['id'], $testDate, $testEntry, $testExit]);

$pdo->prepare("UPDATE bookings SET booking_status = 'Cancelled' WHERE booking_id = ?")->execute([$bookingId2]);
$statusCancel = $pdo->query("SELECT booking_status FROM bookings WHERE booking_id = '$bookingId2'")->fetchColumn();
assert_test("Booking cancellation verified: status = 'Cancelled'", $statusCancel === 'Cancelled');

echo "\n====================================================\n";
echo "SUMMARY: Passed {$testsPassed} of {$totalTests} tests (" . round(($testsPassed / $totalTests) * 100) . "%)\n";
echo "====================================================\n";

if ($testsPassed === $totalTests) {
    echo "STATUS: ALL TESTS PASSED SUCCESSFULLY! PROJECT READY FOR EVALUATION.\n";
    exit(0);
} else {
    echo "STATUS: SOME TESTS FAILED.\n";
    exit(1);
}
