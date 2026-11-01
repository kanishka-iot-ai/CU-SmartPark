<?php
/**
 * SmartPark - Authentication Module
 * 
 * Handles secure session authentication, password verification,
 * login rate limiting, and session security.
 */

require_once __DIR__ . '/functions.php';

/**
 * Attempt admin login.
 * 
 * @param string $username
 * @param string $password
 * @param bool $remember
 * @return array ['success' => bool, 'message' => string]
 */
function attempt_login($username, $password, $remember = false) {
    $pdo = get_db_connection();

    // Rate limiting check using session
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $rateKey = 'login_attempts_' . md5($ip . '_' . $username);
    $attempts = $_SESSION[$rateKey] ?? ['count' => 0, 'first_attempt' => time()];

    // Reset counter if lock time (15 mins) has passed
    if (time() - $attempts['first_attempt'] > 900) {
        $attempts = ['count' => 0, 'first_attempt' => time()];
    }

    if ($attempts['count'] >= 5) {
        $remaining = 15 - ceil((time() - $attempts['first_attempt']) / 60);
        return [
            'success' => false,
            'message' => "Too many failed attempts. Please try again in {$remaining} minute(s)."
        ];
    }

    // Look up admin by username
    $stmt = $pdo->prepare("SELECT id, username, password, full_name, email FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        // Successful login: reset attempts
        unset($_SESSION[$rateKey]);

        // Regenerate session ID to mitigate session fixation (check if headers not sent)
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $admin['id'];
        $_SESSION['admin_username']  = $admin['username'];
        $_SESSION['admin_name']      = $admin['full_name'];
        $_SESSION['admin_email']     = $admin['email'];
        $_SESSION['last_activity']   = time();

        if ($remember) {
            setcookie('smartpark_user', $username, time() + (86400 * 30), "/");
        } else {
            if (isset($_COOKIE['smartpark_user'])) {
                setcookie('smartpark_user', '', time() - 3600, "/");
            }
        }

        return ['success' => true, 'message' => 'Login successful.'];
    }

    // Failed login: increment rate limit counter
    $attempts['count']++;
    $_SESSION[$rateKey] = $attempts;

    return [
        'success' => false,
        'message' => 'Invalid username or password.'
    ];
}

/**
 * Destroy current session and log user out.
 */
function logout_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }

    session_destroy();
}
