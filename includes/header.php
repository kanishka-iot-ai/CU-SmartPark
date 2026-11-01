<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Global Page Header
 */

require_once __DIR__ . '/functions.php';
require_auth();

$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
$adminName = $_SESSION['admin_name'] ?? 'Administrator';
$adminUsername = $_SESSION['admin_username'] ?? 'admin';
$parkingLotName = get_setting('parking_lot_name', 'SmartPark Central');
$flash = get_flash();
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
    <link rel="apple-touch-icon" href="../assets/images/logo.png">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <title><?php echo sanitize($pageTitle); ?> - CU SmartPark | Chandigarh University</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js for Reports & Dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-container">

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Include Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        
        <!-- Top Navigation -->
        <header class="top-nav">
            <div class="nav-left">
                <button type="button" class="menu-toggle-btn" id="menuToggle" aria-label="Toggle Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title-box">
                    <h2><?php echo sanitize($pageTitle); ?></h2>
                    <div class="nav-breadcrumbs">
                        <span>Chandigarh University</span> &rsaquo; <span>CU SmartPark</span> &rsaquo; <span><?php echo sanitize($pageTitle); ?></span>
                    </div>
                </div>
            </div>

            <div class="nav-right">
                <div class="nav-date">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date('D, d M Y'); ?></span>
                </div>

                <a href="vehicle-entry.php" class="btn btn-primary btn-sm no-print">
                    <i class="fas fa-plus-circle"></i> Quick Entry
                </a>

                <!-- Profile Dropdown -->
                <div class="profile-dropdown-wrapper">
                    <button type="button" class="profile-toggle" id="profileToggle">
                        <div class="profile-avatar-mini">
                            <?php echo strtoupper(substr($adminName, 0, 1)); ?>
                        </div>
                        <div style="text-align: left; margin: 0 4px;">
                            <div class="profile-name"><?php echo sanitize($adminName); ?></div>
                            <div class="profile-role">Admin</div>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 11px; color: #9CA3AF;"></i>
                    </button>

                    <div class="profile-menu" id="profileMenu">
                        <a href="settings.php"><i class="fas fa-user-cog"></i> Account Settings</a>
                        <a href="reports.php"><i class="fas fa-chart-line"></i> Analytics</a>
                        <div style="border-top: 1px solid var(--border-color); margin: 4px 0;"></div>
                        <a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <main class="content-body">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo sanitize($flash['type']); ?>">
                    <div>
                        <?php if ($flash['type'] === 'success'): ?>
                            <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                        <?php elseif ($flash['type'] === 'danger'): ?>
                            <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                        <?php else: ?>
                            <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                        <?php endif; ?>
                        <?php echo sanitize($flash['message']); ?>
                    </div>
                    <button type="button" class="alert-close">&times;</button>
                </div>
            <?php endif; ?>
