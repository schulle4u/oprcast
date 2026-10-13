<?php

declare(strict_types=1);

// Router for PHP's development server. Apache uses .htaccess instead.
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if ($path !== '/' && is_file($file) && !in_array($extension, ['md', 'php'], true)) {
    return false;
}

require __DIR__ . '/index.php';

