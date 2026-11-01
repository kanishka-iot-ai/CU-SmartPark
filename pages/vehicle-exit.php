<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Vehicle Exit & Fee Settlement Page
 */

$pageTitle = 'Vehicle Exit & Billing';
$activePage = 'vehicle-exit';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// 1. Check if exit completed parameter is passed
$completedTicket = trim($_GET['completed'] ?? '');
$receiptData = null;

if (!empty($completedTicket)) {
    if (isset($_SESSION['exit_receipt']) && $_SESSION['exit_receipt']['ticket_id'] === $completedTicket) {
        $receiptData = $_SESSION['exit_receipt'];
    } else {
        // Query database for completed payment details
        $stmtReceipt = $pdo->prepare("
            SELECT r.ticket_id, r.entry_time, r.exit_time, r.duration_minutes, r.total_fee,
                   v.registration_number, v.owner_name, v.vehicle_type,
                   s.slot_number,
                   p.payment_method, p.amount, p.payment_date
            FROM parking_records r
            JOIN vehicles v ON r.vehicle_id = v.id
            JOIN parking_slots s ON r.slot_id = s.id
            LEFT JOIN payments p ON p.parking_record_id = r.id
            WHERE r.ticket_id = ? AND r.status = 'Completed'
            ORDER BY p.id DESC LIMIT 1
        ");
        $stmtReceipt->execute([$completedTicket]);
        $row = $stmtReceipt->fetch();
        if ($row) {
            $hours = ceil(($row['duration_minutes'] ?: 60) / 60);
            $receiptData = [
                'ticket_id'      => $row['ticket_id'],
                'reg_number'     => $row['registration_number'],
                'vehicle_type'   => $row['vehicle_type'],
                'owner_name'     => $row['owner_name'],
                'slot_number'    => $row['slot_number'],
                'entry_time'     => date('d M Y, h:i A', strtotime($row['entry_time'])),
                'exit_time'      => date('d M Y, h:i A', strtotime($row['exit_time'])),
                'duration_text'  => floor(($row['duration_minutes'] ?: 60)/60) . ' hr ' . (($row['duration_minutes'] ?: 60)%60) . ' min',
                'billable_hours' => $hours,
                'rate_per_hour'  => get_rate_for_type($row['vehicle_type']),
                'total_fee'      => $row['total_fee'],
                'payment_method' => $row['payment_method'] ?? 'Cash',
                'parking_lot'    => get_setting('parking_lot_name', 'SmartPark Central')
            ];
        }
    }
}

// 2. Search for active parked vehicle by ticket_id or registration_number
$searchQuery = trim($_GET['search'] ?? '');
$selectedRecord = null;
$calculation = null;

if (!empty($searchQuery)) {
    $searchStmt = $pdo->prepare("
        SELECT r.id, r.ticket_id, r.booking_id, r.vehicle_id, r.slot_id, r.entry_time, r.status,
               v.registration_number, v.owner_name, v.owner_phone, v.vehicle_type,
               s.slot_number
        FROM parking_records r
        JOIN vehicles v ON r.vehicle_id = v.id
        JOIN parking_slots s ON r.slot_id = s.id
        WHERE (r.ticket_id = ? OR v.registration_number = ? OR r.booking_id = ?) AND r.status = 'Parked'
        LIMIT 1
    ");
    $searchStmt->execute([$searchQuery, strtoupper($searchQuery), $searchQuery]);
    $selectedRecord = $searchStmt->fetch();

    if ($selectedRecord) {
        $calculation = calculate_parking_fee($selectedRecord['entry_time'], null, $selectedRecord['vehicle_type']);
    }
}

// 3. Fetch all currently parked vehicles for quick selection list
$activeVehiclesQuery = "
    SELECT r.id, r.ticket_id, r.entry_time,
           v.registration_number, v.owner_name, v.vehicle_type,
           s.slot_number
    FROM parking_records r
    JOIN vehicles v ON r.vehicle_id = v.id
    JOIN parking_slots s ON r.slot_id = s.id
    WHERE r.status = 'Parked'
    ORDER BY r.entry_time ASC
";
$activeVehicles = $pdo->query($activeVehiclesQuery)->fetchAll();

$csrf_token = get_csrf_token();
?>

<!-- Header -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Vehicle Checkout &amp; Billing</h3>
        <p style="font-size: 13px; color: var(--text-muted);">Look up active parked vehicles, compute duration-based tariffs, and record manual payments.</p>
    </div>
</div>

<?php if ($receiptData): ?>
<!-- COMPLETED EXIT INVOICE RECEIPT -->
<div class="card" style="border: 2px solid #86EFAC; background: #F0FDF4; margin-bottom: 30px;">
    <div class="card-header" style="background: transparent; border-bottom: 1px solid #BBF7D0;">
        <div class="card-title" style="color: #166534;">
            <i class="fas fa-check-double" style="color: var(--success-green);"></i> Vehicle Checkout &amp; Settlement Completed
        </div>
        <div style="display: flex; gap: 8px;" class="no-print">
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print Payment Receipt
            </button>
            <a href="vehicle-exit.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-search"></i> Checkout Another Vehicle
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="parking-ticket" id="printableReceipt">
            <div class="ticket-header">
                <img src="../assets/images/logo.png" alt="Chandigarh University" style="width: 50px; height: 50px; object-fit: contain; margin-bottom: 6px;">
                <div style="font-size: 16px; font-weight: 800; color: #1E293B;">Chandigarh University</div>
                <div class="ticket-subtitle">Campus Parking Fee Receipt &bull; SmartPark</div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Campus: Gharuan, Mohali, Punjab</div>
            </div>

            <div class="ticket-details">
                <div class="ticket-row">
                    <span>Receipt / Ticket:</span>
                    <span style="font-family: monospace;"><?php echo sanitize($receiptData['ticket_id']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Vehicle No:</span>
                    <span><?php echo sanitize($receiptData['reg_number']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Category:</span>
                    <span><?php echo sanitize($receiptData['vehicle_type']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Owner:</span>
                    <span><?php echo sanitize($receiptData['owner_name']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Assigned Bay:</span>
                    <span><?php echo sanitize($receiptData['slot_number']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Entry Time:</span>
                    <span><?php echo sanitize($receiptData['entry_time']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Exit Time:</span>
                    <span><?php echo sanitize($receiptData['exit_time']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Duration:</span>
                    <span><?php echo sanitize($receiptData['duration_text']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Billable Hours:</span>
                    <span><?php echo $receiptData['billable_hours']; ?> hr (Ceil)</span>
                </div>
                <div class="ticket-row">
                    <span>Hourly Rate:</span>
                    <span>₹<?php echo number_format($receiptData['rate_per_hour'], 2); ?></span>
                </div>
                <?php if (!empty($receiptData['booking_id'])): ?>
                    <div class="ticket-row" style="background: #F1F5F9; padding: 4px 6px; border-radius: 4px; margin: 4px 0;">
                        <span>Online Reservation:</span>
                        <strong style="font-family: monospace; color: #4F46E5;"><?php echo sanitize($receiptData['booking_id']); ?></strong>
                    </div>
                    <?php if (isset($receiptData['estimated_price']) && $receiptData['estimated_price'] !== null): ?>
                        <div class="ticket-row">
                            <span>Estimated Booking Fee:</span>
                            <span>₹<?php echo number_format($receiptData['estimated_price'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="ticket-row" style="border-top: 2px solid #CBD5E1; margin-top: 8px; padding-top: 8px; font-size: 15px;">
                    <span style="font-weight: 700; color: var(--text-primary);">Total Paid:</span>
                    <span style="font-weight: 800; color: var(--success-green); font-size: 17px;">
                        ₹<?php echo number_format($receiptData['total_fee'], 2); ?>
                    </span>
                </div>
                <div class="ticket-row">
                    <span>Payment Method:</span>
                    <span class="badge" style="background: #E0F2FE; color: #0369A1; font-weight: 700;">
                        <?php echo sanitize($receiptData['payment_method']); ?>
                    </span>
                </div>
            </div>

            <div class="ticket-footer">
                Status: <strong style="color: var(--success-green);">PAID &bull; VERIFIED</strong><br>
                Thank you, drive safely!
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Search Card -->
<div class="card no-print" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 22px;">
        <form action="vehicle-exit.php" method="GET" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 280px;">
                <label for="search" style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    <i class="fas fa-search" style="color: var(--primary-blue); margin-right: 4px;"></i> Search Active Vehicle
                </label>
                <input type="text" name="search" id="search" class="form-control" 
                       placeholder="Enter Vehicle Registration No (e.g. KA-01-AB-1234) or Ticket ID..." 
                       value="<?php echo sanitize($searchQuery); ?>" required 
                       style="font-size: 15px; font-family: monospace; text-transform: uppercase;">
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-search"></i> Find Parking Record
                </button>
                <?php if (!empty($searchQuery)): ?>
                    <a href="vehicle-exit.php" class="btn btn-secondary btn-lg">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($searchQuery) && !$selectedRecord): ?>
    <div class="alert alert-danger no-print">
        <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
        No active parked vehicle found matching "<strong><?php echo sanitize($searchQuery); ?></strong>". Either the vehicle is already checked out or the registration/ticket number is incorrect.
    </div>
<?php endif; ?>

<!-- Process Exit Section (When record is found) -->
<?php if ($selectedRecord && $calculation): ?>
<div class="card no-print" style="border: 2px solid #BFDBFE; margin-bottom: 30px;">
    <div class="card-header" style="background: #EFF6FF;">
        <div class="card-title" style="color: #1E40AF;">
            <i class="fas fa-calculator"></i> Active Parking Record Found &amp; Fee Calculation
        </div>
        <span class="badge badge-occupied" style="font-size: 12px; padding: 4px 10px;">
            <i class="fas fa-clock"></i> Currently Parked
        </span>
    </div>
    <div class="card-body">
        <div class="exit-summary-grid">
            
            <!-- Vehicle & Check-In Details -->
            <div style="background: #F8FAFC; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px;">
                <h4 style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 14px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                    <i class="fas fa-car" style="color: var(--primary-blue);"></i> Vehicle Information
                </h4>
                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13.5px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Ticket ID:</span>
                        <strong style="font-family: monospace; color: var(--primary-blue);"><?php echo sanitize($selectedRecord['ticket_id']); ?></strong>
                    </div>
                    <?php if (!empty($selectedRecord['booking_id'])): ?>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Booking Reference:</span>
                            <strong style="font-family: monospace; color: #4F46E5;"><?php echo sanitize($selectedRecord['booking_id']); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Registration No:</span>
                        <strong style="font-family: monospace; font-size: 15px;"><?php echo sanitize($selectedRecord['registration_number']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Vehicle Type:</span>
                        <span><?php echo sanitize($selectedRecord['vehicle_type']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Owner Name:</span>
                        <span><?php echo sanitize($selectedRecord['owner_name']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Contact Phone:</span>
                        <span><?php echo sanitize($selectedRecord['owner_phone']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Parking Bay:</span>
                        <span class="badge" style="background: #DBEAFE; color: #1E40AF; font-size: 13px; font-weight: 700;">
                            <?php echo sanitize($selectedRecord['slot_number']); ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Entry Time:</span>
                        <span><?php echo $calculation['entry_formatted']; ?></span>
                    </div>
                </div>
            </div>

            <!-- Fee Calculation & Settlement Form -->
            <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h4 style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 14px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                        <i class="fas fa-file-invoice-dollar" style="color: var(--success-green);"></i> Tariff &amp; Billing Calculation
                    </h4>

                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13.5px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Parking Duration:</span>
                            <strong><?php echo $calculation['duration_text']; ?> (<?php echo $calculation['duration_minutes']; ?> mins)</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Billable Hours (Rounded Up):</span>
                            <strong><?php echo $calculation['billable_hours']; ?> hr(s)</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Hourly Tariff (<?php echo sanitize($selectedRecord['vehicle_type']); ?>):</span>
                            <strong>₹<?php echo number_format($calculation['rate_per_hour'], 2); ?> / hr</strong>
                        </div>
                        <div style="border-top: 1px dashed var(--border-color); margin: 6px 0;"></div>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 17px;">
                            <span style="font-weight: 700; color: var(--text-primary);">Total Parking Fee:</span>
                            <span style="font-weight: 800; color: var(--success-green); font-size: 24px;">
                                ₹<?php echo number_format($calculation['total_fee'], 2); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <form action="../actions/vehicle-exit.php" method="POST" onsubmit="return confirmAction('Confirm checkout for vehicle <?php echo sanitize($selectedRecord['registration_number']); ?> and collect ₹<?php echo number_format($calculation['total_fee'], 2); ?>?');">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <input type="hidden" name="record_id" value="<?php echo $selectedRecord['id']; ?>">

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="payment_method">Payment Settlement Method <span class="required">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-control" required style="font-weight: 600;">
                            <option value="Cash" selected>Cash Payment</option>
                            <option value="UPI">UPI / QR Code Scan</option>
                            <option value="Card">Debit / Credit Card (POS)</option>
                        </select>
                        <div class="input-hint">Collected and confirmed by parking attendant.</div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg" style="width: 100%;">
                        <i class="fas fa-check-circle"></i> Confirm Payment &amp; Release Bay
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quick List: Currently Parked Vehicles -->
<div class="card no-print">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-parking"></i> Currently Parked Vehicles (<?php echo count($activeVehicles); ?> Active)
        </div>
        <span style="font-size: 12px; color: var(--text-muted);">Click "Checkout" to immediately process calculation and payment</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Vehicle Number</th>
                        <th>Category</th>
                        <th>Owner Name</th>
                        <th>Parking Bay</th>
                        <th>Check-In Time</th>
                        <th>Duration So Far</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeVehicles)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-check-double empty-state-icon" style="color: var(--success-green);"></i>
                                <div class="empty-state-title">No vehicles currently parked</div>
                                <div class="empty-state-text">All bays are available or reserved. Use Vehicle Entry to check in a new vehicle.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeVehicles as $act): ?>
                            <?php
                            $durationCalc = calculate_parking_fee($act['entry_time'], null, $act['vehicle_type']);
                            ?>
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; color: var(--primary-blue);">
                                        <?php echo sanitize($act['ticket_id']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="font-family: monospace; font-size: 14px;">
                                        <?php echo sanitize($act['registration_number']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge" style="background: #F1F5F9; color: #334155;">
                                        <?php echo sanitize($act['vehicle_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo sanitize($act['owner_name']); ?></td>
                                <td>
                                    <span class="badge" style="background: #FEE2E2; color: #991B1B; font-weight: 700;">
                                        <?php echo sanitize($act['slot_number']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 12.5px;">
                                    <?php echo date('d M, h:i A', strtotime($act['entry_time'])); ?>
                                </td>
                                <td style="font-size: 12.5px; font-weight: 500;">
                                    <?php echo $durationCalc['duration_text']; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="vehicle-exit.php?search=<?php echo urlencode($act['ticket_id']); ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-sign-out-alt"></i> Checkout
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
