<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Admin Authentication Page
 */

require_once __DIR__ . '/includes/auth.php';

if (is_authenticated()) {
    header("Location: pages/dashboard.php");
    exit;
}

$error = $_GET['error'] ?? '';
$success = '';

if (isset($_GET['logged_out'])) {
    $success = 'You have been successfully signed out.';
}

$rememberedUser = $_COOKIE['smartpark_user'] ?? '';

// Handle POST login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($token)) {
        $error = 'Security validation failed (Invalid CSRF token). Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $loginResult = attempt_login($username, $password, $remember);
            if ($loginResult['success']) {
                header("Location: pages/dashboard.php");
                exit;
            } else {
                $error = $loginResult['message'];
            }
        }
    }
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
    <meta name="theme-color" content="#0F172A">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    <link rel="icon" type="image/png" href="assets/images/logo.png">

    <title>Sign In - CU SmartPark | Chandigarh University</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">

<div class="login-card">
    <div class="login-header">
        <img src="assets/images/logo.png" alt="Chandigarh University Logo" style="width: 80px; height: 80px; object-fit: contain; margin-bottom: 12px; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.15));">
        <h2 class="login-title" style="margin-bottom: 2px;">CU SmartPark</h2>
        <div style="font-size: 11px; font-weight: 800; color: #DC2626; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px;">Directorate of Campus Security &amp; Transport</div>
        <p class="login-subtitle">Campus Parking Attendant &amp; Operations Sign In</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <div>
                <i class="fas fa-exclamation-circle" style="margin-right: 6px;"></i>
                <?php echo sanitize($error); ?>
            </div>
            <button type="button" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
            <div>
                <i class="fas fa-check-circle" style="margin-right: 6px;"></i>
                <?php echo sanitize($success); ?>
            </div>
            <button type="button" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">

        <div class="form-group">
            <label for="username">Username <span class="required">*</span></label>
            <div style="position: relative;">
                <input type="text" name="username" id="username" class="form-control" 
                       placeholder="e.g. admin" 
                       value="<?php echo sanitize($rememberedUser ?: ($_POST['username'] ?? 'admin')); ?>" 
                       required autofocus style="padding-left: 38px;">
                <i class="fas fa-user" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password <span class="required">*</span></label>
            <div class="password-input-wrapper">
                <input type="password" name="password" id="password" class="form-control" 
                       placeholder="Enter your password" 
                       value="<?php echo (empty($error) ? 'admin123' : ''); ?>"
                       required style="padding-left: 38px; padding-right: 40px;">
                <i class="fas fa-lock" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
                <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px;">
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-secondary); cursor: pointer; user-select: none;">
                <input type="checkbox" name="remember" value="1" <?php echo !empty($rememberedUser) ? 'checked' : ''; ?>>
                Remember username
            </label>
            <span style="font-size: 12px; color: var(--primary-blue); font-weight: 500;">Role: Administrator</span>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
            <i class="fas fa-sign-in-alt"></i> Sign In to Staff Dashboard
        </button>
    </form>

    <div class="login-demo-box" style="background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: var(--radius-md); padding: 12px 14px; margin-top: 18px;">
        <div style="font-weight: 700; margin-bottom: 4px; color: var(--text-primary); display: flex; align-items: center; gap: 6px; font-size: 12px;">
            <i class="fas fa-key" style="color: var(--primary-blue);"></i> Default Attendant Credentials
        </div>
        <div style="font-size: 12px;">Username: <strong style="color: var(--primary-blue);">admin</strong> &bull; Password: <strong style="color: var(--primary-blue);">admin123</strong></div>
    </div>

    <div style="margin-top: 20px; text-align: center; font-size: 13px;">
        <span style="color: var(--text-muted);">Looking to book a parking spot in advance?</span><br>
        <a href="booking.php" style="font-weight: 700; color: var(--primary-blue); display: inline-flex; align-items: center; gap: 6px; margin-top: 4px;">
            <i class="fas fa-calendar-check"></i> Open Public Campus Booking Portal &rarr;
        </a>
    </div>

    <div style="margin-top: 20px; padding-top: 14px; border-top: 1px solid #F1F5F9; text-align: center; font-size: 11px; color: #94A3B8;">
        Chandigarh University &bull; NH-05 Chandigarh-Ludhiana Highway, Mohali, Punjab 140413
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
