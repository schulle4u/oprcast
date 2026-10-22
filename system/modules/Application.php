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
    private bool $building = false;

    /** Uses the configured public URL and language, independently of HTTP request state. */
    public function forBuild(): self
    {
        $url = $this->config['base_url'] ?? null;
        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            || parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
            throw new ContentException('Static builds require an absolute HTTP(S) base_url without query or fragment.');
        }
        $application = new self($this->config, $this->repository->atTime(new \DateTimeImmutable('now')), $this->markdown, $this->feeds,
            new Localization((string) $this->config['language'], dirname(__DIR__) . '/lang'));
        $application->building = true;
        return $application;
    }

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
                $this->send('Method not allowed', 'text/plain; charset=utf-8', 405, ['Allow' => 'GET, HEAD'])->emit();
                return;
            }
            $this->render($this->requestPath())->emit();
        } catch (NotFoundException $exception) {
            $this->html('error', [
                'title' => $this->localization->get('not_found_title'),
                'message' => $this->localization->get('not_found_message'),
            ], 404)->emit();
        } catch (Throwable $exception) {
            error_log((string) $exception);
            $message = $this->config['development'] ? $exception->getMessage() : $this->localization->get('server_error_message');
            $this->html('error', ['title' => $this->localization->get('server_error_title'), 'message' => $message], 500)->emit();
        }
    }

    /** Renders a normalized path without emitting headers or swallowing build errors. */
    public function render(string $path): Response
    {
        if ($path === '/') {
            return $this->html('home', [
                'homePage' => $this->repository->homePage(),
                'seriesList' => $this->repository->series(),
                'episodes' => ($this->config['show_home_episodes'] ?? true) ? $this->repository->allEpisodes() : [],
            ]);
        }
        if ($path === '/feed.xml') {
            $series = $this->repository->series();
            $episodes = $this->repository->allEpisodes();
            return $this->conditional(
                $this->feeds->metaRss($series, $episodes, $this->baseUrl(), $this->repository),
                'application/rss+xml; charset=utf-8',
            );
        }
        if ($path === '/assets/theme.css') {
            $css = file_get_contents(dirname(__DIR__) . '/themes/default/style.css');
            return $this->send($css === false ? '' : $css, 'text/css; charset=utf-8', 200, ['Cache-Control' => 'public, max-age=3600']);
        }
        if ($path === '/assets/player.js') {
            $script = file_get_contents(dirname(__DIR__) . '/themes/default/player.js');
            return $this->send($script === false ? '' : $script, 'text/javascript; charset=utf-8', 200, ['Cache-Control' => 'public, max-age=3600']);
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-z0-9]+(?:-[a-z0-9]+)*)/transcript\.(txt|vtt)$#', $path, $match) === 1) {
            $episode = $this->repository->episode($match[1], $match[2]);
            $transcript = $this->repository->transcript($episode, $this->baseUrl());
            if ($transcript === null || $transcript['extension'] !== $match[3]) {
                throw new NotFoundException('Transcript not found.');
            }
            return $this->conditional($transcript['content'], $transcript['type'] . '; charset=utf-8');
        }
        if (preg_match('#^/pages/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $match) === 1) {
            return $this->html('page', ['page' => $this->repository->page($match[1])]);
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/feed\.([a-z0-9]+)\.xml$#', $path, $match) === 1) {
            return $this->feed($match[1], 'xml', $match[2]);
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/feed\.(xml|atom|json)$#', $path, $match) === 1) {
            return $this->feed($match[1], $match[2]);
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-z0-9]+(?:-[a-z0-9]+)*)/chapters\.json$#', $path, $match) === 1) {
            $episode = $this->repository->episode($match[1], $match[2]);
            return $this->conditional($this->feeds->chapters($episode), 'application/json+chapters; charset=utf-8');
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $match) === 1) {
            $series = $this->repository->seriesBySlug($match[1]);
            return $this->html('episode', ['series' => $series, ...$this->repository->episodeWithNeighbors($match[1], $match[2])]);
        }
        if (preg_match('#^/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $match) === 1) {
            $series = $this->repository->seriesBySlug($match[1]);
            return $this->html('series', ['series' => $series, 'episodes' => $this->repository->episodes($match[1])]);
        }
        throw new NotFoundException('Route not found.');
    }

    /** Builds and conditionally serves one series feed in the requested format. */
    private function feed(string $slug, string $syndicationFormat, ?string $mediaFormat = null): Response
    {
        $series = $this->repository->seriesBySlug($slug);
        $episodes = $this->repository->episodes($slug);
        if ($mediaFormat !== null && !in_array($mediaFormat, $this->repository->seriesFormats($series), true)) {
            throw new NotFoundException('Media format not found.');
        }
        $baseUrl = $this->baseUrl();
        [$body, $type] = match ($syndicationFormat) {
            'xml' => [$this->feeds->rss($series, $episodes, $baseUrl, $this->repository, $mediaFormat), 'application/rss+xml; charset=utf-8'],
            'atom' => [$this->feeds->atom($series, $episodes, $baseUrl, $this->repository), 'application/atom+xml; charset=utf-8'],
            'json' => [$this->feeds->json($series, $episodes, $baseUrl, $this->repository), 'application/feed+json; charset=utf-8'],
        };
        return $this->conditional($body, $type);
    }

    /**
     * Renders a content template inside the common page layout.
     *
     * @param array<string, mixed> $data Variables exposed to both templates.
     */
    private function html(string $template, array $data, int $status = 200): Response
    {
        $data['config'] = $this->config;
        $data['baseUrl'] = $this->baseUrl();
        $data['pages'] = $template === 'error' ? [] : $this->repository->pages(true);
        $item = $data['episode'] ?? $data['series'] ?? $data['page'] ?? $data['homePage'] ?? null;
        $fallbackDescription = preg_replace('/\s+/u', ' ', $this->markdown->text($item['body'] ?? '')) ?? '';
        $data['pageDescription'] = $item['description'] ?? mb_strimwidth(trim($fallbackDescription), 0, 240, '…', 'UTF-8');
        $data['canonicalUrl'] = $status === 200 ? $data['baseUrl'] . match ($template) {
            'episode' => '/' . $data['series']['slug'] . '/' . $data['episode']['slug'] . '/',
            'series' => '/' . $data['series']['slug'] . '/',
            'page' => '/pages/' . $data['page']['slug'] . '/',
            default => '/',
        } : null;
        $data['pageImage'] = null;
        $data['pageImageAlt'] = '';
        if (isset($data['series'])) {
            $series = $data['series'];
            $episode = $data['episode'] ?? null;
            $data['pageImage'] = $episode !== null
                ? $this->repository->episodeImageUrl($series, $episode, $data['baseUrl'])
                : $this->repository->mediaUrl($series, 'image', $data['baseUrl']);
            $imageTitle = $episode !== null && $this->repository->mediaUrl($episode, 'image', $data['baseUrl']) !== null
                ? $episode['title'] : $series['title'];
            $data['pageImageAlt'] = $this->localization->get('cover_alt', ['title' => $imageTitle]);
        }
        $data['pages'] = array_values(array_filter($data['pages'], static fn (array $page): bool => $page['slug'] !== 'home'));
        $data['repository'] = $this->repository;
        $data['markdown'] = $this->markdown;
        $data['localization'] = $this->localization;
        extract($data, EXTR_SKIP);
        $bufferLevel = ob_get_level();
        try {
            ob_start();
            require dirname(__DIR__) . '/layouts/' . $template . '.php';
            $content = (string) ob_get_clean();
            ob_start();
            require dirname(__DIR__) . '/layouts/base.php';
            $body = (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
        return $this->send($body, 'text/html; charset=utf-8', $status, [
            'Vary' => 'Accept-Language',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; img-src 'self' https: data:; media-src 'self' https:; style-src 'self'; base-uri 'self'; frame-ancestors 'self'",
        ]);
    }

    /** Uses representation hashes: file mtimes cannot track deletions or scheduled publication. */
    private function conditional(string $body, string $type): Response
    {
        $etag = '"' . hash('sha256', $body) . '"';
        $notModified = false;
        $condition = $this->building ? '' : trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        // If-Modified-Since is deliberately ignored because no reliable date is available.
        if ($condition === '*') {
            $notModified = true;
        } elseif (preg_match_all('/(?:W\/)?"[^"\x00-\x20\x7f]*"/', $condition, $matches)) {
            foreach ($matches[0] as $candidate) {
                if (preg_replace('/^W\//', '', $candidate) === $etag) {
                    $notModified = true;
                    break;
                }
            }
        }
        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=300',
        ];
        return $this->send($notModified ? '' : $body, $type, $notModified ? 304 : 200, $headers);
    }

    /**
     * Emits an HTTP response and suppresses its body for HEAD and 304 responses.
     *
     * @param array<string, string> $headers Additional response headers.
     */
    private function send(string $body, string $type, int $status = 200, array $headers = []): Response
    {
        return new Response($body, $status, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            ...$headers,
        ]);
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

