<?php

declare(strict_types=1);

use Oprcast\Application;
use Oprcast\ContentException;
use Oprcast\ContentRepository;
use Oprcast\FeedBuilder;
use Oprcast\FrontMatter;
use Oprcast\Localization;
use Oprcast\Markdown;
use Oprcast\StaticBuilder;

require dirname(__DIR__) . '/system/bootstrap.php';

$fixture = sys_get_temp_dir() . '/oprcast-static-' . bin2hex(random_bytes(8));
$public = $fixture . '/public';
$content = $public . '/content';
mkdir($content . '/cast', 0755, true);
mkdir($content . '/pages');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$reject = static function (callable $action, string $message) use ($assert): void {
    try {
        $action();
    } catch (ContentException) {
        return;
    }
    $assert(false, $message);
};
$remove = static function (string $path) use (&$remove): void {
    if (!is_dir($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        $remove($entry->getPathname());
    }
    rmdir($path);
};
try {
    file_put_contents($content . '/cast/index.md', "---\ntitle: Cast\ndescription: Test cast\nlanguage: de\nauthor: Host\nimage: cover.jpg\ncategory: Technology\nexplicit: false\nformats:\n  - mp3\n  - m4a\ndefault_format: mp3\n---\nSeries notes.");
    $episode = "---\ntitle: First\ndate: 2025-01-01\nguid: first-guid\naudio: first\nduration: 01:00\nepisode_type: full\ntranscript: words.vtt\n---\nNotes.\n\n## Kapitel\n\n- 00:00 Intro\n- 00:30 End\n";
    $episodeFile = $content . '/cast/2025-01-01-first.md';
    file_put_contents($episodeFile, $episode);
    file_put_contents($content . '/cast/first.mp3', 'audio-mp3');
    file_put_contents($content . '/cast/first.m4a', 'audio-m4a');
    file_put_contents($content . '/cast/cover.jpg', 'cover');
    $transcript = "WEBVTT\n\n00:00.000 --> 00:01.000\nHello.\n";
    file_put_contents($content . '/cast/words.vtt', $transcript);
    file_put_contents($content . '/cast/2025-01-02-draft.md', "---\ntitle: Draft\ndraft: true\nguid: draft-guid\n---\nHidden.");
    file_put_contents($content . '/cast/2999-01-01-future.md', "---\ntitle: Future\ndate: 2999-01-01\nguid: future-guid\naudio: https://media.example.org/future\naudio_length_mp3: 123\naudio_length_m4a: 456\nduration: 01:00\nepisode_type: full\n---\nScheduled.");
    file_put_contents($content . '/pages/home.md', "---\ntitle: Welcome\n---\nIntro.");
    file_put_contents($content . '/pages/about.md', "---\ntitle: About\n---\nAbout us.");
    $config = array_replace(require dirname(__DIR__) . '/system/config/site.php', [
        'base_url' => 'https://example.test/podcasts', 'language' => 'de', 'content_path' => $content,
    ]);
    $formats = require dirname(__DIR__) . '/system/config/media.php';
    $markdown = new Markdown('Kapitel');
    $repository = new ContentRepository($content, new FrontMatter(), $markdown, $formats);
    $makeApp = static fn (array $settings): Application => new Application($settings, $repository, $markdown,
        new FeedBuilder($settings, $markdown), new Localization('en', dirname(__DIR__) . '/system/lang'));
    $application = $makeApp($config);
    $builder = new StaticBuilder();
    // Builds must ignore HTTP request headers and render in the configured language.
    $_SERVER['REQUEST_METHOD'] = 'HEAD';
    $_SERVER['HTTP_IF_NONE_MATCH'] = '*';
    $builder->build($application, $public);
    $paths = ['index.html', 'feed.xml', 'assets/theme.css', 'assets/player.js', 'pages/home/index.html',
        'pages/about/index.html', 'cast/index.html', 'cast/feed.xml', 'cast/feed.mp3.xml', 'cast/feed.m4a.xml',
        'cast/feed.atom', 'cast/feed.json', 'cast/first/index.html', 'cast/first/chapters.json', 'cast/first/transcript.vtt'];
    foreach ($paths as $path) {
        $assert(is_file($public . '/' . $path), 'Missing export: ' . $path);
        $route = '/' . preg_replace('#index\.html$#', '', $path);
        $assert(file_get_contents($public . '/' . $path) === $application->forBuild()->render($route)->body,
            'Static representation differs from shared rendering: ' . $path);
    }
    $assert(file_get_contents($public . '/cast/first/transcript.vtt') === $transcript, 'Transcript bytes changed.');
    $html = file_get_contents($public . '/cast/first/index.html');
    $languageCode = (new Localization('de', dirname(__DIR__) . '/system/lang'))->languageCode();
    $assert(str_contains($html, '<html lang="' . $languageCode . '">') && str_contains($html, 'https://example.test/podcasts/content/cast/first.mp3'), 'Language or subdirectory media URL is wrong.');
    $assert(!is_dir($public . '/cast/draft') && !is_dir($public . '/cast/future'), 'Unpublished episode exported.');
    $assert(!str_contains(file_get_contents($public . '/feed.xml'), 'future-guid'), 'Scheduled episode leaked into feed.');
    $assert(str_contains(file_get_contents($public . '/.htaccess'), 'DirectoryIndex index.html'), 'Static server configuration missing.');
    $assert(!file_exists($public . '/index.php') && !is_dir($public . '/system'), 'Application sources were copied.');
    $builder->build($application, $public);
    $before = file_get_contents($public . '/feed.xml');
    $lock = fopen($public . '/.oprcast-build.lock', 'c');
    flock($lock, LOCK_EX);
    $reject(fn () => $builder->build($application, $public), 'Concurrent build lock ignored.');
    flock($lock, LOCK_UN);
    fclose($lock);
    $reject(fn () => $builder->build($makeApp(array_replace($config, ['base_url' => null])), $public), 'Missing base_url accepted.');
    foreach ([$content, $content . '/nested', dirname(__DIR__), dirname(__DIR__) . '/system/export'] as $unsafe) {
        $reject(fn () => $builder->build($application, $unsafe), 'Unsafe build destination accepted: ' . $unsafe);
    }
    $assert(!file_exists(dirname(__DIR__) . '/system/export'), 'Rejected output directory was created.');
    $cutoff = $repository->atTime(new DateTimeImmutable('2024-01-01'));
    $assert($cutoff->episodes('cast') === [] && count($repository->episodes('cast')) === 1, 'Build cutoff affected live publication.');
    // Unmanaged files, local media and source Markdown survive repeated builds and pruning.
    file_put_contents($public . '/robots.txt', 'Custom');
    file_put_contents($public . '/cast/first/extra.txt', 'Keep');
    file_put_contents($content . '/pages/about.md', '---' . "\nbroken front matter\n---");
    $reject(fn () => $builder->build($application, $public), 'Invalid content accepted.');
    $assert(file_get_contents($public . '/feed.xml') === $before, 'Failed validation modified published files.');
    unlink($content . '/pages/about.md');
    unlink($episodeFile);
    $builder->build($application, $public);
    foreach (['cast/first/index.html', 'cast/first/chapters.json', 'cast/first/transcript.vtt', 'pages/about/index.html'] as $stale) {
        $assert(!file_exists($public . '/' . $stale), 'Stale export survived: ' . $stale);
    }
    $assert(file_get_contents($public . '/robots.txt') === 'Custom' && file_get_contents($public . '/cast/first/extra.txt') === 'Keep', 'Unmanaged files removed.');
    $assert(file_get_contents($content . '/cast/first.mp3') === 'audio-mp3' && is_file($content . '/cast/index.md'), 'Content was changed.');
    file_put_contents($episodeFile, $episode);
    file_put_contents($public . '/cast/first/index.html', 'Custom page');
    $reject(fn () => $builder->build($application, $public), 'Unmanaged destination overwritten.');
    $assert(file_get_contents($public . '/cast/first/index.html') === 'Custom page', 'Conflict was modified.');
    unlink($public . '/cast/first/index.html');
    $builder->build($application, $public);
    file_put_contents($public . '/index.html', 'Edited export');
    $reject(fn () => $builder->build($application, $public), 'Modified export overwritten.');
    $assert(file_get_contents($public . '/index.html') === 'Edited export', 'Edited export was modified.');
    // Restore the recorded bytes, then verify that a manifest cannot prune arbitrary files.
    file_put_contents($public . '/index.html', $application->forBuild()->render('/')->body);
    $manifest = file_get_contents($public . '/.oprcast-build.json');
    file_put_contents($public . '/.oprcast-build.json', json_encode(['content/cast/index.md' => hash_file('sha256', $content . '/cast/index.md')]));
    $reject(fn () => $builder->build($application, $public), 'Unsafe manifest accepted.');
    file_put_contents($public . '/.oprcast-build.json', $manifest);
    if (PHP_OS_FAMILY !== 'Windows') {
        mkdir($fixture . '/outside');
        symlink($fixture . '/outside', $public . '/linked');
        $reject(fn () => $builder->build($application, $public . '/linked'), 'Symlink destination accepted.');
        unlink($public . '/linked');
        unlink($public . '/cast/first/index.html');
        symlink($public . '/robots.txt', $public . '/cast/first/index.html');
        $reject(fn () => $builder->build($application, $public), 'Symlink file accepted.');
    }
    fwrite(STDOUT, "All static build tests passed.\n");
} finally {
    $remove($fixture);
}
