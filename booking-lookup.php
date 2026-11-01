<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Public Customer Booking Status Lookup Page
 */

require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();
$flash = get_flash();

$lotName = get_setting('parking_lot_name', 'SmartPark Central Hub');
$phoneParam = trim($_GET['phone'] ?? '');
$bookingParam = trim($_GET['booking_id'] ?? '');

$bookingResult = null;
$hasSearched = false;

if (!empty($bookingParam) && !empty($phoneParam)) {
    $hasSearched = true;
    $stmt = $pdo->prepare("
        SELECT b.*, s.slot_number
        FROM bookings b
        JOIN parking_slots s ON b.slot_id = s.id
        WHERE b.booking_id = ? AND b.customer_phone = ?
        LIMIT 1
    ");
    $stmt->execute([$bookingParam, $phoneParam]);
    $bookingResult = $stmt->fetch();
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    
    <!-- Apple & Mobile Web App Optimization -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CU SmartPark">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#111827">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    <link rel="icon" type="image/png" href="assets/images/logo.png">

    <title>Check Booking Status - CU SmartPark | Chandigarh University</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .public-nav {
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nav-link-btn {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-secondary);
            padding: 8px 14px;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }
        .nav-link-btn:hover {
            color: var(--primary-blue);
            background: var(--accent-blue-light);
        }
        .nav-link-btn.active {
            color: var(--primary-blue);
            background: var(--accent-blue-light);
        }
    </style>
</head>
<body style="background-color: var(--bg-primary);">

    <!-- Navigation -->
    <header class="public-nav no-print">
        <div style="display: flex; align-items: center; gap: 12px;">
            <img src="assets/images/logo.png" alt="Chandigarh University Logo" style="width: 44px; height: 44px; object-fit: contain;">
            <div>
                <strong style="font-size: 18px; font-weight: 800; color: var(--text-primary);">CU SmartPark</strong>
                <span style="font-size: 11px; font-weight: 700; color: #DC2626; text-transform: uppercase; margin-left: 6px;">Chandigarh University</span>
            </div>
        </div>

        <nav class="nav-links">
            <a href="booking.php" class="nav-link-btn"><i class="fas fa-calendar-check"></i> Book a Slot</a>
            <a href="booking-lookup.php" class="nav-link-btn active"><i class="fas fa-search"></i> Check Booking Status</a>
            <a href="login.php" class="btn btn-secondary btn-sm"><i class="fas fa-user-shield"></i> Admin</a>
        </nav>
    </header>

    <main style="max-width: 680px; margin: 40px auto; padding: 0 20px;">
        
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo sanitize($flash['type']); ?>">
                <div>
                    <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                    <?php echo sanitize($flash['message']); ?>
                </div>
                <button type="button" class="alert-close">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Lookup Form Card -->
        <div class="card no-print" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-search"></i> Verify &amp; Track Reservation
                </div>
                <span style="font-size: 12px; color: var(--text-muted);"><i class="fas fa-user-lock"></i> Privacy Protected</span>
            </div>
            <div class="card-body">
                <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 18px;">
                    Enter your Booking Reference ID along with the registered phone number to view your parking bay pass.
                </p>

                <form action="booking-lookup.php" method="GET">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="booking_id">Booking ID <span class="required" style="color: var(--danger-red);">*</span></label>
                            <input type="text" name="booking_id" id="booking_id" class="form-control" 
                                   placeholder="e.g. BK-20261101-1001" required 
                                   value="<?php echo sanitize($bookingParam); ?>" 
                                   style="font-family: monospace; font-size: 15px; font-weight: 700; text-transform: uppercase;">
                        </div>

                        <div class="form-group">
                            <label for="phone">Registered Phone Number <span class="required" style="color: var(--danger-red);">*</span></label>
                            <input type="tel" name="phone" id="phone" class="form-control" 
                                   placeholder="e.g. +91 98765 43210" required 
                                   value="<?php echo sanitize($phoneParam); ?>">
                        </div>
                    </div>

                    <div style="margin-top: 14px; display: flex; gap: 10px; justify-content: flex-end;">
                        <?php if ($hasSearched): ?>
                            <a href="booking-lookup.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search Reservation
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($hasSearched && !$bookingResult): ?>
            <div class="alert alert-danger no-print">
                <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                No reservation found for Booking ID "<strong><?php echo sanitize($bookingParam); ?></strong>" with the specified phone number. Please verify your details.
            </div>
        <?php endif; ?>

        <!-- Search Result Card -->
        <?php if ($bookingResult): ?>
            <?php
            $statusBadgeClass = 'badge-available';
            if ($bookingResult['booking_status'] === 'Checked In') $statusBadgeClass = 'badge-occupied';
            elseif ($bookingResult['booking_status'] === 'Cancelled') $statusBadgeClass = 'badge-danger';
            elseif ($bookingResult['booking_status'] === 'Completed') $statusBadgeClass = 'badge-completed';
            ?>
            <div class="card">
                <div class="card-header" style="background: #F8FAFC;">
                    <div class="card-title">
                        <i class="fas fa-calendar-check" style="color: var(--primary-blue);"></i> Reservation Found
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <span class="badge <?php echo $statusBadgeClass; ?>" style="font-size: 12px; padding: 4px 10px;">
                            <?php echo sanitize($bookingResult['booking_status']); ?>
                        </span>
                        <button type="button" class="btn btn-primary btn-sm no-print" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Slip
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="parking-ticket" style="box-shadow: none; border-color: #CBD5E1;">
                        <div class="ticket-header">
                            <img src="assets/images/logo.png" alt="Chandigarh University" style="width: 50px; height: 50px; object-fit: contain; margin-bottom: 6px;">
                            <div style="font-size: 16px; font-weight: 800; color: #1E293B;">Chandigarh University</div>
                            <div class="ticket-subtitle">Campus Parking Pass &bull; SmartPark</div>
                        </div>

                        <div class="ticket-barcode">
                            *<?php echo sanitize($bookingResult['booking_id']); ?>*
                        </div>

                        <div class="ticket-slot-highlight">
                            <div class="label">Designated Bay</div>
                            <div class="slot"><?php echo sanitize($bookingResult['slot_number']); ?></div>
                        </div>

                        <div class="ticket-details">
                            <div class="ticket-row">
                                <span>Booking Reference:</span>
                                <strong style="font-family: monospace; color: var(--primary-blue);"><?php echo sanitize($bookingResult['booking_id']); ?></strong>
                            </div>
                            <div class="ticket-row">
                                <span>Customer Name:</span>
                                <span><?php echo sanitize($bookingResult['customer_name']); ?></span>
                            </div>
                            <div class="ticket-row">
                                <span>Vehicle Registration:</span>
                                <strong style="font-family: monospace;"><?php echo sanitize($bookingResult['vehicle_number']); ?></strong>
                            </div>
                            <div class="ticket-row">
                                <span>Vehicle Type:</span>
                                <span><?php echo sanitize($bookingResult['vehicle_type']); ?></span>
                            </div>
                            <div class="ticket-row">
                                <span>Scheduled Date:</span>
                                <span><?php echo date('D, d M Y', strtotime($bookingResult['booking_date'])); ?></span>
                            </div>
                            <div class="ticket-row">
                                <span>Expected Arrival:</span>
                                <strong><?php echo date('h:i A', strtotime($bookingResult['expected_entry_time'])); ?></strong>
                            </div>
                            <div class="ticket-row">
                                <span>Expected Departure:</span>
                                <strong><?php echo date('h:i A', strtotime($bookingResult['expected_exit_time'])); ?></strong>
                            </div>
                            <?php if ($bookingResult['actual_entry_time']): ?>
                                <div class="ticket-row">
                                    <span>Actual Check-In:</span>
                                    <strong style="color: var(--primary-blue);"><?php echo date('d M Y, h:i A', strtotime($bookingResult['actual_entry_time'])); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if ($bookingResult['actual_exit_time']): ?>
                                <div class="ticket-row">
                                    <span>Actual Check-Out:</span>
                                    <strong style="color: var(--success-green);"><?php echo date('d M Y, h:i A', strtotime($bookingResult['actual_exit_time'])); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="ticket-row" style="border-top: 2px solid #CBD5E1; margin-top: 8px; padding-top: 8px;">
                                <span>Estimated Fee:</span>
                                <strong style="color: var(--success-green); font-size: 16px;">₹<?php echo number_format($bookingResult['estimated_amount'], 2); ?></strong>
                            </div>
                            <?php if ($bookingResult['final_amount'] !== null): ?>
                                <div class="ticket-row">
                                    <span>Final Settled Amount:</span>
                                    <strong style="color: var(--primary-blue); font-size: 18px;">₹<?php echo number_format($bookingResult['final_amount'], 2); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Customer Cancellation Option (if Confirmed) -->
                    <?php if ($bookingResult['booking_status'] === 'Confirmed'): ?>
                        <div class="no-print" style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span style="font-size: 12.5px; color: var(--text-muted);">
                                Plans changed? You can release your reservation prior to check-in.
                            </span>
                            <form action="actions/customer-cancel-booking.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel your reservation? This will free the parking bay for other drivers.');">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                <input type="hidden" name="booking_id" value="<?php echo sanitize($bookingResult['booking_id']); ?>">
                                <input type="hidden" name="customer_phone" value="<?php echo sanitize($bookingResult['customer_phone']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times"></i> Cancel Reservation
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer style="margin-top: 50px; background: #FFFFFF; border-top: 1px solid var(--border-color); padding: 24px 30px; text-align: center; font-size: 12.5px; color: var(--text-muted);" class="no-print">
        <strong><?php echo sanitize($lotName); ?></strong> &bull; Customer Reservation Portal
    </footer>

</body>
</html>
