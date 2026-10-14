<?php

declare(strict_types=1);

namespace Oprcast;

use DateTimeInterface;
use XMLWriter;

final class FeedBuilder
{
    public function __construct(private readonly array $config, private readonly Markdown $markdown)
    {
    }

    public function rss(array $series, array $episodes, string $baseUrl, ContentRepository $repository, ?string $mediaFormat = null): string
    {
        $defaultAlias = $mediaFormat === null;
        $mediaFormat ??= $repository->defaultFormat($series);
        if (!in_array($mediaFormat, $repository->seriesFormats($series), true)) {
            throw new ContentException("{$series['source']}: media format '{$mediaFormat}' is not enabled for this podcast.");
        }
        $xml = $this->writer();
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:podcast', 'https://podcastindex.org/namespace/1.0');
        $xml->startElement('channel');

        $seriesUrl = $this->url($baseUrl, $series['slug'] . '/');
        $feedName = $defaultAlias ? 'feed.xml' : 'feed.' . $mediaFormat . '.xml';
        $feedUrl = $this->url($baseUrl, $series['slug'] . '/' . $feedName);
        $this->element($xml, 'title', (string) $series['title']);
        $this->element($xml, 'link', $seriesUrl);
        $this->element($xml, 'description', (string) ($series['description'] ?? $this->markdown->text((string) $series['body'])));
        $this->element($xml, 'language', (string) ($series['language'] ?? $this->config['language_code'] ?? $this->config['language']));
        $this->element($xml, 'lastBuildDate', $this->lastModified($series, $episodes)->format(DateTimeInterface::RFC2822));
        if (isset($series['copyright'])) {
            $this->element($xml, 'copyright', (string) $series['copyright']);
        }
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $feedUrl);
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();
        $this->element($xml, 'itunes:author', (string) ($series['author'] ?? ''));
        $this->element($xml, 'itunes:summary', (string) ($series['description'] ?? ''));
        $this->element($xml, 'itunes:explicit', $this->bool($series['explicit'] ?? false));
        $this->element($xml, 'itunes:type', (string) ($series['type'] ?? 'episodic'));
        if ($image = $repository->mediaUrl($series, 'image', $baseUrl)) {
            $xml->startElement('itunes:image');
            $xml->writeAttribute('href', $image);
            $xml->endElement();
            $xml->startElement('image');
            $this->element($xml, 'url', $image);
            $this->element($xml, 'title', (string) $series['title']);
            $this->element($xml, 'link', $seriesUrl);
            $xml->endElement();
        }
        foreach ((array) ($series['category'] ?? []) as $category) {
            $xml->startElement('itunes:category');
            $xml->writeAttribute('text', (string) $category);
            $xml->endElement();
        }

