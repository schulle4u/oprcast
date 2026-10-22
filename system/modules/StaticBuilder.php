<?php

declare(strict_types=1);

namespace Oprcast;

use Throwable;

/** Optional export of public representations; never copies the content tree. */
final class StaticBuilder
{
    private const MANIFEST = '.oprcast-build.json';

    /** Returns the number of generated public files. */
    public function build(Application $application, string $directory): int
    {
        $application = $application->forBuild();
        $repository = $application->repository();
        $errors = (new Validator())->validate($repository);
        if ($errors !== []) {
            throw new ContentException("Content validation failed:\n- " . implode("\n- ", $errors));
        }
        if (trim($directory) === '') {
            throw new ContentException('Build directory must not be empty.');
        }
        $this->rejectLinks($directory);
        $root = $this->resolveDirectory($directory);
        $project = dirname(__DIR__, 2);
        if ($this->within($project, $root)) {
            throw new ContentException('Build directory must not contain application sources.');
        }
        foreach ([$repository->contentRoot(), $project . '/system', $project . '/bin', $project . '/tests', $project . '/.git'] as $protected) {
            $protected = realpath($protected) ?: $protected;
            if ($this->within($root, $protected)) {
                throw new ContentException('Build directory overlaps application sources or is inside the content directory.');
            }
        }
        $this->mkdir($root);
        $this->rejectLinks($root . '/.oprcast-build.lock');
        $lock = fopen($root . '/.oprcast-build.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new ContentException('Cannot acquire the build lock.');
        }
        $stage = $root . '/.oprcast-stage-' . bin2hex(random_bytes(8));
        $published = [];
        $backups = [];
        $cleanup = true;
        try {
            $this->mkdir($stage . '/new');
            $manifestPath = $root . '/' . self::MANIFEST;
            $this->rejectLinks($manifestPath);
            $previous = is_file($manifestPath)
                ? json_decode($this->read($manifestPath), true, 512, JSON_THROW_ON_ERROR) : [];
            if (!is_array($previous)) {
                throw new ContentException('Invalid build manifest.');
            }
            foreach ($previous as $path => $hash) {
                if (!is_string($path) || !$this->exportPath($path) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                    throw new ContentException('Invalid build manifest entry.');
                }
            }
            $next = [];
            foreach ($this->routes($repository) as $route) {
                $path = str_ends_with($route, '/') ? ltrim($route, '/') . 'index.html' : ltrim($route, '/');
                $response = $application->render($route);
                if ($response->status !== 200 || isset($next[$path])) {
                    throw new ContentException('Cannot export route: ' . $route);
                }
                $this->write($stage . '/new/' . $path, $response->body);
                $next[$path] = hash('sha256', $response->body);
            }
            $serverConfig = $this->serverConfig();
            $this->write($stage . '/new/.htaccess', $serverConfig);
            $next['.htaccess'] = hash('sha256', $serverConfig);
            // Check every destination before changing the previous build.
            foreach (array_unique([...array_keys($previous), ...array_keys($next)]) as $path) {
                if (!$this->exportPath($path)) {
                    throw new ContentException('Reserved export path: ' . $path);
                }
                $destination = $root . '/' . $path;
                $this->rejectLinks($destination);
                if (file_exists($destination) && (!is_file($destination) || !isset($previous[$path])
                    || hash_file('sha256', $destination) !== $previous[$path])) {
                    throw new ContentException('Refusing to replace an unmanaged or modified file: ' . $path);
                }
                $parent = dirname($destination);
                while ($parent !== $root) {
                    if (file_exists($parent) && !is_dir($parent)) {
                        throw new ContentException('Export directory is occupied by a file: ' . $parent);
                    }
                    $parent = dirname($parent);
                }
            }
            $this->write($stage . '/new/' . self::MANIFEST, json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            foreach ([...array_unique([...array_keys($previous), ...array_keys($next)]), self::MANIFEST] as $path) {
                $destination = $root . '/' . $path;
                if (is_file($destination)) {
                    $backup = $stage . '/old/' . $path;
                    $this->mkdir(dirname($backup));
                    $this->move($destination, $backup);
                    $backups[$destination] = $backup;
                }
                if (is_file($stage . '/new/' . $path)) {
                    $this->mkdir(dirname($destination));
                    $this->move($stage . '/new/' . $path, $destination);
                    $published[] = $destination;
                }
            }
            return count($next);
        } catch (Throwable $exception) {
            // Keep backups for recovery if the filesystem also prevents rollback.
            $cleanup = false;
            foreach (array_reverse($published) as $path) {
                unlink($path);
            }
            foreach (array_reverse($backups, true) as $destination => $backup) {
                $this->move($backup, $destination);
            }
            $cleanup = true;
            throw $exception;
        } finally {
            if ($cleanup) {
                $this->removeStage($stage);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return \Generator<int, string> */
    private function routes(ContentRepository $repository): \Generator
    {
        yield '/';
        yield '/feed.xml';
        yield '/assets/theme.css';
        yield '/assets/player.js';
        foreach ($repository->pages() as $page) {
            yield '/pages/' . $page['slug'] . '/';
        }
        foreach ($repository->series() as $series) {
            $slug = $series['slug'];
            if (in_array($slug, ['content', 'assets', 'pages', 'system', 'bin', 'tests'], true)) {
                throw new ContentException('Reserved series slug for static builds: ' . $slug);
            }
            yield '/' . $slug . '/';
            foreach (['xml', 'atom', 'json'] as $format) {
                yield '/' . $slug . '/feed.' . $format;
            }
            foreach ($repository->seriesFormats($series) as $format) {
                yield '/' . $slug . '/feed.' . $format . '.xml';
            }
            foreach ($repository->episodes($slug) as $episode) {
                $route = '/' . $slug . '/' . $episode['slug'] . '/';
                yield $route;
                if ($episode['chapters'] !== []) {
                    yield $route . 'chapters.json';
                }
                if ($transcript = $repository->transcript($episode, '')) {
                    yield $route . 'transcript.' . $transcript['extension'];
                }
            }
        }
    }

    private function exportPath(string $path): bool
    {
        if (str_starts_with($path, 'content/')) {
            return false;
        }
        return preg_match('#^(?:\.htaccess|index\.html|feed\.xml|assets/(?:theme\.css|player\.js)|pages/[a-z0-9]+(?:-[a-z0-9]+)*/index\.html|[a-z0-9]+(?:-[a-z0-9]+)*/(?:index\.html|feed\.(?:xml|atom|json|[a-z0-9]+\.xml)|[a-z0-9]+(?:-[a-z0-9]+)*/(?:index\.html|chapters\.json|transcript\.(?:txt|vtt))))$#D', $path) === 1;
    }

    private function within(string $path, string $directory): bool
    {
        $path = str_replace('\\', '/', $path);
        $directory = rtrim(str_replace('\\', '/', $directory), '/');
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $directory = strtolower($directory);
        }
        return $path === $directory || str_starts_with($path, $directory . '/');
    }

    private function rejectLinks(string $path): void
    {
        while (true) {
            if (is_link($path)) {
                throw new ContentException('Build paths must not contain symbolic links: ' . $path);
            }
            $parent = dirname($path);
            if ($parent === $path) {
                break;
            }
            $path = $parent;
        }
    }

    /** Resolves a not-yet-created destination before allowing any filesystem changes. */
    private function resolveDirectory(string $directory): string
    {
        $parts = [];
        while (!file_exists($directory)) {
            $parent = dirname($directory);
            if ($parent === $directory) {
                throw new ContentException('Cannot resolve build directory.');
            }
            $parts[] = basename($directory);
            $directory = $parent;
        }
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            throw new ContentException('Build path is not a directory.');
        }
        foreach (array_reverse($parts) as $part) {
            $root = match ($part) {
                '.' => $root,
                '..' => dirname($root),
                default => $root . '/' . $part,
            };
        }
        return $root;
    }

    private function mkdir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new ContentException('Cannot create directory: ' . $path);
        }
    }

