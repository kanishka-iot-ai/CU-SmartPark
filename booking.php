<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Public Customer Online Reservation Page
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
        .public-nav {
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
            flex-wrap: wrap;
            gap: 12px;
        }
        .booking-grid {
            display: grid;
            grid-template-columns: 2fr 1.1fr;
            gap: 28px;
        }
        @media (max-width: 860px) {
            .booking-grid {
                grid-template-columns: 1fr;
            }
            .public-nav {
                padding: 14px 20px;
            }
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
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
        .hero-banner {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            color: #FFFFFF;
            padding: 44px 30px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .hero-title {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }
        .hero-subtitle {
            font-size: 14.5px;
            color: #94A3B8;
            max-width: 620px;
            margin: 0 auto;
        }
        .type-selector-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .type-radio-card {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px 10px;
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
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .type-radio-card i {
            font-size: 22px;
            color: var(--primary-blue);
            margin-bottom: 6px;
            display: block;
        }
        .type-radio-card .type-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .type-radio-card .type-rate {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .slot-pick-box {
            border: 2px solid #86EFAC;
            background: #F0FDF4;
            border-radius: var(--radius-md);
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        .slot-pick-box:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .slot-pick-box.selected {
            border-color: var(--primary-blue);
            background: #EFF6FF;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
        .price-summary-box {
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin: 20px 0;
        }
    </style>
</head>
<body style="background-color: var(--bg-primary);">

    <!-- Campus Gate Operations Bar -->
    <div class="campus-announcement-bar" style="background: #111827; color: #E2E8F0; padding: 8px 24px; font-size: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span class="badge" style="background: #16A34A; color: #FFFFFF; font-size: 10px; padding: 2px 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Gate 1 Ingress Active</span>
            <span style="color: #CBD5E1;">Main Campus Entry (NH-05) &bull; Security Attendant on Duty</span>
        </div>
        <div style="display: flex; align-items: center; gap: 16px; color: #94A3B8; font-size: 12px;">
            <span><i class="fas fa-phone-alt" style="margin-right: 4px; color: #F87171;"></i> Security Control: +91 160 3051003</span>
            <span><i class="fas fa-envelope" style="margin-right: 4px; color: #60A5FA;"></i> transport@cumail.in</span>
        </div>
    </div>

    <!-- Public Navigation Bar -->
    <header class="public-nav">
        <div style="display: flex; align-items: center; gap: 12px;">
            <img src="assets/images/logo.png" alt="Chandigarh University Logo" style="width: 44px; height: 44px; object-fit: contain;">
            <div>
                <strong style="font-size: 18px; font-weight: 800; color: var(--text-primary); letter-spacing: -0.01em;">CU SmartPark</strong>
                <span style="font-size: 11px; font-weight: 700; color: #DC2626; text-transform: uppercase; margin-left: 6px;">Chandigarh University</span>
            </div>
        </div>

        <nav class="nav-links">
            <a href="booking.php" class="nav-link-btn active"><i class="fas fa-calendar-check"></i> Book a Slot</a>
            <a href="booking-lookup.php" class="nav-link-btn"><i class="fas fa-search"></i> Check Booking Status</a>
            <a href="login.php" class="btn btn-secondary btn-sm" style="margin-left: 10px;"><i class="fas fa-user-shield"></i> Admin Sign In</a>
        </nav>
    </header>

    <!-- Hero Banner -->
    <section class="hero-banner">
        <div style="margin-bottom: 10px;">
            <span style="background: rgba(220, 38, 38, 0.2); border: 1px solid rgba(220, 38, 38, 0.4); color: #FCA5A5; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.08em;">
                Directorate of Campus Security &amp; Transport Facilities
            </span>
        </div>
        <h1 class="hero-title" style="font-size: 28px; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 8px;">Campus Vehicle Parking &amp; Bay Reservation</h1>
        <p class="hero-subtitle" style="font-size: 14.5px; color: #CBD5E1; max-width: 680px; margin: 0 auto; line-height: 1.6;">
            Authorized reservation portal for visiting delegates, university faculty, students, and guest vehicles. Pre-book your assigned parking bay for automated barrier clearance at Gharuan Campus, Mohali.
        </p>
    </section>

    <!-- Main Booking Container -->
    <main style="max-width: 1140px; margin: 30px auto; padding: 0 20px;">
        
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo sanitize($flash['type']); ?>">
                <div>
                    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                    <?php echo sanitize($flash['message']); ?>
                </div>
                <button type="button" class="alert-close">&times;</button>
            </div>
        <?php endif; ?>

        <div class="booking-grid">
            
            <!-- Left Column: Reservation Form -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-ticket-alt"></i> Online Parking Reservation Form
                    </div>
                    <span style="font-size: 12px; color: var(--text-muted);"><i class="fas fa-shield-alt"></i> Instant Confirmation</span>
                </div>
                <div class="card-body">
                    <form action="actions/customer-booking.php" method="POST" id="bookingForm" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                        <input type="hidden" name="slot_id" id="selectedSlotId" required>

                        <!-- Step 1: Vehicle Category Selection -->
                        <div style="margin-bottom: 22px;">
                            <label style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: block;">
                                1. Select Vehicle Category <span class="required" style="color: var(--danger-red);">*</span>
                            </label>
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

                        <!-- Step 2: Date & Time Window -->
                        <div style="margin-bottom: 22px;">
                            <label style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: block;">
                                2. Date &amp; Parking Schedule <span class="required" style="color: var(--danger-red);">*</span>
                            </label>
                            <div class="booking-schedule-grid">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="booking_date">Reservation Date</label>
                                    <input type="date" name="booking_date" id="booking_date" class="form-control" 
                                           min="<?php echo $todayDate; ?>" value="<?php echo $todayDate; ?>" required onchange="triggerAvailabilityCheck()">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="entry_time">Expected Arrival</label>
                                    <input type="time" name="entry_time" id="entry_time" class="form-control" 
                                           value="<?php echo $defaultArrival; ?>" required onchange="triggerAvailabilityCheck()">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="exit_time">Expected Departure</label>
                                    <input type="time" name="exit_time" id="exit_time" class="form-control" 
                                           value="<?php echo $defaultDeparture; ?>" required onchange="triggerAvailabilityCheck()">
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Choose Parking Bay -->
                        <div style="margin-bottom: 22px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <label style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin: 0;">
                                    3. Choose Your Preferred Parking Bay <span class="required" style="color: var(--danger-red);">*</span>
                                </label>
                                <span id="availabilityBadge" class="badge badge-available">Checking availability...</span>
                            </div>
                            
                            <!-- Available Slots Container -->
                            <div id="slotsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; max-height: 200px; overflow-y: auto; padding: 4px;">
                                <!-- Dynamically populated via AJAX -->
                            </div>
                            <div id="noSlotsMessage" style="display: none; padding: 18px; text-align: center; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: var(--radius-md); color: #991B1B; font-size: 13px;">
                                <i class="fas fa-exclamation-triangle"></i> No parking bays are currently available for this category and time slot. Try adjusting your arrival or departure times.
                            </div>
                        </div>

                        <!-- Step 4: Customer Contact & Vehicle Info -->
                        <div style="margin-bottom: 22px;">
                            <label style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: block;">
                                4. Vehicle &amp; Contact Details <span class="required" style="color: var(--danger-red);">*</span>
                            </label>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="vehicle_number">Vehicle Registration No <span class="required" style="color: var(--danger-red);">*</span></label>
                                    <input type="text" name="vehicle_number" id="vehicle_number" class="form-control" 
                                           placeholder="e.g. KA-01-AB-1234" required maxlength="20" 
                                           style="text-transform: uppercase; font-family: monospace; font-size: 15px; font-weight: 700;">
                                    <div class="input-hint">Will be verified by the parking attendant upon arrival.</div>
                                </div>

                                <div class="form-group">
                                    <label for="customer_name">Your Full Name <span class="required" style="color: var(--danger-red);">*</span></label>
                                    <input type="text" name="customer_name" id="customer_name" class="form-control" 
                                           placeholder="e.g. Rahul Sharma" required maxlength="100">
                                </div>

                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="customer_phone">Contact Phone Number <span class="required" style="color: var(--danger-red);">*</span></label>
                                    <input type="tel" name="customer_phone" id="customer_phone" class="form-control" 
                                           placeholder="e.g. +91 98765 43210" required maxlength="20">
                                    <div class="input-hint">Used to verify and look up your reservation at the gate.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Calculation Preview Card -->
                        <div class="price-summary-box">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-size: 13px; color: var(--text-muted);">Estimated Duration:</span>
                                <strong id="estDurationText" style="color: var(--text-primary);">2 hr 0 min</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-size: 13px; color: var(--text-muted);">Applicable Tariff:</span>
                                <strong id="estRateText" style="color: var(--text-primary);">₹20.00 / hour</strong>
                            </div>
                            <div style="border-top: 1px dashed var(--border-color); margin: 10px 0;"></div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 14px; font-weight: 700; color: var(--text-primary);">Estimated Parking Charge:</div>
                                    <div style="font-size: 11px; color: var(--text-muted);">Pay upon exit. Actual charges reflect real stay duration.</div>
                                </div>
                                <div id="estTotalFee" style="font-size: 24px; font-weight: 800; color: var(--success-green);">
                                    ₹40.00
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="submitBookingBtn" class="btn btn-primary btn-lg" style="width: 100%;" disabled>
                            <i class="fas fa-check-circle"></i> Complete Parking Reservation
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column: Tariff & Facility Info -->
            <div>
                <!-- Tariff Table Card -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-tags"></i> Transparent Tariff Rates
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
                                        <td><strong><?php echo sanitize($r['vehicle_type']); ?></strong></td>
                                        <td style="text-align: right; font-weight: 700; color: var(--primary-blue);">
                                            ₹<?php echo number_format($r['hourly_rate'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer" style="font-size: 11.5px; color: var(--text-muted); line-height: 1.4;">
                        <i class="fas fa-info-circle"></i> Minimum charge 1 hour. Partial hours are rounded up. Pay easily at exit gate via Cash, UPI, or Card.
                    </div>
                </div>

                <!-- How It Works Card -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-route"></i> How Online Booking Works
                        </div>
                    </div>
                    <div class="card-body" style="font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
                        <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                            <div style="width: 24px; height: 24px; border-radius: 50%; background: #EFF6FF; color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">1</div>
                            <div><strong>Reserve in Advance:</strong> Pick your time window and choose your preferred vacant bay.</div>
                        </div>
                        <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                            <div style="width: 24px; height: 24px; border-radius: 50%; background: #EFF6FF; color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">2</div>
                            <div><strong>Receive Booking Confirmation:</strong> Get a unique Booking ID with printable confirmation.</div>
                        </div>
                        <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                            <div style="width: 24px; height: 24px; border-radius: 50%; background: #EFF6FF; color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">3</div>
                            <div><strong>Arrive &amp; Check In:</strong> Show your Booking ID to the attendant to enter your reserved bay.</div>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <div style="width: 24px; height: 24px; border-radius: 50%; background: #EFF6FF; color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">4</div>
                            <div><strong>Settle Upon Exit:</strong> Pay only for your actual parking duration at checkout.</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Status Lookup Callout -->
                <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-sm); text-align: center;">
                    <i class="fas fa-search" style="font-size: 28px; color: var(--primary-blue); margin-bottom: 10px;"></i>
                    <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 6px;">Already have a booking?</h4>
                    <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px;">Look up your reservation details or print your ticket slip.</p>
                    <a href="booking-lookup.php" class="btn btn-secondary btn-sm" style="width: 100%;">
                        <i class="fas fa-clipboard-check"></i> Check Booking Status
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="margin-top: 50px; background: #FFFFFF; border-top: 1px solid var(--border-color); padding: 28px 30px; text-align: center; font-size: 12.5px; color: var(--text-muted); line-height: 1.6;">
        <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">Chandigarh University &bull; Directorate of Campus Security &amp; Transport Facilities</div>
        <div>NH-05, Chandigarh-Ludhiana Highway, Mohali, Punjab 140413 &bull; Security Helpline: +91 160 3051003 &bull; transport@cumail.in</div>
        <div style="font-size: 11.5px; color: #94A3B8; margin-top: 6px;">
            Authorized Campus Parking &amp; Bay Management System &bull; Active Gate Barrier Checkpoint: Gate 1 &amp; Gate 2
        </div>
    </footer>

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
        document.getElementById('submitBookingBtn').innerHTML = `<i class="fas fa-check-circle"></i> Reserve Bay &amp; Confirm Booking`;
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

                // Update Price Summary Box
                document.getElementById('estDurationText').innerText = data.duration_text;
                document.getElementById('estRateText').innerText = `₹${parseFloat(data.hourly_rate).toFixed(2)} / hour`;
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
                                <div style="font-size: 15px; font-weight: 800; color: var(--text-primary);">${s.slot_number}</div>
                                <div style="font-size: 10px; font-weight: 600; color: var(--success-green); text-transform: uppercase;">Vacant</div>
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
