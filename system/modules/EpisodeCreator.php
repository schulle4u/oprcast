<?php

declare(strict_types=1);

namespace Oprcast;

/** Creates an editable draft without replacing any existing content. */
final class EpisodeCreator
{
    public function create(ContentRepository $repository, string $seriesSlug, string $slug): string
    {
        if ($seriesSlug === 'pages') {
            throw new ContentException('The pages directory is reserved for static pages.');
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1 || $slug === 'index') {
            throw new ContentException('Episode slug must contain lowercase letters, digits and single hyphens; index is reserved.');
        }
        $series = $repository->seriesBySlug($seriesSlug);
        foreach ($repository->episodes($seriesSlug, true, true) as $episode) {
            if ($episode['slug'] === $slug) {
                throw new ContentException("Episode slug '{$slug}' already exists in this series.");
            }
        }
        $now = new \DateTimeImmutable('now');
        $path = dirname($series['source']) . '/' . $now->format('Y-m-d') . '-' . $slug . '.md';
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $guid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
        $title = ucfirst(str_replace('-', ' ', $slug));
        $content = "---\ntitle: \"{$title}\"\ndate: " . $now->format('c') . "\nguid: urn:uuid:{$guid}\ndraft: true\naudio: \"{$slug}\"\nepisode_type: full\n# Add duration: MM:SS when the audio is ready.\n---\n\n";
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new ContentException("Cannot create '{$path}'; the file may already exist or the directory is not writable.");
        }
        try {
            if (fwrite($handle, $content) !== strlen($content)) {
                throw new ContentException("Could not write complete draft '{$path}'.");
            }
        } finally {
            fclose($handle);
        }
        return $path;
    }
}
