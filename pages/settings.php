<?php
/**
 * SmartPark – Vehicle Parking Management System
 * System Configuration & Administrator Settings
 */

$pageTitle = 'Settings & Configuration';
$activePage = 'settings';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();
$adminId = $_SESSION['admin_id'] ?? 1;

// Fetch admin profile
$stmtAdmin = $pdo->prepare("SELECT username, full_name, email, created_at FROM admins WHERE id = ?");
$stmtAdmin->execute([$adminId]);
$admin = $stmtAdmin->fetch();

// Fetch current parking rates
$rates = $pdo->query("SELECT vehicle_type, hourly_rate FROM parking_rates ORDER BY id ASC")->fetchAll();

// Fetch system settings
$lotName  = get_setting('parking_lot_name', 'SmartPark Central Hub');
$phone    = get_setting('contact_phone', '+91 98765 43210');
$email    = get_setting('contact_email', 'support@smartpark.local');
$address  = get_setting('address', 'Tech Park Boulevard, Electronic City, Bangalore');
$currency = get_setting('currency_symbol', '₹');

// Database driver & version info
$dbDriver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$dbVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
$totalSlots = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots")->fetchColumn();

$csrf_token = get_csrf_token();
?>

<!-- Header -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">System Settings &amp; Administration</h3>
        <p style="font-size: 13px; color: var(--text-muted);">Adjust hourly billing tariffs, facility branding, admin security, and database parameters.</p>
    </div>
</div>

<div class="settings-grid">
    
    <!-- Column 1: Tariffs & Facility Profile -->
    <div>
        <!-- Card 1: Parking Rates Configuration -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-money-bill-wave"></i> Hourly Parking Tariffs
                </div>
                <span class="badge" style="background: #EFF6FF; color: var(--primary-blue);">MySQL Driven</span>
            </div>
            <div class="card-body">
                <form action="../actions/update-settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <input type="hidden" name="action_type" value="update_rates">

                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                        Configure hourly rates per vehicle category. These values determine the automatic exit calculation.
                    </p>

                    <?php foreach ($rates as $r): ?>
                        <div class="form-group">
                            <label for="rate_<?php echo md5($r['vehicle_type']); ?>">
                                <?php echo sanitize($r['vehicle_type']); ?> Rate (₹ / Hour) <span class="required">*</span>
                            </label>
                            <div style="position: relative;">
                                <input type="number" step="0.50" min="0" 
                                       name="rates[<?php echo sanitize($r['vehicle_type']); ?>]" 
                                       id="rate_<?php echo md5($r['vehicle_type']); ?>" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($r['hourly_rate']); ?>" required 
                                       style="padding-left: 32px; font-weight: 700;">
                                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted);">₹</span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Tariff Rates
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Card 2: Facility Information -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-building"></i> Facility Profile &amp; Capacity
                </div>
            </div>
            <div class="card-body">
                <form action="../actions/update-settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <input type="hidden" name="action_type" value="update_facility">

                    <div class="form-group">
                        <label for="parking_lot_name">Parking Lot / Facility Name <span class="required">*</span></label>
                        <input type="text" name="parking_lot_name" id="parking_lot_name" class="form-control" 
                               value="<?php echo sanitize($lotName); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">Support Phone Number</label>
                        <input type="text" name="contact_phone" id="contact_phone" class="form-control" 
                               value="<?php echo sanitize($phone); ?>">
                    </div>

                    <div class="form-group">
                        <label for="contact_email">Support Email Address</label>
                        <input type="email" name="contact_email" id="contact_email" class="form-control" 
                               value="<?php echo sanitize($email); ?>">
                    </div>

                    <div class="form-group">
                        <label for="address">Premises Physical Address</label>
                        <textarea name="address" id="address" class="form-control" rows="2"><?php echo sanitize($address); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="currency_symbol">Currency Symbol</label>
                        <input type="text" name="currency_symbol" id="currency_symbol" class="form-control" 
                               value="<?php echo sanitize($currency); ?>" style="width: 80px;">
                    </div>

                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Update Facility Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Column 2: Admin Profile & Security -->
    <div>
        <!-- Card 3: Administrator Profile -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-user-circle"></i> Admin Profile Information
                </div>
            </div>
            <div class="card-body">
                <form action="../actions/update-settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <input type="hidden" name="action_type" value="update_profile">

                    <div class="form-group">
                        <label for="admin_username">Admin Username</label>
                        <input type="text" id="admin_username" class="form-control" 
                               value="<?php echo sanitize($admin['username'] ?? 'admin'); ?>" readonly>
                        <div class="input-hint">Usernames are immutable for security.</div>
                    </div>

                    <div class="form-group">
                        <label for="full_name">Administrator Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" id="full_name" class="form-control" 
                               value="<?php echo sanitize($admin['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Admin Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" 
                               value="<?php echo sanitize($admin['email'] ?? ''); ?>">
                    </div>

                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Card 4: Change Password -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-lock"></i> Update Admin Password
                </div>
            </div>
            <div class="card-body">
                <form action="../actions/update-settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <input type="hidden" name="action_type" value="update_password">

                    <div class="form-group">
                        <label for="current_password">Current Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="current_password" id="current_password" class="form-control" required>
                            <button type="button" class="password-toggle-btn"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password (Min 6 Characters) <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6">
                            <button type="button" class="password-toggle-btn"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6">
                            <button type="button" class="password-toggle-btn"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Card 5: System & DBMS Diagnostics -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-server"></i> DBMS &amp; Environment Diagnostics
                </div>
            </div>
            <div class="card-body" style="font-size: 13px; line-height: 1.6;">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; margin-bottom: 6px;">
                    <span style="color: var(--text-muted);">Database Driver:</span>
                    <strong style="text-transform: uppercase; color: var(--primary-blue);"><?php echo $dbDriver; ?> (PDO)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; margin-bottom: 6px;">
                    <span style="color: var(--text-muted);">Database Target:</span>
                    <strong><?php echo DB_NAME; ?> @ <?php echo DB_HOST; ?>:<?php echo DB_PORT; ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; margin-bottom: 6px;">
                    <span style="color: var(--text-muted);">PHP Version:</span>
                    <strong><?php echo PHP_VERSION; ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; margin-bottom: 6px;">
                    <span style="color: var(--text-muted);">Configured Bays:</span>
                    <strong><?php echo $totalSlots; ?> Active Slots</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Server Software:</span>
                    <span style="font-size: 11.5px;"><?php echo sanitize($_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI Server / Apache'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
