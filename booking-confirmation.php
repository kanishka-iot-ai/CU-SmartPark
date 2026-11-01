<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Customer Booking Confirmation & Printable Ticket
 */

require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();
$bookingId = trim($_GET['booking_id'] ?? '');
$booking = null;

// Fetch from session or database
if (!empty($bookingId)) {
    if (isset($_SESSION['customer_booking_confirmation']) && $_SESSION['customer_booking_confirmation']['booking_id'] === $bookingId) {
        $booking = $_SESSION['customer_booking_confirmation'];
    } else {
        $stmt = $pdo->prepare("
            SELECT b.*, s.slot_number
            FROM bookings b
            JOIN parking_slots s ON b.slot_id = s.id
            WHERE b.booking_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch();
        if ($row) {
            $entryTs = strtotime($row['expected_entry_time']);
            $exitTs  = strtotime($row['expected_exit_time']);
            $durationMins = max(60, ceil(($exitTs - $entryTs) / 60));
            $h = floor($durationMins / 60);
            $m = $durationMins % 60;
            
            $booking = [
                'booking_id'       => $row['booking_id'],
                'customer_name'    => $row['customer_name'],
                'customer_phone'   => $row['customer_phone'],
                'vehicle_number'   => $row['vehicle_number'],
                'vehicle_type'     => $row['vehicle_type'],
                'slot_number'      => $row['slot_number'],
                'booking_date'     => date('D, d M Y', strtotime($row['booking_date'])),
                'arrival_time'     => date('h:i A', $entryTs),
                'departure_time'   => date('h:i A', $exitTs),
                'duration_text'    => ($h > 0 ? $h . ' hr ' : '') . $m . ' min',
                'billable_hours'   => ceil($durationMins / 60),
                'hourly_rate'      => get_rate_for_type($row['vehicle_type']),
                'estimated_price'  => $row['estimated_amount'],
                'booking_status'   => $row['booking_status'],
                'parking_lot'      => get_setting('parking_lot_name', 'SmartPark Central')
            ];
        }
    }
}

if (!$booking) {
    header("Location: booking.php");
    exit;
}

$lotName = $booking['parking_lot'];
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

    <title>Booking Confirmation #<?php echo sanitize($booking['booking_id']); ?> - CU SmartPark | Chandigarh University</title>
    
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
            <a href="booking.php" class="nav-link-btn"><i class="fas fa-plus"></i> New Booking</a>
            <a href="booking-lookup.php" class="nav-link-btn"><i class="fas fa-search"></i> Check Booking Status</a>
            <a href="login.php" class="btn btn-secondary btn-sm"><i class="fas fa-user-shield"></i> Admin</a>
        </nav>
    </header>

    <main style="max-width: 600px; margin: 40px auto; padding: 0 20px;">
        
        <!-- Success Banner -->
        <div class="no-print" style="text-align: center; margin-bottom: 24px;">
            <div style="width: 56px; height: 56px; background: #DCFCE7; color: var(--success-green); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);">
                <i class="fas fa-check"></i>
            </div>
            <h2 style="font-size: 24px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Reservation Confirmed!</h2>
            <p style="font-size: 13.5px; color: var(--text-muted);">
                Your bay has been reserved. Please show this confirmation slip to the parking attendant upon arrival.
            </p>
            <div style="margin-top: 14px; display: flex; justify-content: center; gap: 10px;">
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Confirmation
                </button>
                <a href="booking.php" class="btn btn-secondary">
                    <i class="fas fa-calendar-plus"></i> Book Another
                </a>
            </div>
        </div>

        <!-- Printable Parking Reservation Slip -->
        <div class="parking-ticket" style="max-width: 440px;">
            <div class="ticket-header">
                <img src="assets/images/logo.png" alt="Chandigarh University" style="width: 52px; height: 52px; object-fit: contain; margin-bottom: 6px;">
                <div style="font-size: 16px; font-weight: 800; color: #1E293B; line-height: 1.2;">Chandigarh University</div>
                <div class="ticket-subtitle">OFFICIAL CAMPUS PARKING &amp; GATE CLEARANCE PERMIT</div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Directorate of Campus Security &bull; Gharuan (Mohali)</div>
            </div>

            <div class="ticket-barcode">
                *<?php echo sanitize($booking['booking_id']); ?>*
            </div>

            <div class="ticket-slot-highlight">
                <div class="label">Authorized Parking Bay</div>
                <div class="slot"><?php echo sanitize($booking['slot_number']); ?></div>
            </div>

            <div class="ticket-details">
                <div class="ticket-row">
                    <span>Permit ID:</span>
                    <strong style="font-family: monospace; color: var(--primary-blue);"><?php echo sanitize($booking['booking_id']); ?></strong>
                </div>
                <div class="ticket-row">
                    <span>Clearance Status:</span>
                    <span class="badge badge-available">
                        <i class="fas fa-check-circle"></i> <?php echo sanitize($booking['booking_status']); ?>
                    </span>
                </div>
                <div class="ticket-row">
                    <span>Visitor / Faculty Name:</span>
                    <span><?php echo sanitize($booking['customer_name']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Registered Contact:</span>
                    <span><?php echo sanitize($booking['customer_phone']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Vehicle Plate:</span>
                    <strong style="font-family: monospace; font-size: 14px;"><?php echo sanitize($booking['vehicle_number']); ?></strong>
                </div>
                <div class="ticket-row">
                    <span>Vehicle Category:</span>
                    <span><?php echo sanitize($booking['vehicle_type']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Scheduled Date:</span>
                    <span><?php echo sanitize($booking['booking_date']); ?></span>
                </div>
                <div class="ticket-row">
                    <span>Arrival Window:</span>
                    <strong><?php echo sanitize($booking['arrival_time']); ?> &ndash; <?php echo sanitize($booking['departure_time']); ?></strong>
                </div>
                <div class="ticket-row">
                    <span>Allocated Duration:</span>
                    <span><?php echo sanitize($booking['duration_text']); ?></span>
                </div>
                <div class="ticket-row" style="border-top: 2px solid #CBD5E1; margin-top: 8px; padding-top: 8px;">
                    <span style="font-weight: 700; color: var(--text-primary);">Estimated Campus Tariff:</span>
                    <span style="font-weight: 800; color: var(--success-green); font-size: 18px;">
                        ₹<?php echo number_format($booking['estimated_price'], 2); ?>
                    </span>
                </div>
            </div>

            <!-- University Regulations & Gate Checkpoint Verification Block -->
            <div style="margin-top: 14px; border-top: 1px dashed var(--border-color); padding-top: 12px; font-size: 11px; color: var(--text-muted); line-height: 1.5; text-align: left;">
                <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 4px; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em;">University Gate Instructions:</div>
                <ul style="padding-left: 14px; margin: 0 0 10px;">
                    <li>Present this digital pass or printed slip at Gate 1 or Gate 2 Security Booth.</li>
                    <li>Campus speed limit is strictly 15 km/h. Please park only in Bay <?php echo sanitize($booking['slot_number']); ?>.</li>
                    <li>Final tariff is settled upon checkout at the exit gate barrier.</li>
                </ul>
                <div style="display: flex; justify-content: space-between; align-items: flex-end; padding-top: 8px; border-top: 1px solid #F1F5F9; font-size: 11px;">
                    <div>
                        <div style="font-size: 9.5px; text-transform: uppercase; color: #94A3B8;">Designated Gate</div>
                        <strong style="color: #334155;">Gate 1 (NH-05 Main Ingress)</strong>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 9.5px; text-transform: uppercase; color: #94A3B8;">Issuing Authority</div>
                        <strong style="color: #334155;">CU Security Directorate</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="no-print" style="margin-top: 20px; text-align: center; font-size: 12.5px; color: var(--text-muted);">
            Save or bookmark your Booking ID: <strong style="font-family: monospace; color: var(--primary-blue);"><?php echo sanitize($booking['booking_id']); ?></strong><br>
            You can verify or check your reservation anytime on our <a href="booking-lookup.php" style="font-weight: 600;">Status Lookup Page</a>.
        </div>
    </main>

</body>
</html>
