<?php

declare(strict_types=1);

use Oprcast\Application;
use Oprcast\ContentRepository;
use Oprcast\FeedBuilder;
use Oprcast\FrontMatter;
use Oprcast\Markdown;

if (PHP_VERSION_ID < 80300) {
    http_response_code(500);
    exit('OPRCast requires PHP 8.3 or newer.');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Oprcast\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/modules/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require __DIR__ . '/config/site.php';
date_default_timezone_set($config['timezone']);

$markdown = new Markdown();
$repository = new ContentRepository($config['content_path'], new FrontMatter(), $markdown);

return new Application($config, $repository, $markdown, new FeedBuilder($config, $markdown));

