<?php

declare(strict_types=1);

return [
    'title' => 'OPRCast',
    // Fallback CMS translation in system/lang/{language}.php when browser detection fails.
    // Also determines feed metadata and the chapter heading used in Markdown content.
    'language' => 'de',
    'timezone' => 'Europe/Berlin',
    // Set this in production, for example: https://podcasts.example.org
    'base_url' => getenv('OPRCAST_BASE_URL') ?: null,
    // Optional base URL for relative media paths, for example: https://cdn.podcast.org/
    'cdn_url' => getenv('OPRCAST_CDN_URL') ?: null,
    'content_path' => dirname(__DIR__, 2) . '/content',
    'feed_limit' => 100,
    'development' => filter_var(getenv('OPRCAST_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
];

