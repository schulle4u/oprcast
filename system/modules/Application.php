<?php

declare(strict_types=1);

namespace Oprcast;

use Throwable;

/**
 * Handles an HTTP request and renders the matching page or syndication feed.
 *
 * The application is deliberately framework-free: it owns routing, template
 * rendering, cache validation and the security-related response headers.
 */
final class Application
{
    /**
     * @param array<string, mixed> $config Runtime and site configuration.
     */
    public function __construct(
        private readonly array $config,
        private readonly ContentRepository $repository,
        private readonly Markdown $markdown,
        private readonly FeedBuilder $feeds,
        private readonly Localization $localization,
    ) {
    }

    /** Returns the repository used by the application, primarily for bootstrap tooling. */
    public function repository(): ContentRepository
    {
        return $this->repository;
    }

    /** Dispatches the current request and converts expected failures into error pages. */
    public function run(): void
    {
        try {
            if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
                $this->send('Method not allowed', 'text/plain; charset=utf-8', 405, ['Allow' => 'GET, HEAD']);
                return;
            }
            $this->dispatch($this->requestPath());
        } catch (ContentException $exception) {
            $this->html('error', [
                'title' => $this->localization->get('not_found_title'),
                'message' => $this->localization->get('not_found_message'),
            ], 404);
        } catch (Throwable $exception) {
            $message = $this->config['development'] ? $exception->getMessage() : $this->localization->get('server_error_message');
            $this->html('error', ['title' => $this->localization->get('server_error_title'), 'message' => $message], 500);
        }
    }

    /** Resolves a normalized request path and emits the corresponding response. */
    private function dispatch(string $path): void
    {
        if ($path === '/') {
            $this->html('home', [
                'seriesList' => $this->repository->series(),
                'episodes' => $this->repository->allEpisodes(),
            ]);
            return;
        }
        if ($path === '/feed.xml') {
            $series = $this->repository->series();
            $episodes = $this->repository->allEpisodes();
            $this->conditional(
                $this->feeds->metaRss($series, $episodes, $this->baseUrl(), $this->repository),
                'application/rss+xml; charset=utf-8',
                $this->latestModified($series, $episodes),
            );
            return;
        }
        if ($path === '/assets/theme.css') {
            $css = file_get_contents(dirname(__DIR__) . '/themes/default/style.css');
            $this->send($css === false ? '' : $css, 'text/css; charset=utf-8', 200, ['Cache-Control' => 'public, max-age=3600']);
            return;
        }
        if (preg_match('#^/pages/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $match) === 1) {
            $this->html('page', ['page' => $this->repository->page($match[1])]);
            return;
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/feed\.([a-z0-9]+)\.xml$#', $path, $match) === 1) {
            $this->feed($match[1], 'xml', $match[2]);
            return;
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/feed\.(xml|atom|json)$#', $path, $match) === 1) {
            $this->feed($match[1], $match[2]);
            return;
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-z0-9]+(?:-[a-z0-9]+)*)/chapters\.json$#', $path, $match) === 1) {
            $episode = $this->repository->episode($match[1], $match[2]);
            $this->conditional($this->feeds->chapters($episode), 'application/json+chapters; charset=utf-8', (int) $episode['modified']);
            return;
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $match) === 1) {
            $series = $this->repository->seriesBySlug($match[1]);
            $episode = $this->repository->episode($match[1], $match[2]);
            $this->html('episode', ['series' => $series, 'episode' => $episode]);
            return;
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $match) === 1) {
            $series = $this->repository->seriesBySlug($match[1]);
            $this->html('series', ['series' => $series, 'episodes' => $this->repository->episodes($match[1])]);
            return;
        }
        throw new ContentException('Route not found.');
    }

    /** Builds and conditionally serves one series feed in the requested format. */
    private function feed(string $slug, string $syndicationFormat, ?string $mediaFormat = null): void
    {
        $series = $this->repository->seriesBySlug($slug);
        $episodes = $this->repository->episodes($slug);
        $baseUrl = $this->baseUrl();
        [$body, $type] = match ($syndicationFormat) {
            'xml' => [$this->feeds->rss($series, $episodes, $baseUrl, $this->repository, $mediaFormat), 'application/rss+xml; charset=utf-8'],
            'atom' => [$this->feeds->atom($series, $episodes, $baseUrl, $this->repository), 'application/atom+xml; charset=utf-8'],
            'json' => [$this->feeds->json($series, $episodes, $baseUrl, $this->repository), 'application/feed+json; charset=utf-8'],
        };
        $modified = (int) $series['modified'];
        foreach ($episodes as $episode) {
            $modified = max($modified, (int) $episode['modified']);
        }
        $this->conditional($body, $type, $modified);
    }

    /**
     * Renders a content template inside the common page layout.
     *
     * @param array<string, mixed> $data Variables exposed to both templates.
     */
    private function html(string $template, array $data, int $status = 200): void
    {
        $data['config'] = $this->config;
        $data['baseUrl'] = $this->baseUrl();
        $data['pages'] = $this->repository->pages();
        $data['repository'] = $this->repository;
        $data['markdown'] = $this->markdown;
        $data['localization'] = $this->localization;
        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__) . '/layouts/' . $template . '.php';
        $content = (string) ob_get_clean();
        ob_start();
        require dirname(__DIR__) . '/layouts/base.php';
        $this->send((string) ob_get_clean(), 'text/html; charset=utf-8', $status, [
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' https: data:; media-src 'self' https:; style-src 'self'; base-uri 'self'; frame-ancestors 'self'",
        ]);
    }

    /**
     * Finds the newest modification time across series and episode records.
     *
     * @param list<array<string, mixed>> $series
     * @param list<array<string, mixed>> $episodes
     */
    private function latestModified(array $series, array $episodes): int
    {
        $modified = 0;
        foreach (array_merge($series, $episodes) as $item) {
            $modified = max($modified, (int) ($item['modified'] ?? 0));
        }
        return $modified;
    }

    /** Sends a cacheable response, honoring ETag and If-Modified-Since validators. */
    private function conditional(string $body, string $type, int $modified): void
    {
        $etag = '"' . hash('sha256', $body) . '"';
        $notModified = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag;
        if (!$notModified && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            $notModified = $since !== false && $since >= $modified;
        }
        $headers = [
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $modified) . ' GMT',
            'Cache-Control' => 'public, max-age=300',
        ];
        $this->send($notModified ? '' : $body, $type, $notModified ? 304 : 200, $headers);
    }

    /**
     * Emits an HTTP response and suppresses its body for HEAD and 304 responses.
     *
     * @param array<string, string> $headers Additional response headers.
     */
    private function send(string $body, string $type, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: ' . $type);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD' && $status !== 304) {
            echo $body;
        }
    }

    /** Returns the URL-decoded request path relative to the installation directory. */
    private function requestPath(): string
    {
        $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/.');
        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        return '/' . ltrim($path, '/');
    }

    /** Resolves the configured public base URL or derives one from the request. */
    private function baseUrl(): string
    {
        if (is_string($this->config['base_url']) && $this->config['base_url'] !== '') {
            return rtrim($this->config['base_url'], '/');
        }
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/.');
        return $scheme . '://' . ($host ?: 'localhost') . $basePath;
    }
}

