<?php

declare(strict_types=1);

namespace Oprcast;

use DateTimeImmutable;
use FilesystemIterator;

final class ContentRepository
{
    private readonly ?string $cdnUrl;

    public function __construct(
        private readonly string $root,
        private readonly FrontMatter $frontMatter,
        private readonly Markdown $markdown,
        ?string $cdnUrl = null,
    ) {
        $cdnUrl = is_string($cdnUrl) ? trim($cdnUrl) : null;
        if ($cdnUrl !== null && $cdnUrl !== '') {
            $scheme = strtolower((string) parse_url($cdnUrl, PHP_URL_SCHEME));
            if (filter_var($cdnUrl, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
                throw new ContentException("Invalid CDN URL '{$cdnUrl}'.");
            }
            $this->cdnUrl = rtrim($cdnUrl, '/');
            return;
        }
        $this->cdnUrl = null;
    }

    /** @return list<array<string, mixed>> */
    public function series(): array
    {
        if (!is_dir($this->root)) {
            return [];
        }

        $series = [];
        foreach (new FilesystemIterator($this->root, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (!$entry->isDir() || $entry->getFilename() === 'pages' || !$this->validSlug($entry->getFilename())) {
                continue;
            }
            $index = $entry->getPathname() . '/index.md';
            if (is_file($index)) {
                $series[] = $this->loadSeries($entry->getFilename());
            }
        }
        usort($series, static fn (array $a, array $b): int => strcasecmp((string) $a['title'], (string) $b['title']));
        return $series;
    }

    /** @return array<string, mixed> */
    public function seriesBySlug(string $slug): array
    {
        if (!$this->validSlug($slug)) {
            throw new ContentException('Podcast series not found.');
        }
        return $this->loadSeries($slug);
    }

    /** @return list<array<string, mixed>> */
    public function episodes(string $seriesSlug, bool $includeFuture = false): array
    {
        $directory = $this->safeDirectory($seriesSlug);
        $episodes = [];
        foreach (glob($directory . '/*.md') ?: [] as $file) {
            if (basename($file) === 'index.md') {
                continue;
            }
            $episode = $this->loadEpisode($seriesSlug, $file);
            if ($includeFuture || $episode['date'] <= new DateTimeImmutable('now')) {
                $episodes[] = $episode;
            }
        }
        usort($episodes, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);
        return $episodes;
    }

    /** @return array<string, mixed> */
    public function episode(string $seriesSlug, string $episodeSlug): array
    {
        if (!$this->validSlug($episodeSlug)) {
            throw new ContentException('Episode not found.');
        }
        foreach ($this->episodes($seriesSlug) as $episode) {
            if ($episode['slug'] === $episodeSlug) {
                return $episode;
            }
        }
        throw new ContentException('Episode not found.');
    }

    /** @return list<array<string, mixed>> */
    public function pages(): array
    {
        $directory = $this->root . '/pages';
        if (!is_dir($directory)) {
            return [];
        }
        $pages = [];
        foreach (glob($directory . '/*.md') ?: [] as $file) {
            $parsed = $this->read($file);
            $slug = pathinfo($file, PATHINFO_FILENAME);
            if (!$this->validSlug($slug)) {
                continue;
            }
            $pages[] = array_replace($parsed['meta'], [
                'slug' => $slug,
                'title' => $parsed['meta']['title'] ?? ucfirst(str_replace('-', ' ', $slug)),
                'body' => $parsed['body'],
                'html' => $this->markdown->render($parsed['body']),
                'source' => $file,
                'modified' => filemtime($file) ?: time(),
            ]);
        }
        return $pages;
    }

    /** @return array<string, mixed> */
    public function page(string $slug): array
    {
        foreach ($this->pages() as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }
        throw new ContentException('Page not found.');
    }

    public function mediaUrl(array $item, string $field, string $baseUrl): ?string
    {
        $value = $item[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            if (!in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                throw new ContentException("Unsupported media URL scheme in '{$value}'.");
            }
            return $value;
        }
        if (str_contains($value, '..') || str_contains($value, '\\')) {
            throw new ContentException("Unsafe media path '{$value}'.");
        }
        $path = implode('/', array_map('rawurlencode', explode('/', ltrim($value, '/'))));
        if ($this->cdnUrl !== null) {
            return $this->cdnUrl . '/' . $path;
        }
        return rtrim($baseUrl, '/') . '/content/' . rawurlencode((string) $item['series_slug']) . '/' . $path;
    }

    public function usesCdnForRelativeMedia(): bool
    {
        return $this->cdnUrl !== null;
    }

    /** @return array<string, mixed> */
    private function loadSeries(string $slug): array
    {
        $file = $this->safeDirectory($slug) . '/index.md';
        if (!is_file($file)) {
            throw new ContentException("Podcast series '{$slug}' not found.");
        }
        $parsed = $this->read($file);
        return array_replace($parsed['meta'], [
            'slug' => $slug,
            'series_slug' => $slug,
            'title' => $parsed['meta']['title'] ?? ucfirst(str_replace('-', ' ', $slug)),
            'body' => $parsed['body'],
            'html' => $this->markdown->render($parsed['body']),
            'source' => $file,
            'modified' => filemtime($file) ?: time(),
        ]);
    }

    /** @return array<string, mixed> */
    private function loadEpisode(string $seriesSlug, string $file): array
    {
        $parsed = $this->read($file);
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $slug = preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', $filename) ?: $filename;
        if (!$this->validSlug($slug)) {
            throw new ContentException("{$file}: invalid episode slug.");
        }
        try {
            $date = new DateTimeImmutable((string) ($parsed['meta']['date'] ?? substr($filename, 0, 10)));
        } catch (\Throwable) {
            throw new ContentException("{$file}: invalid or missing publication date.");
        }
        return array_replace($parsed['meta'], [
            'slug' => $slug,
            'series_slug' => $seriesSlug,
            'title' => $parsed['meta']['title'] ?? ucfirst(str_replace('-', ' ', $slug)),
            'date' => $date,
            'body' => $parsed['body'],
            'html' => $this->markdown->render($parsed['body']),
            'chapters' => $this->markdown->chapters($parsed['body']),
            'source' => $file,
            'modified' => filemtime($file) ?: time(),
        ]);
    }

    /** @return array{meta: array<string, mixed>, body: string} */
    private function read(string $file): array
    {
        $source = file_get_contents($file);
        if ($source === false) {
            throw new ContentException("Cannot read {$file}.");
        }
        return $this->frontMatter->parse($source, $file);
    }

    private function safeDirectory(string $slug): string
    {
        if (!$this->validSlug($slug)) {
            throw new ContentException('Invalid series slug.');
        }
        $directory = $this->root . '/' . $slug;
        if (!is_dir($directory)) {
            throw new ContentException("Podcast series '{$slug}' not found.");
        }
        return $directory;
    }

    private function validSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
}

