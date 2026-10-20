<?php

declare(strict_types=1);

namespace Oprcast;

use DateTimeInterface;
use XMLWriter;

/** Serializes normalized podcast records as RSS, Atom, JSON and chapter feeds. */
final class FeedBuilder
{
    /** @param array<string, mixed> $config Feed limits and site metadata. */
    public function __construct(private readonly array $config, private readonly Markdown $markdown)
    {
    }

    /**
     * Builds an RSS 2.0 podcast feed for one series and media format.
     *
     * Omitting the media format creates the canonical feed alias and selects the
     * series default while still advertising all alternatives.
     *
     * @param array<string, mixed> $series
     * @param list<array<string, mixed>> $episodes
     */
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
            $this->writeEpisodeImage($xml, $repository, $series, $episode, $baseUrl);
            $this->writeTranscript($xml, $repository, $episode, $baseUrl);
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
        $homePage = $repository->homePage();
        $this->element($xml, 'description', (string) ($homePage['description'] ?? $this->markdown->text($homePage['body'] ?? '')));
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
            $this->writeEpisodeImage($xml, $repository, $series, $episode, $baseUrl);
            $this->writeTranscript($xml, $repository, $episode, $baseUrl);
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

    /**
     * Builds an Atom feed for one series, including all available enclosures.
     *
     * @param array<string, mixed> $series
     * @param list<array<string, mixed>> $episodes
     */
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

    /**
     * Builds a JSON Feed 1.1 document for one series.
     *
     * @param array<string, mixed> $series
     * @param list<array<string, mixed>> $episodes
     */
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
            if ($image = $repository->episodeImageUrl($series, $episode, $baseUrl)) {
                $item['image'] = $image;
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

    /**
     * Builds a Podcasting 2.0 chapters document for an episode.
     *
     * @param array<string, mixed> $episode
     */
    public function chapters(array $episode): string
    {
        return json_encode(['version' => '1.2.0', 'chapters' => $episode['chapters']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /** Creates an indented in-memory UTF-8 XML writer. */
    private function writer(): XMLWriter
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        return $xml;
    }

    /** Publishes the same artwork used by the episode page and link previews. */
    private function writeEpisodeImage(XMLWriter $xml, ContentRepository $repository, array $series, array $episode, string $baseUrl): void
    {
        if ($image = $repository->episodeImageUrl($series, $episode, $baseUrl)) {
            $xml->startElement('itunes:image');
            $xml->writeAttribute('href', $image);
            $xml->endElement();
        }
    }

    /** Advertises the companion file in both series and publisher RSS. */
    private function writeTranscript(XMLWriter $xml, ContentRepository $repository, array $episode, string $baseUrl): void
    {
        if ($transcript = $repository->transcript($episode, $baseUrl)) {
            $xml->startElement('podcast:transcript');
            $xml->writeAttribute('url', $transcript['url']);
            $xml->writeAttribute('type', $transcript['type']);
            $xml->endElement();
        }
    }

    /** Writes a complete text-only XML element. */
    private function element(XMLWriter $xml, string $name, string $value): void
    {
        $xml->startElement($name);
        $xml->text($value);
        $xml->endElement();
    }

    /** Writes an Atom link, optionally including the resource length. */
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

    /**
     * Writes the primary RSS enclosure for an episode.
     *
     * @param array{url: string, mime_type: string, length: int} $enclosure
     */
    private function writeEnclosure(XMLWriter $xml, array $enclosure): void
    {
        $xml->startElement('enclosure');
        $xml->writeAttribute('url', $enclosure['url']);
        $xml->writeAttribute('length', (string) $enclosure['length']);
        $xml->writeAttribute('type', $enclosure['mime_type']);
        $xml->endElement();
    }

    /**
     * Advertises each media variant using the Podcasting 2.0 namespace.
     *
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

    /** Joins a public base URL and relative path without duplicate separators. */
    private function url(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /** Converts a loosely typed metadata value to an XML boolean literal. */
    private function bool(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 'true' : 'false';
    }

    /**
     * Returns the newest modification timestamp within one series feed.
     *
     * @param array<string, mixed> $series
     * @param list<array<string, mixed>> $episodes
     */
    private function lastModified(array $series, array $episodes): \DateTimeImmutable
    {
        $modified = (int) $series['modified'];
        foreach ($episodes as $episode) {
            $modified = max($modified, (int) $episode['modified']);
        }
        return new \DateTimeImmutable('@' . $modified);
    }

    /**
     * Returns the newest timestamp represented in the site-wide feed.
     *
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

    /** Converts an MM:SS or HH:MM:SS duration to seconds for JSON Feed. */
    private function durationSeconds(string $duration): int
    {
        $parts = array_map('intval', explode(':', $duration));
        return count($parts) === 3 ? $parts[0] * 3600 + $parts[1] * 60 + $parts[2] : $parts[0] * 60 + ($parts[1] ?? 0);
    }
}

