<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Public Customer Online Reservation Page
 * Redesigned for a professional single-screen, non-scrolling desktop experience
 */

require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();
$flash = get_flash();

$lotName  = get_setting('parking_lot_name', 'SmartPark Central Hub');
$phone    = get_setting('contact_phone', '+91 98765 43210');
$address  = get_setting('address', 'Tech Park Boulevard, Electronic City, Bangalore');
$rates    = $pdo->query("SELECT vehicle_type, hourly_rate FROM parking_rates ORDER BY hourly_rate ASC")->fetchAll();

$todayDate = date('Y-m-d');
$defaultArrival = date('H:i', ceil(time() / 1800) * 1800); // nearest 30 mins
$defaultDeparture = date('H:i', (ceil(time() / 1800) * 1800) + 7200); // +2 hours

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

    <title>Campus Parking Reservation - Chandigarh University | CU SmartPark</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Base Universal Styles */
        :root {
            --nav-height: 52px;
            --substrip-height: 30px;
            --footer-height: 28px;
        }

        .booking-page-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--bg-primary);
        }

        .public-nav {
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-color);
            padding: 0 24px;
            height: var(--nav-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            flex-shrink: 0;
        }

        .nav-brand-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-brand-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
        }

        .nav-brand-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.01em;
            line-height: 1.1;
        }

        .nav-brand-sub {
            font-size: 10px;
            font-weight: 800;
            color: #DC2626;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nav-gate-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 9999px;
            margin-left: 8px;
        }

        .nav-gate-dot {
            width: 6px;
            height: 6px;
            background: #10B981;
            border-radius: 50%;
            display: inline-block;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-link-btn {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: var(--radius-md);
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .nav-link-btn:hover {
            color: var(--primary-blue);
            background: var(--accent-blue-light);
        }

        .nav-link-btn.active {
            color: var(--primary-blue);
            background: var(--accent-blue-light);
            font-weight: 700;
        }

        .hero-strip {
            background: #0F172A;
            color: #CBD5E1;
            height: var(--substrip-height);
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11.5px;
            font-weight: 500;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .type-radio-card {
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 8px 6px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: #FFFFFF;
            user-select: none;
        }

        .type-radio-card:hover {
            border-color: var(--primary-blue);
            background: #F8FAFC;
        }

        .type-radio-card.selected {
            border-color: var(--primary-blue);
            background: var(--accent-blue-light);
            box-shadow: 0 0 0 2.5px rgba(37, 99, 235, 0.15);
        }

        .type-radio-card i {
            font-size: 18px;
            color: var(--primary-blue);
            margin-bottom: 3px;
            display: block;
        }

        .type-radio-card .type-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .type-radio-card .type-rate {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .slot-pick-box {
            border: 1.5px solid #86EFAC;
            background: #F0FDF4;
            border-radius: var(--radius-md);
            padding: 5px 12px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            flex-shrink: 0;
            min-width: 80px;
        }

        .slot-pick-box:hover {
            border-color: #22C55E;
            transform: translateY(-1px);
        }

        .slot-pick-box.selected {
            border-color: var(--primary-blue);
            background: #EFF6FF;
            box-shadow: 0 0 0 2.5px rgba(37, 99, 235, 0.2);
        }

        .slot-pick-box .slot-number-text {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .slot-pick-box .slot-status-text {
            font-size: 9.5px;
            font-weight: 700;
            color: var(--success-green);
            text-transform: uppercase;
        }

        .public-footer-strip {
            background: #FFFFFF;
            border-top: 1px solid var(--border-color);
            padding: 5px 24px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            height: var(--footer-height);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            line-height: 1.2;
        }

        /* -------------------------------------------------------------
           DESKTOP VIEWPORT LOCK (>= 1025px): Zero Scroll Single-Screen
           ------------------------------------------------------------- */
        @media (min-width: 1025px) {
            html, body {
                height: 100vh;
                max-height: 100vh;
                overflow: hidden !important;
                margin: 0;
                padding: 0;
            }

            .booking-page-container {
                height: 100vh;
                max-height: 100vh;
                overflow: hidden;
            }

            .booking-main-area {
                flex: 1;
                min-height: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 10px 24px;
                overflow: hidden;
            }

            .booking-grid {
                display: grid;
                grid-template-columns: 1.85fr 1.15fr;
                gap: 18px;
                width: 100%;
                max-width: 1240px;
                height: 100%;
                max-height: calc(100vh - var(--nav-height) - var(--substrip-height) - var(--footer-height) - 24px);
                align-items: stretch;
            }

            .booking-form-card {
                height: 100%;
                display: flex;
                flex-direction: column;
                margin-bottom: 0;
                overflow: hidden;
            }

            .booking-form-card .card-header {
                padding: 8px 18px;
                flex-shrink: 0;
            }

            .booking-form-card .card-body {
                padding: 10px 18px;
                flex: 1;
                min-height: 0;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                overflow: hidden;
            }

            .step-block {
                margin-bottom: 7px;
            }

            .step-label {
                font-size: 12px;
                font-weight: 700;
                color: var(--text-primary);
                margin-bottom: 5px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .type-selector-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
                margin-bottom: 0;
            }

            .booking-schedule-grid {
                display: grid;
                grid-template-columns: 1.2fr 1fr 1fr;
                gap: 10px;
            }

            .booking-schedule-grid .form-group {
                margin-bottom: 0;
            }

            .booking-schedule-grid label {
                font-size: 11px;
                font-weight: 600;
                color: var(--text-secondary);
                margin-bottom: 2px;
                display: block;
            }

            .booking-schedule-grid .form-control {
                height: 33px;
                padding: 5px 10px;
                font-size: 12.5px;
            }

            #slotsContainer {
                display: flex;
                gap: 8px;
                overflow-x: auto;
                overflow-y: hidden;
                padding: 2px 1px;
                height: 44px;
                align-items: center;
            }

            .customer-info-grid {
                display: grid;
                grid-template-columns: 1.15fr 1.2fr 1.15fr;
                gap: 10px;
            }

            .customer-info-grid .form-group {
                margin-bottom: 0;
            }

            .customer-info-grid label {
                font-size: 11px;
                font-weight: 600;
                color: var(--text-secondary);
                margin-bottom: 2px;
                display: block;
            }

            .customer-info-grid .form-control {
                height: 33px;
                padding: 5px 10px;
                font-size: 12.5px;
            }

            .customer-info-grid .input-hint {
                display: none;
            }

            .price-action-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: #F8FAFC;
                border: 1px solid var(--border-color);
                border-radius: var(--radius-md);
                padding: 7px 14px;
                margin-top: 6px;
                gap: 12px;
                flex-shrink: 0;
            }

            .price-action-metrics {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .price-metric-item {
                font-size: 11.5px;
                color: var(--text-muted);
                line-height: 1.2;
            }

            .price-metric-item strong {
                color: var(--text-primary);
                font-size: 12px;
            }

            .price-action-total {
                font-size: 20px;
                font-weight: 800;
                color: var(--success-green);
                line-height: 1;
            }

            .price-action-btn {
                padding: 8px 18px;
                font-size: 13px;
                font-weight: 700;
                white-space: nowrap;
                height: 38px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .right-side-panel {
                height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                gap: 10px;
                overflow: hidden;
            }

            .right-side-panel .card {
                margin-bottom: 0;
            }

            .tariff-table-card .card-header {
                padding: 7px 14px;
            }

            .tariff-table-card table td, .tariff-table-card table th {
                padding: 5px 12px;
                font-size: 11.5px;
            }

            .tariff-table-card .card-footer {
                padding: 5px 12px;
                font-size: 10.5px;
                line-height: 1.3;
            }

            .instructions-card .card-header {
                padding: 7px 14px;
            }

            .instructions-card .card-body {
                padding: 8px 14px;
                font-size: 11.5px;
                line-height: 1.4;
            }

            .instructions-card .step-row {
                display: flex;
                gap: 8px;
                margin-bottom: 5px;
                align-items: flex-start;
            }

            .instructions-card .step-row:last-child {
                margin-bottom: 0;
            }

            .instructions-card .step-bullet {
                width: 18px;
                height: 18px;
                border-radius: 50%;
                background: #EFF6FF;
                color: var(--primary-blue);
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 10px;
                flex-shrink: 0;
                margin-top: 1px;
            }

            .lookup-callout-card {
                padding: 8px 14px;
                border-radius: var(--radius-lg);
                background: #FFFFFF;
                border: 1px solid var(--border-color);
                box-shadow: var(--shadow-sm);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }

            .lookup-callout-card h4 {
                font-size: 12.5px;
                font-weight: 700;
                margin: 0 0 2px 0;
                color: var(--text-primary);
            }

            .lookup-callout-card p {
                font-size: 11px;
                color: var(--text-muted);
                margin: 0;
            }
        }

        /* -------------------------------------------------------------
           RESPONSIVE MOBILE / TABLET VIEW (<= 1024px): Natural Touch Scroll
           ------------------------------------------------------------- */
        @media (max-width: 1024px) {
            html, body {
                height: auto;
                overflow-y: auto !important;
            }

            .booking-main-area {
                padding: 20px 16px;
            }

            .booking-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 20px;
                max-width: 720px;
                margin: 0 auto;
            }

            .type-selector-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .booking-schedule-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 12px;
            }

            #slotsContainer {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 10px;
                max-height: 160px;
                overflow-y: auto;
                padding: 4px;
            }

            .customer-info-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .price-action-bar {
                display: flex;
                flex-direction: column;
                gap: 12px;
                background: #F8FAFC;
                border: 1px solid var(--border-color);
                border-radius: var(--radius-md);
                padding: 14px;
                margin-top: 14px;
            }

            .price-action-btn {
                width: 100%;
                justify-content: center;
            }

            .public-nav {
                height: auto;
                padding: 12px 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .nav-links {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .hero-strip {
                height: auto;
                padding: 8px 16px;
                flex-direction: column;
                text-align: center;
                gap: 4px;
            }

            .public-footer-strip {
                height: auto;
                padding: 14px 16px;
                flex-direction: column;
                gap: 6px;
            }
        }
    </style>
</head>
<body>

    <div class="booking-page-container">
        
        <!-- Public Navigation Header Bar -->
        <header class="public-nav">
            <div class="nav-brand-group">
                <img src="assets/images/logo.png" alt="Chandigarh University Emblem" class="nav-brand-logo">
                <div>
                    <div class="nav-brand-title">CU SmartPark</div>
                    <div class="nav-brand-sub">Chandigarh University</div>
                </div>
                <div class="nav-gate-badge">
                    <span class="nav-gate-dot"></span>
                    <span>Gate 1 Ingress Active</span>
                </div>
            </div>

            <nav class="nav-links">
                <a href="booking.php" class="nav-link-btn active"><i class="fas fa-calendar-check"></i> Book a Slot</a>
                <a href="booking-lookup.php" class="nav-link-btn"><i class="fas fa-search"></i> Check Booking Status</a>
                <a href="login.php" class="btn btn-secondary btn-sm" style="font-size: 12px; padding: 6px 12px;"><i class="fas fa-user-shield"></i> Admin Sign In</a>
            </nav>
        </header>

        <!-- Sub-header Compact Institutional Banner -->
        <div class="hero-strip">
            <div>
                <i class="fas fa-shield-alt" style="color: #F87171; margin-right: 5px;"></i>
                <strong>Directorate of Campus Security &amp; Transport Facilities</strong> &bull; Gharuan Campus (NH-05), Mohali
            </div>
            <div style="color: #94A3B8;">
                Pre-book assigned parking bay for automated barrier clearance
            </div>
        </div>

        <!-- Main Booking Screen Area -->
        <main class="booking-main-area">
            <div class="booking-grid">
                
                <!-- Left Column: The Interactive Reservation Form Card -->
                <div class="card booking-form-card">
                    <div class="card-header">
                        <div class="card-title" style="font-size: 14.5px;">
                            <i class="fas fa-ticket-alt"></i> Online Parking Reservation Form
                        </div>
                        <span style="font-size: 11.5px; color: var(--text-muted); font-weight: 500;">
                            <i class="fas fa-check-circle" style="color: var(--success-green);"></i> Instant Bay Assignment
                        </span>
                    </div>

                    <div class="card-body">
                        
                        <?php if ($flash): ?>
                            <div class="alert alert-<?php echo sanitize($flash['type']); ?>" style="padding: 6px 12px; margin-bottom: 8px; font-size: 12px;">
                                <div>
                                    <i class="fas fa-exclamation-circle" style="margin-right: 6px;"></i>
                                    <?php echo sanitize($flash['message']); ?>
                                </div>
                                <button type="button" class="alert-close" style="font-size: 16px;">&times;</button>
                            </div>
                        <?php endif; ?>

                        <form action="actions/customer-booking.php" method="POST" id="bookingForm" autocomplete="off" style="display: flex; flex-direction: column; height: 100%; justify-content: space-between;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                            <input type="hidden" name="slot_id" id="selectedSlotId" required>

                            <div>
                                <!-- Step 1: Vehicle Category Selection -->
                                <div class="step-block">
                                    <div class="step-label">
                                        <span>1. Select Vehicle Category <span class="required" style="color: var(--danger-red);">*</span></span>
                                    </div>
                                    <input type="hidden" name="vehicle_type" id="selectedVehicleType" value="Car">
                                    <div class="type-selector-grid">
                                        <div class="type-radio-card" data-type="Two Wheeler" onclick="selectType('Two Wheeler')">
                                            <i class="fas fa-motorcycle"></i>
                                            <div class="type-name">Two Wheeler</div>
                                            <div class="type-rate">₹10 / hr</div>
                                        </div>
                                        <div class="type-radio-card selected" data-type="Car" onclick="selectType('Car')">
                                            <i class="fas fa-car"></i>
                                            <div class="type-name">Car / Sedan</div>
                                            <div class="type-rate">₹20 / hr</div>
                                        </div>
                                        <div class="type-radio-card" data-type="SUV" onclick="selectType('SUV')">
                                            <i class="fas fa-truck-pickup"></i>
                                            <div class="type-name">SUV</div>
                                            <div class="type-rate">₹30 / hr</div>
                                        </div>
                                        <div class="type-radio-card" data-type="Other" onclick="selectType('Other')">
                                            <i class="fas fa-truck"></i>
                                            <div class="type-name">Other</div>
                                            <div class="type-rate">₹25 / hr</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 2: Date & Time Schedule -->
                                <div class="step-block">
                                    <div class="step-label">
                                        <span>2. Schedule &amp; Time Window <span class="required" style="color: var(--danger-red);">*</span></span>
                                    </div>
                                    <div class="booking-schedule-grid">
                                        <div class="form-group">
                                            <label for="booking_date">Reservation Date</label>
                                            <input type="date" name="booking_date" id="booking_date" class="form-control" 
                                                   min="<?php echo $todayDate; ?>" value="<?php echo $todayDate; ?>" required onchange="triggerAvailabilityCheck()">
                                        </div>
                                        <div class="form-group">
                                            <label for="entry_time">Expected Arrival</label>
                                            <input type="time" name="entry_time" id="entry_time" class="form-control" 
                                                   value="<?php echo $defaultArrival; ?>" required onchange="triggerAvailabilityCheck()">
                                        </div>
                                        <div class="form-group">
                                            <label for="exit_time">Expected Departure</label>
                                            <input type="time" name="exit_time" id="exit_time" class="form-control" 
                                                   value="<?php echo $defaultDeparture; ?>" required onchange="triggerAvailabilityCheck()">
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 3: Choose Parking Bay -->
                                <div class="step-block">
                                    <div class="step-label">
                                        <span>3. Choose Your Preferred Parking Bay <span class="required" style="color: var(--danger-red);">*</span></span>
                                        <span id="availabilityBadge" class="badge badge-available" style="font-size: 10px; padding: 2px 7px;">Checking availability...</span>
                                    </div>
                                    
                                    <!-- Available Bays Container -->
                                    <div id="slotsContainer">
                                        <!-- Dynamically populated via AJAX -->
                                    </div>
                                    <div id="noSlotsMessage" style="display: none; padding: 8px 12px; text-align: center; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: var(--radius-md); color: #991B1B; font-size: 11.5px;">
                                        <i class="fas fa-exclamation-triangle"></i> No parking bays are currently available for this category and time slot. Try adjusting arrival or departure.
                                    </div>
                                </div>

                                <!-- Step 4: Vehicle & Contact Details -->
                                <div class="step-block" style="margin-bottom: 0;">
                                    <div class="step-label">
                                        <span>4. Vehicle &amp; Contact Details <span class="required" style="color: var(--danger-red);">*</span></span>
                                    </div>
                                    <div class="customer-info-grid">
                                        <div class="form-group">
                                            <label for="vehicle_number">Vehicle Registration No</label>
                                            <input type="text" name="vehicle_number" id="vehicle_number" class="form-control" 
                                                   placeholder="e.g. KA-01-AB-1234" required maxlength="20" 
                                                   style="text-transform: uppercase; font-family: monospace; font-size: 13.5px; font-weight: 700;">
                                        </div>

                                        <div class="form-group">
                                            <label for="customer_name">Full Name</label>
                                            <input type="text" name="customer_name" id="customer_name" class="form-control" 
                                                   placeholder="e.g. Rahul Sharma" required maxlength="100">
                                        </div>

                                        <div class="form-group">
                                            <label for="customer_phone">Phone Number</label>
                                            <input type="tel" name="customer_phone" id="customer_phone" class="form-control" 
                                                   placeholder="e.g. +91 98765 43210" required maxlength="20">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Price Calculation & Submit Bar -->
                            <div class="price-action-bar">
                                <div class="price-action-metrics">
                                    <div class="price-metric-item">
                                        <div>Duration: <strong id="estDurationText">2 hr 0 min</strong></div>
                                        <div>Tariff: <strong id="estRateText">₹20.00 / hr</strong></div>
                                    </div>
                                    <div style="border-left: 1px solid var(--border-color); height: 26px;"></div>
                                    <div>
                                        <div style="font-size: 10px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Estimated Fee</div>
                                        <div class="price-action-total" id="estTotalFee">₹40.00</div>
                                    </div>
                                </div>

                                <button type="submit" id="submitBookingBtn" class="btn btn-primary price-action-btn" disabled>
                                    <i class="fas fa-check-circle"></i> Reserve Bay &amp; Confirm
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Tariff & Campus Ingress Info -->
                <div class="right-side-panel">
                    
                    <!-- Tariff Rates Card -->
                    <div class="card tariff-table-card">
                        <div class="card-header">
                            <div class="card-title" style="font-size: 13.5px;">
                                <i class="fas fa-tags"></i> Transparent Tariff Rates
                            </div>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <table class="table" style="margin-bottom: 0;">
                                <thead>
                                    <tr>
                                        <th>Vehicle Category</th>
                                        <th style="text-align: right;">Hourly Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rates as $r): ?>
                                        <tr>
                                            <td><strong><?php echo sanitize($r['vehicle_type']); ?></strong></td>
                                            <td style="text-align: right; font-weight: 700; color: var(--primary-blue);">
                                                ₹<?php echo number_format($r['hourly_rate'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer">
                            <i class="fas fa-info-circle" style="color: var(--primary-blue);"></i> Minimum charge 1 hour. Pay at exit gate via Cash, UPI, or Card.
                        </div>
                    </div>

                    <!-- Campus Gate Protocol Card -->
                    <div class="card instructions-card">
                        <div class="card-header">
                            <div class="card-title" style="font-size: 13.5px;">
                                <i class="fas fa-route"></i> Campus Ingress Protocol
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="step-row">
                                <div class="step-bullet">1</div>
                                <div><strong>Pre-Book:</strong> Select your category and bay to receive a unique Booking ID.</div>
                            </div>
                            <div class="step-row">
                                <div class="step-bullet">2</div>
                                <div><strong>Gate 1 Ingress:</strong> Present your Booking ID to security attendant for barrier clearance.</div>
                            </div>
                            <div class="step-row">
                                <div class="step-bullet">3</div>
                                <div><strong>Checkout:</strong> Settle payment for actual stay duration upon exit at Gate 2.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Booking Status Lookup Callout -->
                    <div class="lookup-callout-card">
                        <div>
                            <h4>Existing Reservation?</h4>
                            <p>Look up your reservation details or print your ticket slip.</p>
                        </div>
                        <a href="booking-lookup.php" class="btn btn-secondary btn-sm" style="font-size: 11.5px; padding: 6px 12px; white-space: nowrap;">
                            <i class="fas fa-clipboard-check"></i> Check Status
                        </a>
                    </div>
                </div>

            </div>
        </main>

        <!-- Compact Single-Line Footer -->
        <footer class="public-footer-strip">
            <span>&copy; <?php echo date('Y'); ?> Chandigarh University &bull; Directorate of Campus Security &amp; Transport &bull; NH-05 Gharuan Campus, Mohali &bull; Security Helpline: +91 160 3051003 &bull; transport@cumail.in</span>
        </footer>

    </div>

    <!-- Client-Side Dynamic Availability & Fee Calculation Script -->
    <script>
    let currentSelectedType = 'Car';
    let currentSelectedSlotId = null;

    function selectType(type) {
        currentSelectedType = type;
        document.getElementById('selectedVehicleType').value = type;
        
        document.querySelectorAll('.type-radio-card').forEach(card => {
            if (card.getAttribute('data-type') === type) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });

        triggerAvailabilityCheck();
    }

    function selectSlot(slotId, element) {
        currentSelectedSlotId = slotId;
        document.getElementById('selectedSlotId').value = slotId;

        document.querySelectorAll('.slot-pick-box').forEach(box => box.classList.remove('selected'));
        element.classList.add('selected');

        document.getElementById('submitBookingBtn').disabled = false;
        document.getElementById('submitBookingBtn').innerHTML = `<i class="fas fa-check-circle"></i> Reserve Bay &amp; Confirm`;
    }

    function triggerAvailabilityCheck() {
        const date = document.getElementById('booking_date').value;
        const entryTime = document.getElementById('entry_time').value;
        const exitTime = document.getElementById('exit_time').value;
        const badge = document.getElementById('availabilityBadge');
        const container = document.getElementById('slotsContainer');
        const noSlots = document.getElementById('noSlotsMessage');
        const submitBtn = document.getElementById('submitBookingBtn');

        badge.className = 'badge badge-occupied';
        badge.innerText = 'Checking...';
        currentSelectedSlotId = null;
        document.getElementById('selectedSlotId').value = '';
        submitBtn.disabled = true;

        fetch(`actions/check-availability.php?vehicle_type=${encodeURIComponent(currentSelectedType)}&booking_date=${date}&entry_time=${entryTime}&exit_time=${exitTime}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    badge.className = 'badge badge-occupied';
                    badge.innerText = 'Invalid Time';
                    container.innerHTML = '';
                    noSlots.style.display = 'block';
                    noSlots.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${data.error}`;
                    return;
                }

                // Update Price Summary
                document.getElementById('estDurationText').innerText = data.duration_text;
                document.getElementById('estRateText').innerText = `₹${parseFloat(data.hourly_rate).toFixed(2)} / hr`;
                document.getElementById('estTotalFee').innerText = data.formatted_amount;

                // Update Slots Grid
                if (data.available_count === 0) {
                    badge.className = 'badge badge-occupied';
                    badge.innerText = 'No Bays Free';
                    container.innerHTML = '';
                    noSlots.style.display = 'block';
                    noSlots.innerHTML = `<i class="fas fa-exclamation-triangle"></i> No ${currentSelectedType} bays are available for this time window. Try another slot or category.`;
                } else {
                    badge.className = 'badge badge-available';
                    badge.innerText = `${data.available_count} Bays Vacant`;
                    noSlots.style.display = 'none';

                    let html = '';
                    data.slots.forEach(s => {
                        html += `
                            <div class="slot-pick-box" onclick="selectSlot(${s.id}, this)">
                                <div class="slot-number-text">${s.slot_number}</div>
                                <div class="slot-status-text">Vacant</div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                }
            })
            .catch(err => {
                badge.innerText = 'Error checking slots';
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        triggerAvailabilityCheck();
    });
    </script>
</body>
</html>
