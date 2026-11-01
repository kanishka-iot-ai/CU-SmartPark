<?php
/**
 * SmartPark – Live Visual Preview Router
 */

require_once __DIR__ . '/includes/auth.php';

// Set up authenticated admin session
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id']        = 1;
$_SESSION['admin_username']  = 'admin';
$_SESSION['admin_name']      = 'System Administrator';
$_SESSION['admin_email']     = 'admin@smartpark.com';
$_SESSION['last_activity']   = time();

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = [
    'dashboard'       => 'pages/dashboard.php',
    'parking-slots'   => 'pages/parking-slots.php',
    'bookings'        => 'pages/bookings.php',
    'vehicle-entry'   => 'pages/vehicle-entry.php',
    'vehicle-exit'    => 'pages/vehicle-exit.php',
    'vehicle-records' => 'pages/vehicle-records.php',
    'payments'        => 'pages/payments.php',
    'reports'         => 'pages/reports.php',
    'settings'        => 'pages/settings.php'
];

if (isset($allowedPages[$page])) {
    require_once __DIR__ . '/' . $allowedPages[$page];
} else {
    require_once __DIR__ . '/pages/dashboard.php';
}
