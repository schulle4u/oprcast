<?php

declare(strict_types=1);

return [
    'title' => 'OPRCast',
    'description' => 'Podcasts, unabhängig veröffentlicht.',
    'language' => 'de-DE',
    'timezone' => 'Europe/Berlin',
    // Set this in production, for example: https://podcasts.example.org
    'base_url' => getenv('OPRCAST_BASE_URL') ?: null,
    'content_path' => dirname(__DIR__, 2) . '/content',
    'feed_limit' => 100,
    'development' => filter_var(getenv('OPRCAST_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
];

