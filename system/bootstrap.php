<?php

declare(strict_types=1);

use Oprcast\Application;
use Oprcast\ContentRepository;
use Oprcast\FeedBuilder;
use Oprcast\FrontMatter;
use Oprcast\Localization;
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
$config['media_formats'] = require __DIR__ . '/config/media.php';
date_default_timezone_set($config['timezone']);
$contentLocalization = new Localization((string) $config['language'], __DIR__ . '/lang');
$config['language_code'] = $contentLocalization->languageCode();
$config['site_description'] = $contentLocalization->get('site_description');
$language = Localization::browserLanguage(
    (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''),
    (string) $config['language'],
    __DIR__ . '/lang',
);
$localization = new Localization($language, __DIR__ . '/lang');

$markdown = new Markdown($contentLocalization->get('chapters_keyword'));
$repository = new ContentRepository($config['content_path'], new FrontMatter(), $markdown, $config['media_formats'], $config['cdn_url'] ?? null);

return new Application($config, $repository, $markdown, new FeedBuilder($config, $markdown), $localization);

