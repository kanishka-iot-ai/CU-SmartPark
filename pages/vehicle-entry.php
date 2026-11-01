<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Vehicle Entry Page
 */

$pageTitle = 'Vehicle Entry';
$activePage = 'vehicle-entry';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Check if ticket parameter is present (to show printed receipt)
$ticketParam = trim($_GET['ticket'] ?? '');
$ticketData = null;

if (!empty($ticketParam)) {
    if (isset($_SESSION['last_ticket']) && $_SESSION['last_ticket']['ticket_id'] === $ticketParam) {
        $ticketData = $_SESSION['last_ticket'];
    } else {
        // Query database for ticket
        $stmtTicket = $pdo->prepare("
            SELECT r.ticket_id, r.entry_time, 
                   v.registration_number, v.owner_name, v.owner_phone, v.vehicle_type,
                   s.slot_number
            FROM parking_records r
            JOIN vehicles v ON r.vehicle_id = v.id
            JOIN parking_slots s ON r.slot_id = s.id
            WHERE r.ticket_id = ?
            LIMIT 1
        ");
        $stmtTicket->execute([$ticketParam]);
        $row = $stmtTicket->fetch();
        if ($row) {
            $ticketData = [
                'ticket_id'    => $row['ticket_id'],
                'reg_number'   => $row['registration_number'],
                'vehicle_type' => $row['vehicle_type'],
                'owner_name'   => $row['owner_name'],
                'owner_phone'  => $row['owner_phone'],
                'slot_number'  => $row['slot_number'],
                'entry_time'   => date('d M Y, h:i A', strtotime($row['entry_time'])),
                'parking_lot'  => get_setting('parking_lot_name', 'SmartPark Central'),
                'hourly_rate'  => get_rate_for_type($row['vehicle_type'])
            ];
        }
    }
}

// Fetch available slots for initial dropdown (defaulting to Car)
$defaultType = 'Car';
$slotsStmt = $pdo->prepare("SELECT id, slot_number, vehicle_type FROM parking_slots WHERE status = 'Available' ORDER BY vehicle_type, slot_number ASC");
$slotsStmt->execute();
$allAvailableSlots = $slotsStmt->fetchAll();

// Fetch rates for pricing table preview
$rates = $pdo->query("SELECT vehicle_type, hourly_rate FROM parking_rates ORDER BY hourly_rate ASC")->fetchAll();

$csrf_token = get_csrf_token();
?>

<!-- Header -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Walk-In Vehicle Check-In &amp; Entry</h3>
        <p style="font-size: 13px; color: var(--text-muted);">Register walk-in vehicles, assign vacant bays, and generate digital parking receipts.</p>
    </div>
    <div>
        <a href="bookings.php" class="btn btn-secondary">
            <i class="fas fa-calendar-check" style="color: #4F46E5;"></i> Have an Online Booking? Check In Here &rarr;
        </a>
    </div>
</div>

<?php if ($ticketData): ?>
<!-- SUCCESSFUL TICKET RECEIPT DISPLAY -->
<div class="card" style="border: 2px solid #86EFAC; background: #F0FDF4; margin-bottom: 30px;">
    <div class="card-header" style="background: transparent; border-bottom: 1px solid #BBF7D0;">
        <div class="card-title" style="color: #166534;">
            <i class="fas fa-check-circle" style="color: var(--success-green);"></i> Parking Ticket Successfully Issued
        </div>
        <div style="display: flex; gap: 8px;" class="no-print">
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print Ticket
            </button>
            <a href="vehicle-entry.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-plus"></i> New Vehicle Entry
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="parking-ticket" id="printableTicket">
            <div class="ticket-header">
                <img src="../assets/images/logo.png" alt="Chandigarh University" style="width: 50px; height: 50px; object-fit: contain; margin-bottom: 6px;">
                <div style="font-size: 16px; font-weight: 800; color: #1E293B;">Chandigarh University</div>
                <div class="ticket-subtitle">Campus Parking Ticket &bull; SmartPark</div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Campus: Gharuan, Mohali</div>
            </div>

            <div class="ticket-barcode">
                *<?php echo sanitize($ticketData['ticket_id']); ?>*
            </div>

            <div class="ticket-slot-highlight">
                <div class="label">Assigned Bay</div>
                <div class="slot"><?php echo sanitize($ticketData['slot_number']); ?></div>
            </div>

            <div class="ticket-details">
                <div class="ticket-row">
                    <span>Ticket ID:</span>
                    <span style="font-family: monospace;"><?php echo sanitize($ticketData['ticket_id']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Vehicle No:</span>
                    <span><?php echo sanitize($ticketData['reg_number']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Category:</span>
                    <span><?php echo sanitize($ticketData['vehicle_type']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Owner:</span>
                    <span><?php echo sanitize($ticketData['owner_name']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Contact:</span>
                    <span><?php echo sanitize($ticketData['owner_phone']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Entry Time:</span>
                    <span><?php echo sanitize($ticketData['entry_time']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Tariff:</span>
                    <span>₹<?php echo number_format($ticketData['hourly_rate'], 2); ?> / hour</span>
                </div>
            </div>

            <div class="ticket-footer">
                Please present this ticket at the exit gate.<br>
                Thank you for parking with SmartPark!
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Entry Form Grid -->
<div class="entry-grid no-print">
    
    <!-- Left Column: Entry Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-id-card"></i> Vehicle Details &amp; Bay Assignment
            </div>
            <span style="font-size: 12px; color: var(--text-muted);">All fields marked with * are required</span>
        </div>
        <div class="card-body">
            <form action="../actions/vehicle-entry.php" method="POST" id="vehicleEntryForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">

                <div class="form-grid">
                    <!-- Vehicle Registration Number -->
                    <div class="form-group">
                        <label for="registration_number">Vehicle Registration Number <span class="required">*</span></label>
                        <input type="text" name="registration_number" id="registration_number" class="form-control" 
                               placeholder="e.g. KA-01-AB-1234" required maxlength="20" 
                               style="text-transform: uppercase; font-family: monospace; font-size: 15px; font-weight: 700;">
                        <div class="input-hint">Standard RTO registration format.</div>
                    </div>

                    <!-- Vehicle Category -->
                    <div class="form-group">
                        <label for="vehicle_type">Vehicle Category <span class="required">*</span></label>
                        <select name="vehicle_type" id="vehicle_type" class="form-control" required onchange="filterSlotsByType(this.value)">
                            <option value="Car" selected>Car</option>
                            <option value="Two Wheeler">Two Wheeler (Bike/Scooter)</option>
                            <option value="SUV">SUV (Sports Utility Vehicle)</option>
                            <option value="Other">Other / Commercial</option>
                        </select>
                        <div class="input-hint">Changes rate and filters compatible bays.</div>
                    </div>

                    <!-- Owner Name -->
                    <div class="form-group">
                        <label for="owner_name">Owner / Driver Full Name <span class="required">*</span></label>
                        <input type="text" name="owner_name" id="owner_name" class="form-control" 
                               placeholder="e.g. Rajesh Kumar" required maxlength="100">
                    </div>

                    <!-- Owner Phone -->
                    <div class="form-group">
                        <label for="owner_phone">Owner Contact Phone <span class="required">*</span></label>
                        <input type="tel" name="owner_phone" id="owner_phone" class="form-control" 
                               placeholder="e.g. +91 98765 43210" required maxlength="20">
                    </div>

                    <!-- Select Available Slot -->
                    <div class="form-group">
                        <label for="slot_id">Assigned Parking Bay <span class="required">*</span></label>
                        <select name="slot_id" id="slot_id" class="form-control" required>
                            <option value="">-- Choose Vacant Slot --</option>
                            <?php foreach ($allAvailableSlots as $sl): ?>
                                <option value="<?php echo $sl['id']; ?>" data-type="<?php echo sanitize($sl['vehicle_type']); ?>">
                                    <?php echo sanitize($sl['slot_number']); ?> (<?php echo sanitize($sl['vehicle_type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="input-hint" id="slotHint">Showing available bays.</div>
                    </div>

                    <!-- Entry Date & Time (Automatic) -->
                    <div class="form-group">
                        <label>Entry Timestamp (Automatic)</label>
                        <input type="text" class="form-control" readonly value="<?php echo date('d M Y, h:i A'); ?>">
                        <div class="input-hint">Recorded automatically by system clock.</div>
                    </div>
                </div>

                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-check-circle"></i> Check In Vehicle &amp; Generate Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Parking Lot Tariff & Quick Insights -->
    <div>
        <!-- Tariff Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-tags"></i> Hourly Tariff Rates
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Hourly Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rates as $r): ?>
                            <tr>
                                <td style="font-weight: 500;">
                                    <?php echo sanitize($r['vehicle_type']); ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--primary-blue);">
                                    ₹<?php echo number_format($r['hourly_rate'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer" style="font-size: 11.5px; color: var(--text-muted); line-height: 1.4;">
                <i class="fas fa-info-circle"></i> Minimum charge 1 hour. Billing is rounded up to the nearest whole hour upon exit.
            </div>
        </div>

        <!-- System Validation Rules Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-shield-alt"></i> DBMS Rules Enforced
                </div>
            </div>
            <div class="card-body" style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.6;">
                <ul style="padding-left: 18px; margin: 0;">
                    <li><strong>Duplicate Prevention:</strong> Prevents multiple active check-ins for the same registration number.</li>
                    <li><strong>Concurrency Control:</strong> Atomic SQL transaction locks bay assignment.</li>
                    <li><strong>Relational Audit:</strong> Generates unique ticket ID with immutable timestamp.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function filterSlotsByType(selectedType) {
    const slotSelect = document.getElementById('slot_id');
    const options = slotSelect.querySelectorAll('option');
    let matchCount = 0;

    options.forEach(opt => {
        if (!opt.value) return; // Keep the placeholder
        const slotType = opt.getAttribute('data-type');
        if (slotType === selectedType) {
            opt.style.display = '';
            matchCount++;
        } else {
            opt.style.display = 'none';
        }
    });

    // Reset selection to placeholder
    slotSelect.value = '';
    const hint = document.getElementById('slotHint');
    if (matchCount === 0) {
        hint.innerHTML = `<span style="color: var(--danger-red); font-weight: 600;"><i class="fas fa-exclamation-triangle"></i> No vacant ${selectedType} slots currently available!</span>`;
    } else {
        hint.innerHTML = `<span style="color: var(--success-green); font-weight: 500;">Found ${matchCount} available ${selectedType} bay(s).</span>`;
    }
}

// Run once on initial load
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('vehicle_type');
    if (typeSelect) {
        filterSlotsByType(typeSelect.value);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
