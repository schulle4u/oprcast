<?php

declare(strict_types=1);

namespace Oprcast;

use DateTimeImmutable;
use FilesystemIterator;

/**
 * Loads podcast series, episodes and pages from the filesystem.
 *
 * Besides mapping Markdown files to normalized records, the repository keeps
 * path handling inside the content root and resolves media variants and URLs.
 */
final class ContentRepository
{
    private readonly ?string $cdnUrl;

    /**
     * @param array<string, array{extension: string, mime_type: string}> $mediaFormats
     */
    public function __construct(
        private readonly string $root,
        private readonly FrontMatter $frontMatter,
        private readonly Markdown $markdown,
        private readonly array $mediaFormats,
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

    /**
     * Returns every series with an index file, sorted by title.
     *
     * @return list<array<string, mixed>>
     */
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

    /**
     * Loads one series by its URL-safe slug.
     *
     * @return array<string, mixed>
     */
    public function seriesBySlug(string $slug): array
    {
        if (!$this->validSlug($slug)) {
            throw new ContentException('Podcast series not found.');
        }
        return $this->loadSeries($slug);
    }

    /**
     * Returns a series' episodes in reverse publication order.
     *
     * Future episodes are hidden unless explicitly requested, which allows the
     * same repository to serve the site and validate scheduled publications.
     *
     * @return list<array<string, mixed>>
     */
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

    /**
     * Collects episodes from all series and annotates them with the series title.
     *
     * @return list<array<string, mixed>>
     */
    public function allEpisodes(bool $includeFuture = false): array
    {
        $episodes = [];
        foreach ($this->series() as $series) {
            foreach ($this->episodes((string) $series['slug'], $includeFuture) as $episode) {
                $episode['series_title'] = $series['title'];
                $episodes[] = $episode;
            }
        }
        usort($episodes, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);
        return $episodes;
    }

    /**
     * Finds one published episode by series and episode slug.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Loads all standalone pages from the reserved `pages` directory.
     *
     * @return list<array<string, mixed>>
     */
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

    /**
     * Finds a standalone page by slug.
     *
     * @return array<string, mixed>
     */
    public function page(string $slug): array
    {
        foreach ($this->pages() as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }
        throw new ContentException('Page not found.');
    }

    /**
     * Resolves a media field to a safe HTTP(S), CDN or local-content URL.
     *
     * @param array<string, mixed> $item
     */
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

    /** Reports whether relative media paths are published through a configured CDN. */
    public function usesCdnForRelativeMedia(): bool
    {
        return $this->cdnUrl !== null;
    }

    /**
     * Validates and returns the media formats enabled for a series.
     *
     * @param array<string, mixed> $series
     * @return list<string>
     */
    public function seriesFormats(array $series): array
    {
        $formats = $series['formats'] ?? [];
        if (!is_array($formats)) {
            throw new ContentException("{$series['source']}: formats must be a list.");
        }
        if ($formats === []) {
            throw new ContentException("{$series['source']}: formats must contain at least one media format.");
        }
        if (count($formats) !== count(array_unique($formats, SORT_REGULAR))) {
            throw new ContentException("{$series['source']}: formats must not contain duplicates.");
        }
        foreach ($formats as $format) {
            if (!is_string($format) || preg_match('/^[a-z0-9]+$/', $format) !== 1) {
                throw new ContentException("{$series['source']}: every media format must be a lowercase name.");
            }
            $definition = $this->mediaFormats[$format] ?? null;
            if (!is_array($definition) || !isset($definition['extension'], $definition['mime_type'])) {
                throw new ContentException("{$series['source']}: unknown media format '{$format}'.");
            }
        }
        return array_values($formats);
    }

    /**
     * Returns the series' default format after checking that it is enabled.
     *
     * @param array<string, mixed> $series
     */
    public function defaultFormat(array $series): string
    {
        $format = $series['default_format'] ?? null;
        if (!is_string($format) || $format === '') {
            throw new ContentException("{$series['source']}: missing required field 'default_format'.");
        }
        if (!in_array($format, $this->seriesFormats($series), true)) {
            throw new ContentException("{$series['source']}: default_format must be included in formats.");
        }
        return $format;
    }

    /**
     * Builds enclosure metadata for every media format enabled by the series.
     *
     * @param array<string, mixed> $series
     * @param array<string, mixed> $episode
     * @return list<array{format: string, url: string, mime_type: string, length: int, default: bool}>
     */
    public function enclosures(array $series, array $episode, string $baseUrl): array
    {
        $stem = $episode['audio'] ?? null;
        if (!is_string($stem) || trim($stem) === '') {
            throw new ContentException("{$episode['source']}: missing required field 'audio'.");
        }

        $default = $this->defaultFormat($series);
        $enclosures = [];
        foreach ($this->seriesFormats($series) as $format) {
            $definition = $this->mediaFormats[$format] ?? null;
            if (!is_array($definition) || !isset($definition['extension'], $definition['mime_type'])) {
                throw new ContentException("{$series['source']}: unknown media format '{$format}'.");
            }
            $media = $stem . (string) $definition['extension'];
            $item = array_replace($episode, ['audio_file' => $media]);
            $url = (string) $this->mediaUrl($item, 'audio_file', $baseUrl);
            $length = $this->mediaLength($episode, $media, $format);
            $enclosures[] = [
                'format' => $format,
                'url' => $url,
                'mime_type' => (string) $definition['mime_type'],
                'length' => $length,
                'default' => $format === $default,
            ];
        }
        return $enclosures;
    }

    /**
     * Selects a single enclosure in the requested format.
     *
     * @param array<string, mixed> $series
     * @param array<string, mixed> $episode
     * @return array{format: string, url: string, mime_type: string, length: int, default: bool}
     */
    public function enclosure(array $series, array $episode, string $format, string $baseUrl): array
    {
        foreach ($this->enclosures($series, $episode, $baseUrl) as $enclosure) {
            if ($enclosure['format'] === $format) {
                return $enclosure;
            }
        }
        throw new ContentException("{$series['source']}: media format '{$format}' is not enabled for this podcast.");
    }

    /**
     * Determines media size locally or reads the required explicit remote size.
     *
     * @param array<string, mixed> $episode
     */
    private function mediaLength(array $episode, string $media, string $format): int
    {
        if (filter_var($media, FILTER_VALIDATE_URL) === false) {
            $path = dirname((string) $episode['source']) . '/' . ltrim($media, '/');
            if (is_file($path)) {
                $length = filesize($path);
                if ($length !== false && $length > 0) {
                    return $length;
                }
            }
            if ($this->cdnUrl === null) {
                throw new ContentException("{$episode['source']}: local audio file does not exist or is empty: {$media}.");
            }
        }
        $field = 'audio_length_' . $format;
        $length = $episode[$field] ?? null;
        if (!is_int($length) || $length < 1) {
            throw new ContentException("{$episode['source']}: missing positive '{$field}' for media that is not available locally.");
        }
        return $length;
    }

    /**
     * Loads and normalizes a series index record.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Loads an episode file and derives its slug, publication date and chapters.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Reads a content file and delegates front matter parsing.
     *
     * @return array{meta: array<string, mixed>, body: string}
     */
    private function read(string $file): array
    {
        $source = file_get_contents($file);
        if ($source === false) {
            throw new ContentException("Cannot read {$file}.");
        }
        return $this->frontMatter->parse($source, $file);
    }

    /** Resolves an existing series directory after validating its slug. */
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

    /** Checks the lowercase, hyphen-separated slug format used in routes and paths. */
    private function validSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
}

