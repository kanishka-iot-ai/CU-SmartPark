<?php
/**
 * Vercel Serverless Gateway Router
 * CU SmartPark – Chandigarh University Vehicle Parking Management System
 */

// Handle PHP sessions in serverless environments
if (session_status() === PHP_SESSION_NONE) {
    $tempDir = is_dir('/tmp') ? '/tmp' : (getenv('TMP') ?: getenv('TEMP') ?: sys_get_temp_dir());
    $sessDir = rtrim($tempDir, '/\\') . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0777, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        @session_save_path($sessDir);
    }
}

// Parse request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';
$path = urldecode($path);
$cleanPath = trim($path, '/');

// Base directory is project root
$baseDir = dirname(__DIR__);

// Default to landing page / booking
if ($cleanPath === '' || $cleanPath === 'index.php') {
    chdir($baseDir);
    require $baseDir . '/index.php';
    exit;
}

// Serve static assets if reached via fallback
if (strpos($cleanPath, 'assets/') === 0) {
    $filePath = $baseDir . '/' . $cleanPath;
    if (file_exists($filePath) && is_file($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=UTF-8',
            'js'    => 'application/javascript; charset=UTF-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'json'  => 'application/json'
        ];
        header("Content-Type: " . ($mimes[$ext] ?? 'application/octet-stream'));
        header("Cache-Control: public, max-age=86400");
        readfile($filePath);
        exit;
    }
}

// Resolve PHP files (either with or without .php extension)
$targetFile = $baseDir . '/' . $cleanPath;
if (!file_exists($targetFile) || !is_file($targetFile)) {
    if (file_exists($targetFile . '.php') && is_file($targetFile . '.php')) {
        $targetFile .= '.php';
    }
}

// Security: Prevent directory traversal outside project root
$realBase = realpath($baseDir);
$realTarget = realpath($targetFile);
if ($realTarget && strpos($realTarget, $realBase) === 0 && is_file($realTarget)) {
    // Set working directory to the target file's directory so relative paths work seamlessly
    chdir(dirname($realTarget));
    require $realTarget;
    exit;
}

// 404 Not Found
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found - CU SmartPark</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0F172A; color: #F8FAFC; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: #1E293B; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); max-width: 480px; text-align: center; border: 1px solid #334155; }
        h1 { color: #EF4444; margin: 0 0 10px; font-size: 56px; font-weight: 800; }
        h2 { margin: 0 0 12px; font-size: 22px; color: #F1F5F9; }
        p { color: #94A3B8; margin-bottom: 24px; line-height: 1.6; font-size: 14px; }
        code { background: #0F172A; padding: 3px 8px; border-radius: 4px; color: #38BDF8; font-family: monospace; }
        a { display: inline-flex; align-items: center; gap: 8px; background: #2563EB; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; }
        a:hover { background: #1D4ED8; }
    </style>
</head>
<body>
    <div class="card">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The requested route <code>/<?php echo htmlspecialchars($cleanPath); ?></code> could not be located in CU SmartPark.</p>
        <a href="/">Return to Home / Booking</a>
    </div>
</body>
</html>