    private function read(string $path): string
    {
        $body = file_get_contents($path);
        if ($body === false) {
            throw new ContentException('Cannot read: ' . $path);
        }
        return $body;
    }

    private function write(string $path, string $body): void
    {
        $this->mkdir(dirname($path));
        if (file_put_contents($path, $body) !== strlen($body)) {
            throw new ContentException('Cannot write: ' . $path);
        }
    }

    private function move(string $source, string $destination): void
    {
        if (!rename($source, $destination)) {
            throw new ContentException('Cannot publish or restore: ' . $destination);
        }
    }

    private function removeStage(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
            $entry->isDir() && !$entry->isLink() ? $this->removeStage($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }

    private function serverConfig(): string
    {
        return <<<'APACHE'
Options -Indexes
DirectoryIndex index.html

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule (^|/)\.(?!well-known/) - [F,L]
</IfModule>

# This directory is a static export. Never route requests to PHP.
<FilesMatch "(?i:\.(?:md|php|phtml|phar)$)|^\.">
    Require all denied
</FilesMatch>
AddType application/rss+xml .xml
AddType application/atom+xml .atom
AddType application/json .json
AddType text/vtt .vtt
AddCharset UTF-8 .html .css .js .xml .atom .json .txt .vtt
<Files "feed.json">
    ForceType application/feed+json
</Files>
<Files "chapters.json">
    ForceType application/json+chapters
</Files>
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; img-src 'self' https: data:; media-src 'self' https:; style-src 'self'; base-uri 'self'; frame-ancestors 'self'"
    <FilesMatch "\.(?:xml|atom|json)$">
        Header set Cache-Control "public, max-age=300"
    </FilesMatch>
</IfModule>

APACHE;
    }
}
