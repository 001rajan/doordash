<?php
/**
 * App configuration — adjust for your XAMPP MySQL user/password.
 * Target: PHP 8.0+ (e.g. 8.2 / 8.4 bundled with current XAMPP).
 */
declare(strict_types=1);

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hyperlocal_food');

define('SITE_NAME', 'HyperLocal Eats');
define('BASE_PATH', dirname(__DIR__));

// Web path to app root (works under htdocs/miniproject/food-delivery-app or htdocs/food-delivery-app)
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$appRoot = realpath(dirname(__DIR__)) ?: '';
if ($docRoot !== '' && $appRoot !== '' && str_starts_with(strtolower($appRoot), strtolower($docRoot))) {
    $rel = substr($appRoot, strlen($docRoot));
    $rel = str_replace('\\', '/', $rel);
    define('BASE_URL', $rel === '' || $rel === '/' ? '' : rtrim($rel, '/'));
} else {
    define('BASE_URL', '/food-delivery-app');
}

define('DELIVERY_FEE_FLAT', 29.00);
define('TAX_RATE', 0.05); // 5%
