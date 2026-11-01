-- ============================================================
-- SmartPark – Vehicle Parking Management System
-- Database Schema & Initial Data Seeding (Upgraded with Online Bookings)
-- Target DBMS: MySQL 8.0+ / MariaDB 10.4+
-- Database Name: smartpark_db
-- ============================================================

CREATE DATABASE IF NOT EXISTS `smartpark_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `smartpark_db`;

-- Disable foreign key checks during creation and data load
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Table 1: admins
-- Stores administrative user credentials and profile details
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table 2: parking_rates
-- Stores configurable hourly billing rates per vehicle category
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `parking_rates`;
CREATE TABLE `parking_rates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `vehicle_type` ENUM('Two Wheeler', 'Car', 'SUV', 'Other') NOT NULL UNIQUE,
    `hourly_rate` DECIMAL(8, 2) NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table 3: parking_slots
-- Stores all physical parking slots, designated type and status
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `parking_slots`;
CREATE TABLE `parking_slots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slot_number` VARCHAR(20) NOT NULL UNIQUE,
    `vehicle_type` ENUM('Two Wheeler', 'Car', 'SUV', 'Other') NOT NULL,
    `status` ENUM('Available', 'Occupied', 'Reserved') NOT NULL DEFAULT 'Available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_slot_status` (`status`),
    INDEX `idx_slot_type` (`vehicle_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table 4: vehicles
-- Stores registered vehicle profiles and owner contact info
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `registration_number` VARCHAR(30) NOT NULL UNIQUE,
    `owner_name` VARCHAR(100) NOT NULL,
    `owner_phone` VARCHAR(20) NOT NULL,
    `vehicle_type` ENUM('Two Wheeler', 'Car', 'SUV', 'Other') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reg_number` (`registration_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table 5: bookings (UPGRADED: Customer Online Reservations)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` VARCHAR(50) NOT NULL UNIQUE,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `vehicle_number` VARCHAR(30) NOT NULL,
    `vehicle_type` ENUM('Two Wheeler', 'Car', 'SUV', 'Other') NOT NULL,
    `slot_id` INT NOT NULL,
    `booking_date` DATE NOT NULL,
    `expected_entry_time` DATETIME NOT NULL,
    `expected_exit_time` DATETIME NOT NULL,
    `actual_entry_time` DATETIME DEFAULT NULL,
    `actual_exit_time` DATETIME DEFAULT NULL,
    `estimated_amount` DECIMAL(10, 2) NOT NULL,
    `final_amount` DECIMAL(10, 2) DEFAULT NULL,
    `booking_status` ENUM('Pending', 'Confirmed', 'Checked In', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Confirmed',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_booking_id` (`booking_id`),
    INDEX `idx_booking_phone` (`customer_phone`),
    INDEX `idx_booking_date` (`booking_date`),
    INDEX `idx_booking_status` (`booking_status`),
    INDEX `idx_booking_slot` (`slot_id`),
    CONSTRAINT `fk_bookings_slot`
        FOREIGN KEY (`slot_id`) REFERENCES `parking_slots` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table 6: parking_records
-- Tracks vehicle check-ins, ticket IDs, duration, fees & status
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `parking_records`;
CREATE TABLE `parking_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` VARCHAR(50) NOT NULL UNIQUE,
    `booking_id` VARCHAR(50) DEFAULT NULL,
    `vehicle_id` INT NOT NULL,
    `slot_id` INT NOT NULL,
    `entry_time` DATETIME NOT NULL,
    `exit_time` DATETIME DEFAULT NULL,
    `duration_minutes` INT DEFAULT NULL,
    `total_fee` DECIMAL(10, 2) DEFAULT NULL,
    `status` ENUM('Parked', 'Completed') NOT NULL DEFAULT 'Parked',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_record_status` (`status`),
    INDEX `idx_record_entry_time` (`entry_time`),
    INDEX `idx_record_booking` (`booking_id`),
    CONSTRAINT `fk_records_vehicle` 
        FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) 
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_records_slot` 
        FOREIGN KEY (`slot_id`) REFERENCES `parking_slots` (`id`) 
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table 7: payments
-- Records settlement details for completed parking tickets
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parking_record_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('Cash', 'UPI', 'Card') NOT NULL,
    `payment_status` ENUM('Paid', 'Pending', 'Failed') NOT NULL DEFAULT 'Paid',
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payment_date` (`payment_date`),
    CONSTRAINT `fk_payments_record` 
        FOREIGN KEY (`parking_record_id`) REFERENCES `parking_records` (`id`) 
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table 8: system_settings
-- System-wide key-value configuration
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SAMPLE DATA INSERTION (FOR VIVA & DEMONSTRATION)
-- ============================================================

-- 1. Default Admin Account (Password: admin123)
INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `email`) VALUES
(1, 'admin', '$2y$10$t4yUBd1xaCal9MBlG.kanea26IaZQAshY8fTVb1N80SeidzQnA5xG', 'System Administrator', 'admin@smartpark.com');

-- 2. Default Parking Rates
INSERT INTO `parking_rates` (`id`, `vehicle_type`, `hourly_rate`) VALUES
(1, 'Two Wheeler', 10.00),
(2, 'Car', 20.00),
(3, 'SUV', 30.00),
(4, 'Other', 25.00);

-- 3. System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('parking_lot_name', 'Chandigarh University Campus Parking'),
('contact_phone', '+91 160 3051003'),
('contact_email', 'parking@cumail.in'),
('address', 'Chandigarh University, NH-05, Chandigarh-Ludhiana Highway, Mohali, Punjab 140413'),
('currency_symbol', '₹'),
('total_capacity', '20');

-- 4. 20 Parking Slots (Across 4 categories)
-- Initial statuses reflect 3 Occupied, 2 Reserved, 15 Available
INSERT INTO `parking_slots` (`id`, `slot_number`, `vehicle_type`, `status`) VALUES
-- Two Wheelers (5 slots: TW-01 Occupied, TW-02 to TW-05 Available)
(1, 'TW-01', 'Two Wheeler', 'Occupied'),
(2, 'TW-02', 'Two Wheeler', 'Available'),
(3, 'TW-03', 'Two Wheeler', 'Available'),
(4, 'TW-04', 'Two Wheeler', 'Available'),
(5, 'TW-05', 'Two Wheeler', 'Available'),

-- Cars (8 slots: CR-01 Occupied, CR-08 Reserved, CR-02..07 Available)
(6, 'CR-01', 'Car', 'Occupied'),
(7, 'CR-02', 'Car', 'Available'),
(8, 'CR-03', 'Car', 'Available'),
(9, 'CR-04', 'Car', 'Available'),
(10, 'CR-05', 'Car', 'Available'),
(11, 'CR-06', 'Car', 'Available'),
(12, 'CR-07', 'Car', 'Available'),
(13, 'CR-08', 'Car', 'Reserved'),

-- SUVs (4 slots: SV-01 Occupied, SV-04 Reserved, SV-02..03 Available)
(14, 'SV-01', 'SUV', 'Occupied'),
(15, 'SV-02', 'SUV', 'Available'),
(16, 'SV-03', 'SUV', 'Available'),
(17, 'SV-04', 'SUV', 'Reserved'),

-- Others / Heavy / Commercial (3 slots: OT-01 to OT-03 Available)
(18, 'OT-01', 'Other', 'Available'),
(19, 'OT-02', 'Other', 'Available'),
(20, 'OT-03', 'Other', 'Available');

-- 5. 10 Sample Vehicles (Chandigarh University Campus)
INSERT INTO `vehicles` (`id`, `registration_number`, `owner_name`, `owner_phone`, `vehicle_type`) VALUES
(1, 'PB-65-AB-1024', 'Dr. Harpreet Singh (Dean Academics)', '+91 9814012345', 'Car'),
(2, 'CH-01-BT-3482', 'Amanpreet Kaur (CSE Faculty)', '+91 9872054321', 'Two Wheeler'),
(3, 'HR-03-D-9988', 'Prof. Rajesh Sharma (Administration)', '+91 9417088990', 'SUV'),
(4, 'PB-12-C-4521', 'Karanveer Singh (Guest Lecturer)', '+91 9888123456', 'Car'),
(5, 'PB-65-TW-8877', 'Simranjit Kaur (Research Scholar)', '+91 9915099887', 'Two Wheeler'),
(6, 'DL-01-CU-2026', 'Vikramaditya Roy (Conference Delegate)', '+91 9811023456', 'Car'),
(7, 'HP-12-F-6743', 'Sanjay Thakur (Admissions Visitor)', '+91 9418045678', 'SUV'),
(8, 'PB-65-BUS-01', 'CU Campus Fleet Services', '+91 9876510001', 'Other'),
(9, 'PB-10-AZ-1122', 'Gurkirat Singh (Hostel Warden)', '+91 9815011223', 'Two Wheeler'),
(10, 'CH-04-A-7700', 'Dr. Shalini Gupta (Biotechnology)', '+91 9876077001', 'Car');

-- 6. Sample Completed Parking Records (5 records)
INSERT INTO `parking_records` (`id`, `ticket_id`, `booking_id`, `vehicle_id`, `slot_id`, `entry_time`, `exit_time`, `duration_minutes`, `total_fee`, `status`) VALUES
(1, 'SP-20261028-0001', NULL, 4, 7,  DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 4 DAY), INTERVAL -150 MINUTE), 150, 60.00, 'Completed'),
(2, 'SP-20261029-0002', NULL, 5, 2,  DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 3 DAY), INTERVAL -75 MINUTE),  75,  20.00, 'Completed'),
(3, 'SP-20261030-0003', NULL, 6, 8,  DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 2 DAY), INTERVAL -210 MINUTE), 210, 80.00, 'Completed'),
(4, 'SP-20261031-0004', NULL, 7, 15, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 1 DAY), INTERVAL -180 MINUTE), 180, 90.00, 'Completed'),
(5, 'SP-20261101-0005', NULL, 8, 18, DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(DATE_SUB(NOW(), INTERVAL 5 HOUR), INTERVAL -110 MINUTE), 110, 50.00, 'Completed');

-- 7. Corresponding Payments for Completed Sessions (5 payments)
INSERT INTO `payments` (`id`, `parking_record_id`, `amount`, `payment_method`, `payment_status`, `payment_date`) VALUES
(1, 1, 60.00, 'UPI',  'Paid', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 2, 20.00, 'Cash', 'Paid', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 3, 80.00, 'Card', 'Paid', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 4, 90.00, 'UPI',  'Paid', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 5, 50.00, 'Cash', 'Paid', DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- 8. 3 Active Parking Records (Vehicles currently parked)
INSERT INTO `parking_records` (`id`, `ticket_id`, `booking_id`, `vehicle_id`, `slot_id`, `entry_time`, `exit_time`, `duration_minutes`, `total_fee`, `status`) VALUES
(6, 'SP-20261101-0101', NULL, 2, 1,  DATE_SUB(NOW(), INTERVAL 95 MINUTE), NULL, NULL, NULL, 'Parked'),
(7, 'SP-20261101-0102', NULL, 1, 6,  DATE_SUB(NOW(), INTERVAL 140 MINUTE), NULL, NULL, NULL, 'Parked'),
(8, 'SP-20261101-0103', NULL, 3, 14, DATE_SUB(NOW(), INTERVAL 45 MINUTE),  NULL, NULL, NULL, 'Parked');

-- 9. Sample Online Customer Bookings (UPGRADED: Reservation Records)
INSERT INTO `bookings` (`id`, `booking_id`, `customer_name`, `customer_phone`, `vehicle_number`, `vehicle_type`, `slot_id`, `booking_date`, `expected_entry_time`, `expected_exit_time`, `actual_entry_time`, `actual_exit_time`, `estimated_amount`, `final_amount`, `booking_status`) VALUES
(1, 'BK-20261101-1001', 'Arjun Kapoor',  '+91 9876500001', 'KA-04-MB-4567', 'Car', 13, CURRENT_DATE(), DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 5 HOUR), NULL, NULL, 60.00, NULL, 'Confirmed'),
(2, 'BK-20261101-1002', 'Rohan Mehra',   '+91 9876500002', 'MH-02-CD-8899', 'SUV', 17, CURRENT_DATE(), DATE_ADD(NOW(), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 7 HOUR), NULL, NULL, 90.00, NULL, 'Confirmed'),
(3, 'BK-20261031-1003', 'Sunil Joshi',   '+91 9876500003', 'DL-01-XY-3344', 'Car', 7,  DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 26 HOUR), DATE_SUB(NOW(), INTERVAL 22 HOUR), DATE_SUB(NOW(), INTERVAL 26 HOUR), DATE_SUB(NOW(), INTERVAL 22 HOUR), 80.00, 80.00, 'Completed'),
(4, 'BK-20261030-1004', 'Divya Nair',    '+91 9876500004', 'KL-01-AA-9900', 'Two Wheeler', 3, DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 50 HOUR), DATE_SUB(NOW(), INTERVAL 48 HOUR), NULL, NULL, 20.00, NULL, 'Cancelled');
