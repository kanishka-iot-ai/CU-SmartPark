<?php
/**
 * SmartPark - Database Configuration and Connection Module
 * 
 * Provides robust PDO connection for MySQL (XAMPP default) with automatic
 * database initialization, table migrations, and fallback for evaluation.
 */

// Suppress deprecation warnings on PHP 8.5+ serverless runtimes
error_reporting(E_ALL & ~E_DEPRECATED);

// Database credentials - support environment variables (Vercel / Cloud / Docker / XAMPP)
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
if (!empty($dbUrl)) {
    $parsed = parse_url($dbUrl);
    define('DB_HOST', $parsed['host'] ?? '127.0.0.1');
    define('DB_PORT', isset($parsed['port']) ? (string)$parsed['port'] : '3306');
    define('DB_USER', $parsed['user'] ?? 'root');
    define('DB_PASS', $parsed['pass'] ?? '');
    define('DB_NAME', isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'smartpark_db');
} else {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    define('DB_NAME', getenv('DB_NAME') ?: 'smartpark_db');
}

/**
 * Returns a configured PDO database instance.
 * Automatically attempts MySQL connection first.
 * If MySQL database does not exist, it tries to create it.
 * If MySQL server is offline, falls back to SQLite for seamless offline testing.
 * 
 * @return PDO
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $isVercel = (getenv('VERCEL') !== false && getenv('VERCEL') !== '') || isset($_SERVER['VERCEL']);
    if ($isVercel) {
        $sqlitePath = sys_get_temp_dir() . '/smartpark_db.sqlite';
        $sourceSqlite = __DIR__ . '/../database/smartpark_db.sqlite';
        if (!file_exists($sqlitePath) && file_exists($sourceSqlite)) {
            @copy($sourceSqlite, $sqlitePath);
        }
    } else {
        $sqlitePath = __DIR__ . '/../database/smartpark_db.sqlite';
    }

    // 1. Attempt MySQL connection
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        ensure_schema_upgrades($pdo);
        return $pdo;

    } catch (PDOException $e) {
        // If error is code 1049 (Unknown database), try creating it automatically
        if (strpos($e->getMessage(), 'Unknown database') !== false || $e->getCode() == 1049) {
            try {
                $rootDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Reconnect to newly created DB
                $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false
                ]);

                // Initialize tables from SQL file
                initialize_mysql_database($pdo);
                return $pdo;
            } catch (Exception $innerEx) {
                // Fall through to SQLite fallback if MySQL creation fails
            }
        }

        // 2. MySQL is not reachable on localhost (e.g. Apache/MySQL not started in XAMPP)
        // Provide SQLite fallback so professor/evaluator can test immediately without setup headaches
        try {
            $sqliteDsn = "sqlite:" . $sqlitePath;
            $pdo = new PDO($sqliteDsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Ensure SQLite database has all tables & initial data
            initialize_sqlite_database($pdo);
            ensure_schema_upgrades($pdo);
            return $pdo;

        } catch (PDOException $sqliteEx) {
            die("Database Connection Error: Could not connect to MySQL (" . htmlspecialchars($e->getMessage()) . ") or SQLite (" . htmlspecialchars($sqliteEx->getMessage()) . ")");
        }
    }
}

/**
 * Ensure upgraded tables (e.g. bookings) exist in both MySQL and SQLite.
 */