        foreach (array_slice($episodes, 0, (int) $this->config['feed_limit']) as $episode) {
            $episodeUrl = $this->url($baseUrl, $series['slug'] . '/' . $episode['slug'] . '/');
            $xml->startElement('item');
            $this->element($xml, 'title', (string) $episode['title']);
            $this->element($xml, 'link', $episodeUrl);
            $xml->startElement('guid');
            $xml->writeAttribute('isPermaLink', 'false');
            $xml->text((string) ($episode['guid'] ?? $episodeUrl));
            $xml->endElement();
            $this->element($xml, 'pubDate', $episode['date']->format(DateTimeInterface::RFC2822));
            $description = (string) ($episode['description'] ?? $this->markdown->text((string) $episode['body']));
            $this->element($xml, 'description', $description);
            $xml->startElement('content:encoded');
            $xml->writeCdata((string) $episode['html']);
            $xml->endElement();
            $enclosures = $repository->enclosures($series, $episode, $baseUrl);
            $this->writeEnclosure($xml, $repository->enclosure($series, $episode, $mediaFormat, $baseUrl));
            $this->writeAlternateEnclosures($xml, $enclosures, $mediaFormat);
            foreach (['duration', 'episode', 'season'] as $tag) {
                if (isset($episode[$tag])) {
                    $this->element($xml, 'itunes:' . $tag, (string) $episode[$tag]);
                }
            }
            $this->element($xml, 'itunes:episodeType', (string) ($episode['episode_type'] ?? 'full'));
            $this->element($xml, 'itunes:explicit', $this->bool($episode['explicit'] ?? $series['explicit'] ?? false));
            if ($episode['chapters'] !== []) {
                $xml->startElement('podcast:chapters');
                $xml->writeAttribute('url', $this->url($baseUrl, $series['slug'] . '/' . $episode['slug'] . '/chapters.json'));
                $xml->writeAttribute('type', 'application/json+chapters');
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        return $xml->outputMemory();
    }

    /**
     * Builds one RSS feed containing the latest episodes from every series.
     *
     * @param list<array<string, mixed>> $seriesList
     * @param list<array<string, mixed>> $episodes
     */
    public function metaRss(array $seriesList, array $episodes, string $baseUrl, ContentRepository $repository): string
    {
        $xml = $this->writer();
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:podcast', 'https://podcastindex.org/namespace/1.0');
        $xml->startElement('channel');

        $homeUrl = $this->url($baseUrl, '');
        $feedUrl = $this->url($baseUrl, 'feed.xml');
        $this->element($xml, 'title', (string) ($this->config['title'] ?? 'OPRCast'));
        $this->element($xml, 'link', $homeUrl);
        $this->element($xml, 'description', (string) ($this->config['site_description'] ?? ''));
        $this->element($xml, 'language', (string) ($this->config['language_code'] ?? $this->config['language'] ?? 'en'));
        $this->element($xml, 'lastBuildDate', $this->latestModified($seriesList, $episodes)->format(DateTimeInterface::RFC2822));
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $feedUrl);
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        $seriesBySlug = [];
        foreach ($seriesList as $series) {
            $seriesBySlug[(string) $series['slug']] = $series;
        }
        foreach (array_slice($episodes, 0, (int) $this->config['feed_limit']) as $episode) {
            $seriesSlug = (string) $episode['series_slug'];
            $series = $seriesBySlug[$seriesSlug] ?? [];
            $episodeUrl = $this->url($baseUrl, $seriesSlug . '/' . $episode['slug'] . '/');
            $xml->startElement('item');
            $this->element($xml, 'title', (string) $episode['title']);
            $this->element($xml, 'link', $episodeUrl);
            $xml->startElement('guid');
            $xml->writeAttribute('isPermaLink', 'false');
            $xml->text((string) ($episode['guid'] ?? $episodeUrl));
            $xml->endElement();
            $this->element($xml, 'pubDate', $episode['date']->format(DateTimeInterface::RFC2822));
            $this->element($xml, 'description', (string) ($episode['description'] ?? $this->markdown->text((string) $episode['body'])));
            $xml->startElement('content:encoded');
            $xml->writeCdata((string) $episode['html']);
            $xml->endElement();
            $xml->startElement('source');
            $xml->writeAttribute('url', $this->url($baseUrl, $seriesSlug . '/feed.xml'));
            $xml->text((string) ($series['title'] ?? $episode['series_title'] ?? $seriesSlug));
            $xml->endElement();
            $this->element($xml, 'itunes:author', (string) ($series['author'] ?? $series['title'] ?? ''));
            $enclosures = $repository->enclosures($series, $episode, $baseUrl);
            $defaultFormat = $repository->defaultFormat($series);
            $this->writeEnclosure($xml, $repository->enclosure($series, $episode, $defaultFormat, $baseUrl));
            $this->writeAlternateEnclosures($xml, $enclosures, $defaultFormat);
            foreach (['duration', 'episode', 'season'] as $tag) {
                if (isset($episode[$tag])) {
                    $this->element($xml, 'itunes:' . $tag, (string) $episode[$tag]);
                }
            }
            $this->element($xml, 'itunes:episodeType', (string) ($episode['episode_type'] ?? 'full'));
            $this->element($xml, 'itunes:explicit', $this->bool($episode['explicit'] ?? $series['explicit'] ?? false));
            if ($episode['chapters'] !== []) {
                $xml->startElement('podcast:chapters');
                $xml->writeAttribute('url', $this->url($baseUrl, $seriesSlug . '/' . $episode['slug'] . '/chapters.json'));
                $xml->writeAttribute('type', 'application/json+chapters');
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        return $xml->outputMemory();
    }

    public function atom(array $series, array $episodes, string $baseUrl, ContentRepository $repository): string
    {
        $xml = $this->writer();
        $xml->startElement('feed');
        $xml->writeAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        $seriesUrl = $this->url($baseUrl, $series['slug'] . '/');
        $this->element($xml, 'title', (string) $series['title']);
        $this->element($xml, 'id', $seriesUrl);
        $this->element($xml, 'updated', $this->lastModified($series, $episodes)->format(DateTimeInterface::ATOM));
        $this->link($xml, $seriesUrl, 'alternate', 'text/html');
        $this->link($xml, $this->url($baseUrl, $series['slug'] . '/feed.atom'), 'self', 'application/atom+xml');
        $xml->startElement('author');
        $this->element($xml, 'name', (string) ($series['author'] ?? $series['title']));
        $xml->endElement();
        foreach (array_slice($episodes, 0, (int) $this->config['feed_limit']) as $episode) {
            $url = $this->url($baseUrl, $series['slug'] . '/' . $episode['slug'] . '/');
            $xml->startElement('entry');
            $this->element($xml, 'title', (string) $episode['title']);
            $this->element($xml, 'id', (string) ($episode['guid'] ?? $url));
            $this->element($xml, 'published', $episode['date']->format(DateTimeInterface::ATOM));
            $this->element($xml, 'updated', (new \DateTimeImmutable('@' . $episode['modified']))->format(DateTimeInterface::ATOM));
            $this->link($xml, $url, 'alternate', 'text/html');
            foreach ($repository->enclosures($series, $episode, $baseUrl) as $enclosure) {
                $this->link($xml, $enclosure['url'], 'enclosure', $enclosure['mime_type'], (string) $enclosure['length']);
            }
            $xml->startElement('content');
            $xml->writeAttribute('type', 'html');
            $xml->text((string) $episode['html']);
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
        return $xml->outputMemory();
    }

    public function json(array $series, array $episodes, string $baseUrl, ContentRepository $repository): string
    {
        $items = [];
        foreach (array_slice($episodes, 0, (int) $this->config['feed_limit']) as $episode) {
            $url = $this->url($baseUrl, $series['slug'] . '/' . $episode['slug'] . '/');
            $item = [
                'id' => (string) ($episode['guid'] ?? $url),
                'url' => $url,
                'title' => (string) $episode['title'],
                'content_html' => (string) $episode['html'],
                'summary' => (string) ($episode['description'] ?? $this->markdown->text((string) $episode['body'])),
                'date_published' => $episode['date']->format(DateTimeInterface::ATOM),
            ];
            $attachments = [];
            foreach ($repository->enclosures($series, $episode, $baseUrl) as $enclosure) {
                $attachment = [
                    'url' => $enclosure['url'],
                    'mime_type' => $enclosure['mime_type'],
                    'size_in_bytes' => $enclosure['length'],
                ];
                if (isset($episode['duration'])) {
                    $attachment['duration_in_seconds'] = $this->durationSeconds((string) $episode['duration']);
                }
                $attachments[] = $attachment;
            }
            if ($attachments !== []) {
                $item['attachments'] = $attachments;
            }
            $items[] = $item;
        }
        $feed = [
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => (string) $series['title'],
            'home_page_url' => $this->url($baseUrl, $series['slug'] . '/'),
            'feed_url' => $this->url($baseUrl, $series['slug'] . '/feed.json'),
            'description' => (string) ($series['description'] ?? ''),
            'language' => (string) ($series['language'] ?? $this->config['language_code'] ?? $this->config['language']),
            'items' => $items,
        ];
        if ($icon = $repository->mediaUrl($series, 'image', $baseUrl)) {
            $feed['icon'] = $icon;
        }
        return json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    public function chapters(array $episode): string
    {
        return json_encode(['version' => '1.2.0', 'chapters' => $episode['chapters']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    private function writer(): XMLWriter
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        return $xml;
    }

    private function element(XMLWriter $xml, string $name, string $value): void
    {
        $xml->startElement($name);
        $xml->text($value);
        $xml->endElement();
    }

    private function link(XMLWriter $xml, string $href, string $rel, string $type, ?string $length = null): void
    {
        $xml->startElement('link');
        $xml->writeAttribute('href', $href);
        $xml->writeAttribute('rel', $rel);
        $xml->writeAttribute('type', $type);
        if ($length !== null && $length !== '') {
            $xml->writeAttribute('length', $length);
        }
        $xml->endElement();
    }

    /** @param array{url: string, mime_type: string, length: int} $enclosure */
    private function writeEnclosure(XMLWriter $xml, array $enclosure): void
    {
        $xml->startElement('enclosure');
        $xml->writeAttribute('url', $enclosure['url']);
        $xml->writeAttribute('length', (string) $enclosure['length']);
        $xml->writeAttribute('type', $enclosure['mime_type']);
        $xml->endElement();
    }

    /**
     * @param list<array{format: string, url: string, mime_type: string, length: int, default: bool}> $enclosures
     */
    private function writeAlternateEnclosures(XMLWriter $xml, array $enclosures, string $selectedFormat): void
    {
        foreach ($enclosures as $enclosure) {
            $xml->startElement('podcast:alternateEnclosure');
            $xml->writeAttribute('type', $enclosure['mime_type']);
            $xml->writeAttribute('length', (string) $enclosure['length']);
            $xml->writeAttribute('title', strtoupper($enclosure['format']));
            if ($enclosure['format'] === $selectedFormat) {
                $xml->writeAttribute('default', 'true');
            }
            $xml->startElement('podcast:source');
            $xml->writeAttribute('uri', $enclosure['url']);
            $xml->endElement();
            $xml->endElement();
        }
    }

    private function url(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function bool(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 'true' : 'false';
    }

    private function lastModified(array $series, array $episodes): \DateTimeImmutable
    {
        $modified = (int) $series['modified'];
        foreach ($episodes as $episode) {
            $modified = max($modified, (int) $episode['modified']);
        }
        return new \DateTimeImmutable('@' . $modified);
    }

    /**
     * @param list<array<string, mixed>> $seriesList
     * @param list<array<string, mixed>> $episodes
     */
    private function latestModified(array $seriesList, array $episodes): \DateTimeImmutable
    {
        $modified = 0;
        foreach (array_merge($seriesList, $episodes) as $item) {
            $modified = max($modified, (int) ($item['modified'] ?? 0));
        }
        return new \DateTimeImmutable('@' . $modified);
    }

    private function durationSeconds(string $duration): int
    {
        $parts = array_map('intval', explode(':', $duration));
        return count($parts) === 3 ? $parts[0] * 3600 + $parts[1] * 60 + $parts[2] : $parts[0] * 60 + ($parts[1] ?? 0);
    }
}

