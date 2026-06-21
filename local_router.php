<?php
// local_router.php
// Custom router script for PHP built-in web server to host the project locally

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$projectRoot = __DIR__;

// Redirect root to /msoceurope/ to match React's base path
if ($uri === '/' || $uri === '' || $uri === '/msoceurope') {
    header("Location: /msoceurope/");
    exit;
}

// 1. Route API requests to Laravel public/index.php
if (preg_match('/^\/api\//', $uri)) {
    $_SERVER['SCRIPT_NAME'] = '/laravel/public/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $projectRoot . '/laravel/public/index.php';
    chdir($projectRoot . '/laravel/public');
    require_once $projectRoot . '/laravel/public/index.php';
    exit;
}

// 2. Route storage requests to Laravel storage
if (preg_match('/^\/storage\/(.*)$/', $uri, $matches)) {
    $storageFile = $projectRoot . '/laravel/public/storage/' . $matches[1];
    if (file_exists($storageFile) && !is_dir($storageFile)) {
        $mimeType = mime_content_type($storageFile);
        header("Content-Type: $mimeType");
        readfile($storageFile);
        exit;
    }
}

// 3. Route assets and favicon under /msoceurope/
if (preg_match('/^\/msoceurope\/(.*)$/', $uri, $matches)) {
    $filePath = $projectRoot . '/' . $matches[1];
    if (file_exists($filePath) && !is_dir($filePath)) {
        $mimeType = mime_content_type($filePath);
        // Fix mime types if needed (e.g. for .js, .css, .svg)
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'js') {
            $mimeType = 'application/javascript';
        } elseif ($ext === 'css') {
            $mimeType = 'text/css';
        } elseif ($ext === 'svg') {
            $mimeType = 'image/svg+xml';
        }
        header("Content-Type: $mimeType");
        readfile($filePath);
        exit;
    }
}

// 4. If request is a static file that exists in the root folder, serve it
if ($uri !== '/' && file_exists($projectRoot . $uri) && !is_dir($projectRoot . $uri)) {
    $filePath = $projectRoot . $uri;
    $mimeType = mime_content_type($filePath);
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext === 'js') {
        $mimeType = 'application/javascript';
    } elseif ($ext === 'css') {
        $mimeType = 'text/css';
    } elseif ($ext === 'svg') {
        $mimeType = 'image/svg+xml';
    }
    header("Content-Type: $mimeType");
    readfile($filePath);
    exit;
}

// 5. Fallback all other routes to React's index.html (SPA Routing)
$_SERVER['SCRIPT_NAME'] = '/index.html';
$_SERVER['SCRIPT_FILENAME'] = $projectRoot . '/index.html';
header("Content-Type: text/html");
require_once $projectRoot . '/index.html';
exit;
