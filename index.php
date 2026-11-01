<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Entry Point / Router
 */

require_once __DIR__ . '/includes/functions.php';

if (is_authenticated()) {
    header("Location: pages/dashboard.php");
    exit;
} else {
    header("Location: login.php");
    exit;
}