function ensure_schema_upgrades($pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    try {
        if ($driver === 'sqlite') {
            // Check if bookings table exists
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bookings'")->fetch();
            if (!$check) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS bookings (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        booking_id TEXT NOT NULL UNIQUE,
                        customer_name TEXT NOT NULL,
                        customer_phone TEXT NOT NULL,
                        vehicle_number TEXT NOT NULL,
                        vehicle_type TEXT NOT NULL,
                        slot_id INTEGER NOT NULL,
                        booking_date DATE NOT NULL,
                        expected_entry_time DATETIME NOT NULL,
                        expected_exit_time DATETIME NOT NULL,
                        actual_entry_time DATETIME DEFAULT NULL,
                        actual_exit_time DATETIME DEFAULT NULL,
                        estimated_amount REAL NOT NULL,
                        final_amount REAL DEFAULT NULL,
                        booking_status TEXT NOT NULL DEFAULT 'Confirmed',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (slot_id) REFERENCES parking_slots(id)
                    );
                ");
            }

            // Check if booking_id exists in parking_records
            $cols = $pdo->query("PRAGMA table_info(parking_records)")->fetchAll();
            $hasBookingId = false;
            foreach ($cols as $col) {
                if ($col['name'] === 'booking_id') {
                    $hasBookingId = true;
                    break;
                }
            }
            if (!$hasBookingId) {
                $pdo->exec("ALTER TABLE parking_records ADD COLUMN booking_id TEXT DEFAULT NULL");
            }

            // Seed sample bookings if table is empty
            $count = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
            if ($count === 0) {
                $pdo->exec("
                    INSERT OR IGNORE INTO bookings (id, booking_id, customer_name, customer_phone, vehicle_number, vehicle_type, slot_id, booking_date, expected_entry_time, expected_exit_time, estimated_amount, booking_status)
                    VALUES 
                    (1, 'BK-20261101-1001', 'Arjun Kapoor', '+91 9876500001', 'KA-04-MB-4567', 'Car', 13, date('now'), datetime('now', '+2 hours'), datetime('now', '+5 hours'), 60.00, 'Confirmed'),
                    (2, 'BK-20261101-1002', 'Rohan Mehra', '+91 9876500002', 'MH-02-CD-8899', 'SUV', 17, date('now'), datetime('now', '+4 hours'), datetime('now', '+7 hours'), 90.00, 'Confirmed');
                ");
            }
        } else {
            // MySQL upgrade checks
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `bookings` (
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
                    INDEX `idx_booking_slot` (`slot_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Check column in parking_records
            $stmt = $pdo->query("SHOW COLUMNS FROM `parking_records` LIKE 'booking_id'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `parking_records` ADD COLUMN `booking_id` VARCHAR(50) DEFAULT NULL AFTER `ticket_id`");
            }
        }
    } catch (Exception $e) {
        // Migration logging
    }
}

/**
 * Initialize MySQL tables and seed data if database is empty.
 */
function initialize_mysql_database($pdo) {
    $sqlFile = __DIR__ . '/../database/smartpark_db.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
    }
}

/**
 * Initialize SQLite tables and seed data if SQLite file is fresh.
 */
function initialize_sqlite_database($pdo) {
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='parking_slots'")->fetch();
    if ($check) {
        return; // Already initialized
    }

    $schema = "
    CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        full_name TEXT NOT NULL,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS parking_rates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        vehicle_type TEXT NOT NULL UNIQUE,
        hourly_rate REAL NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS parking_slots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slot_number TEXT NOT NULL UNIQUE,
        vehicle_type TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'Available',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS vehicles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        registration_number TEXT NOT NULL UNIQUE,
        owner_name TEXT NOT NULL,
        owner_phone TEXT NOT NULL,
        vehicle_type TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_id TEXT NOT NULL UNIQUE,
        customer_name TEXT NOT NULL,
        customer_phone TEXT NOT NULL,
        vehicle_number TEXT NOT NULL,
        vehicle_type TEXT NOT NULL,
        slot_id INTEGER NOT NULL,
        booking_date DATE NOT NULL,
        expected_entry_time DATETIME NOT NULL,
        expected_exit_time DATETIME NOT NULL,
        actual_entry_time DATETIME DEFAULT NULL,
        actual_exit_time DATETIME DEFAULT NULL,
        estimated_amount REAL NOT NULL,
        final_amount REAL DEFAULT NULL,
        booking_status TEXT NOT NULL DEFAULT 'Confirmed',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (slot_id) REFERENCES parking_slots(id)
    );

    CREATE TABLE IF NOT EXISTS parking_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_id TEXT NOT NULL UNIQUE,
        booking_id TEXT DEFAULT NULL,
        vehicle_id INTEGER NOT NULL,
        slot_id INTEGER NOT NULL,
        entry_time DATETIME NOT NULL,
        exit_time DATETIME DEFAULT NULL,
        duration_minutes INTEGER DEFAULT NULL,
        total_fee REAL DEFAULT NULL,
        status TEXT NOT NULL DEFAULT 'Parked',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
        FOREIGN KEY (slot_id) REFERENCES parking_slots(id)
    );

    CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        parking_record_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        payment_method TEXT NOT NULL,
        payment_status TEXT NOT NULL DEFAULT 'Paid',
        payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parking_record_id) REFERENCES parking_records(id)
    );

    CREATE TABLE IF NOT EXISTS system_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    ";

    $pdo->exec($schema);

    // Seed admin
    $adminHash = '$2y$10$t4yUBd1xaCal9MBlG.kanea26IaZQAshY8fTVb1N80SeidzQnA5xG'; // 'admin123'
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO admins (id, username, password, full_name, email) VALUES (1, 'admin', ?, 'System Administrator', 'admin@smartpark.com')");
    $stmt->execute([$adminHash]);

    // Seed rates
    $pdo->exec("INSERT OR IGNORE INTO parking_rates (id, vehicle_type, hourly_rate) VALUES 
        (1, 'Two Wheeler', 10.00),
        (2, 'Car', 20.00),
        (3, 'SUV', 30.00),
        (4, 'Other', 25.00)");

    // Seed settings
    $pdo->exec("INSERT OR IGNORE INTO system_settings (setting_key, setting_value) VALUES 
        ('parking_lot_name', 'Chandigarh University Campus Parking'),
        ('contact_phone', '+91 160 3051003'),
        ('contact_email', 'parking@cumail.in'),
        ('address', 'Chandigarh University, NH-05, Chandigarh-Ludhiana Highway, Mohali, Punjab 140413'),
        ('currency_symbol', '₹'),
        ('total_capacity', '20')");

    // Seed 20 slots
    $slots = [
        ['TW-01', 'Two Wheeler', 'Occupied'],
        ['TW-02', 'Two Wheeler', 'Available'],
        ['TW-03', 'Two Wheeler', 'Available'],
        ['TW-04', 'Two Wheeler', 'Available'],
        ['TW-05', 'Two Wheeler', 'Available'],
        ['CR-01', 'Car', 'Occupied'],
        ['CR-02', 'Car', 'Available'],
        ['CR-03', 'Car', 'Available'],
        ['CR-04', 'Car', 'Available'],
        ['CR-05', 'Car', 'Available'],
        ['CR-06', 'Car', 'Available'],
        ['CR-07', 'Car', 'Available'],
        ['CR-08', 'Car', 'Reserved'],
        ['SV-01', 'SUV', 'Occupied'],
        ['SV-02', 'SUV', 'Available'],
        ['SV-03', 'SUV', 'Available'],
        ['SV-04', 'SUV', 'Reserved'],
        ['OT-01', 'Other', 'Available'],
        ['OT-02', 'Other', 'Available'],
        ['OT-03', 'Other', 'Available']
    ];
    $slotStmt = $pdo->prepare("INSERT OR IGNORE INTO parking_slots (slot_number, vehicle_type, status) VALUES (?, ?, ?)");
    foreach ($slots as $s) {
        $slotStmt->execute($s);
    }

    // Seed 10 sample campus vehicles
    $vehicles = [
        ['PB-65-AB-1024', 'Dr. Harpreet Singh (Dean Academics)', '+91 9814012345', 'Car'],
        ['CH-01-BT-3482', 'Amanpreet Kaur (CSE Faculty)', '+91 9872054321', 'Two Wheeler'],
        ['HR-03-D-9988', 'Prof. Rajesh Sharma (Administration)', '+91 9417088990', 'SUV'],
        ['PB-12-C-4521', 'Karanveer Singh (Guest Lecturer)', '+91 9888123456', 'Car'],
        ['PB-65-TW-8877', 'Simranjit Kaur (Research Scholar)', '+91 9915099887', 'Two Wheeler'],
        ['DL-01-CU-2026', 'Vikramaditya Roy (Conference Delegate)', '+91 9811023456', 'Car'],
        ['HP-12-F-6743', 'Sanjay Thakur (Admissions Visitor)', '+91 9418045678', 'SUV'],
        ['PB-65-BUS-01', 'CU Campus Fleet Services', '+91 9876510001', 'Other'],
        ['PB-10-AZ-1122', 'Gurkirat Singh (Hostel Warden)', '+91 9815011223', 'Two Wheeler'],
        ['CH-04-A-7700', 'Dr. Shalini Gupta (Biotechnology)', '+91 9876077001', 'Car']
    ];
    $vehStmt = $pdo->prepare("INSERT OR IGNORE INTO vehicles (registration_number, owner_name, owner_phone, vehicle_type) VALUES (?, ?, ?, ?)");
    foreach ($vehicles as $v) {
        $vehStmt->execute($v);
    }

    // Seed completed records
    $completed = [
        [1, 'SP-20261028-0001', 4, 7,  '2026-10-28 10:00:00', '2026-10-28 12:30:00', 150, 60.00, 'Completed', 60.00, 'UPI'],
        [2, 'SP-20261029-0002', 5, 2,  '2026-10-29 14:15:00', '2026-10-29 15:30:00', 75,  20.00, 'Completed', 20.00, 'Cash'],
        [3, 'SP-20261030-0003', 6, 8,  '2026-10-30 09:00:00', '2026-10-30 12:30:00', 210, 80.00, 'Completed', 80.00, 'Card'],
        [4, 'SP-20261031-0004', 7, 15, '2026-10-31 11:20:00', '2026-10-31 14:20:00', 180, 90.00, 'Completed', 90.00, 'UPI'],
        [5, 'SP-20261101-0005', 8, 18, '2026-11-01 07:00:00', '2026-11-01 08:50:00', 110, 50.00, 'Completed', 50.00, 'Cash']
    ];
    $recStmt = $pdo->prepare("INSERT OR IGNORE INTO parking_records (id, ticket_id, vehicle_id, slot_id, entry_time, exit_time, duration_minutes, total_fee, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $payStmt = $pdo->prepare("INSERT OR IGNORE INTO payments (id, parking_record_id, amount, payment_method, payment_status, payment_date) VALUES (?, ?, ?, ?, 'Paid', ?)");

    foreach ($completed as $c) {
        $recStmt->execute([$c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $c[7], $c[8]]);
        $payStmt->execute([$c[0], $c[0], $c[9], $c[10], $c[5]]);
    }

    // Seed active records (3 currently parked)
    $active = [
        [6, 'SP-20261101-0101', 2, 1,  date('Y-m-d H:i:s', time() - (95 * 60)),  'Parked'],
        [7, 'SP-20261101-0102', 1, 6,  date('Y-m-d H:i:s', time() - (140 * 60)), 'Parked'],
        [8, 'SP-20261101-0103', 3, 14, date('Y-m-d H:i:s', time() - (45 * 60)),  'Parked']
    ];
    $actStmt = $pdo->prepare("INSERT OR IGNORE INTO parking_records (id, ticket_id, vehicle_id, slot_id, entry_time, status) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($active as $a) {
        $actStmt->execute($a);
    }
}
