<?php

declare(strict_types=1);

use Oprcast\ContentRepository;
use Oprcast\FeedBuilder;
use Oprcast\FrontMatter;
use Oprcast\Markdown;
use Oprcast\Validator;
use Oprcast\Application;
use Oprcast\Localization;

require dirname(__DIR__) . '/system/bootstrap.php';

$failures = [];
$test = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$frontMatter = new FrontMatter();
$parsed = $frontMatter->parse("---\ntitle: Test\nexplicit: false\ncategories:\n  - Technology\n---\nHello");
$test($parsed['meta']['title'] === 'Test', 'Front matter parses strings.');
$test($parsed['meta']['explicit'] === false, 'Front matter parses booleans.');
$test($parsed['meta']['categories'] === ['Technology'], 'Front matter parses lists.');

$german = new Localization('de', dirname(__DIR__) . '/system/lang');
$markdown = new Markdown($german->get('chapters_keyword'));
$rendered = $markdown->render("## Heading\n\nHello **world**.\n\n<script>alert(1)</script>");
$test(str_contains($rendered, '<h2 id="heading">Heading</h2>'), 'Markdown renders headings.');
$test(str_contains($rendered, '<strong>world</strong>'), 'Markdown renders strong text.');
$test(!str_contains($rendered, '<script>'), 'Markdown escapes raw HTML.');
$chapters = $markdown->chapters("## Kapitel\n\n- 00:00 Intro\n- 01:02 Thema");
$test(count($chapters) === 2 && $chapters[1]['startTime'] === 62, 'Markdown extracts chapters.');
$english = new Localization('en', dirname(__DIR__) . '/system/lang');
$englishMarkdown = new Markdown($english->get('chapters_keyword'));
$englishChapters = $englishMarkdown->chapters("## Chapters\n\n- 00:00 Intro\n- 01:02 Topic");
$test(count($englishChapters) === 2 && $englishChapters[1]['startTime'] === 62, 'Markdown extracts localized chapters.');
$test($englishMarkdown->chapters("## Kapitel\n\n- 00:00 Intro") === [], 'Markdown only recognizes the configured chapter keyword.');

$test($german->languageCode() === 'de-DE', 'Localization provides the HTML language code.');
foreach ([
    ['en', 'de', 'en'],
    ['EN-us,en;q=0.9,de;q=0.8', 'de', 'en'],
    ['de;q=0.4,en;q=0.9', 'de', 'en'],
    ['fr-CA, en-GB;q=0.8, de;q=0.5', 'de', 'en'],
    ['de;q=0.8,en;q=0.8', 'en', 'de'],
    ['', 'en', 'en'],
    ['fr,es;q=0.8', 'de', 'de'],
    ['en;q=0', 'de', 'de'],
    ['en;q=1.5,de;q=0.5', 'en', 'de'],
    ['en;q=invalid', 'de', 'de'],
    ['../../en', 'de', 'de'],
    ['*', 'de', 'de'],
    ['en;q=1,*;q=0.5', 'de', 'en'],
] as [$header, $fallback, $expected]) {
    $test(
        Localization::browserLanguage($header, $fallback, dirname(__DIR__) . '/system/lang') === $expected,
        "Browser language selection handles '{$header}' with fallback '{$fallback}'.",
    );
}

// Exercise request language selection through the real bootstrap and page renderer.
$savedServer = $_SERVER;
$siteConfig = require dirname(__DIR__) . '/system/config/site.php';
$fallbackLocalization = new Localization($siteConfig['language'], dirname(__DIR__) . '/system/lang');
foreach (['en-US,en;q=0.9' => 'en', 'de-DE,de;q=0.9' => 'de-DE', 'fr-FR' => $fallbackLocalization->languageCode(), '' => $fallbackLocalization->languageCode()] as $header => $expected) {
    $_SERVER = [
        'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/missing/route/',
        'SCRIPT_NAME' => '/index.php', 'HTTP_HOST' => 'example.test',
        'HTTP_ACCEPT_LANGUAGE' => $header,
    ];
    $browserApplication = require dirname(__DIR__) . '/system/bootstrap.php';
    ob_start();
    $browserApplication->run();
    $browserPage = (string) ob_get_clean();
    $test(str_contains($browserPage, '<html lang="' . $expected . '">'), 'Bootstrap selects the browser language or configured fallback for error pages.');
    $test(
        count($markdown->chapters('## ' . $fallbackLocalization->get('chapters_keyword') . "\n\n- 00:00 Intro")) === 1,
        'Browser language changes preserve chapter recognition in the configured content language.',
    );
    $test(
        $config['language_code'] === $fallbackLocalization->languageCode(),
        'Browser language changes preserve configured feed metadata.',
    );
}
$_SERVER = $savedServer;
http_response_code(200);
$test($german->get('cover_alt', ['title' => 'Test']) === 'Cover von Test', 'Localization replaces named placeholders.');
$test(
    $german->formatEpisodeDate(new DateTimeImmutable('2025-01-01T10:05:00+01:00')) === '01.01.2025, 10:05 Uhr',
    'Localization formats episode dates and times.',
);

$fixture = sys_get_temp_dir() . '/oprcast-test-' . bin2hex(random_bytes(4));
mkdir($fixture . '/lang', 0777, true);
file_put_contents($fixture . '/lang/en.php', '<?php return [];');
file_put_contents($fixture . '/lang/en_GB.php', '<?php return [];');
$test(Localization::browserLanguage('en-GB,en;q=0.8', 'de', $fixture . '/lang') === 'en_GB', 'Browser selection prefers an installed regional catalogue over its base language.');
$test(Localization::browserLanguage('en-US', 'de', $fixture . '/lang') === 'en', 'Browser selection falls back from a missing regional catalogue to its installed base language.');
mkdir($fixture . '/test-cast', 0777, true);
file_put_contents($fixture . '/test-cast/cover.jpg', 'fixture');
file_put_contents($fixture . '/test-cast/episode.mp3', 'fixture-audio');
file_put_contents($fixture . '/test-cast/episode.m4a', 'fixture-audio-m4a');
file_put_contents($fixture . '/test-cast/index.md', <<<'MD'
---
title: Test Cast
description: A test podcast
language: en-US
author: Test Author
image: cover.jpg
category: Technology
explicit: false
type: episodic
formats:
  - mp3
  - m4a
default_format: mp3
---
About this podcast.
MD);
file_put_contents($fixture . '/test-cast/2025-01-01-first.md', <<<'MD'
---
title: First episode
date: 2025-01-01T10:00:00+00:00
guid: urn:uuid:420111d4-ff3a-425d-a921-2ab9a9054dd6
audio: episode
duration: 01:02
episode: 1
episode_type: full
explicit: false
---
Episode notes.

## Kapitel

- 00:00 Intro
- 00:30 Topic
MD);

$mediaFormats = [
    'mp3' => ['extension' => '.mp3', 'mime_type' => 'audio/mpeg'],
    'm4a' => ['extension' => '.m4a', 'mime_type' => 'audio/x-m4a'],
];
$repository = new ContentRepository($fixture, $frontMatter, $markdown, $mediaFormats);
$series = $repository->seriesBySlug('test-cast');
$episodes = $repository->episodes('test-cast');
$test($series['title'] === 'Test Cast', 'Repository loads a series.');
$test(count($episodes) === 1 && $episodes[0]['date'] instanceof DateTimeImmutable, 'Repository loads and normalizes episodes.');
$allEpisodes = $repository->allEpisodes();
$test(count($allEpisodes) === 1 && $allEpisodes[0]['series_title'] === 'Test Cast', 'Repository loads episodes across all series.');
$test((new Validator())->validate($repository) === [], 'Validator accepts a complete fixture.');
$episodeEnclosures = $repository->enclosures($series, $episodes[0], 'https://example.test');
$test(count($episodeEnclosures) === 2, 'Repository resolves every configured media format.');
$test($episodeEnclosures[0]['url'] === 'https://example.test/content/test-cast/episode.mp3', 'Repository builds media URLs from the audio stem.');
$test($episodeEnclosures[0]['length'] === 13, 'Repository reads local media lengths.');
$test($repository->defaultFormat($series) === 'mp3', 'Repository resolves the default media format.');

$cdnRepository = new ContentRepository($fixture, $frontMatter, $markdown, $mediaFormats, 'https://cdn.podcast.org/');
$cdnEpisode = $episodes[0];
$cdnEpisode['audio'] = 'mypodcast/first episode';
$cdnEpisode['audio_length_mp3'] = 123;
$cdnEpisode['audio_length_m4a'] = 124;
$test(
    $cdnRepository->enclosure($series, $cdnEpisode, 'mp3', 'https://example.test')['url'] === 'https://cdn.podcast.org/mypodcast/first%20episode.mp3',
    'Repository resolves generated media paths against the configured CDN URL.',
);
$cdnEpisode['audio'] = 'https://media.example.org/episode';
$test(
    $cdnRepository->enclosure($series, $cdnEpisode, 'm4a', 'https://example.test')['url'] === 'https://media.example.org/episode.m4a',
    'Repository preserves generated absolute media URLs when a CDN URL is configured.',
);

unlink($fixture . '/test-cast/episode.mp3');
$missingMediaErrors = (new Validator())->validate($repository);
$test(count(array_filter($missingMediaErrors, static fn (string $error): bool => str_contains($error, 'episode.mp3'))) === 1, 'Validator rejects a missing local format.');
$cdnFixtureSource = file_get_contents($fixture . '/test-cast/2025-01-01-first.md');
file_put_contents($fixture . '/test-cast/2025-01-01-first.md', str_replace('audio: episode', "audio: episode\naudio_length_mp3: 13", (string) $cdnFixtureSource));
$test((new Validator())->validate($cdnRepository) === [], 'Validator accepts explicit lengths for CDN media not available locally.');
file_put_contents($fixture . '/test-cast/2025-01-01-first.md', $cdnFixtureSource);
file_put_contents($fixture . '/test-cast/episode.mp3', 'fixture-audio');

if (class_exists(XMLWriter::class)) {
    $config = ['language' => 'en-US', 'feed_limit' => 100];
    $feeds = new FeedBuilder($config, $markdown);
    $rss = $feeds->rss($series, $episodes, 'https://example.test', $repository);
    $m4aRss = $feeds->rss($series, $episodes, 'https://example.test', $repository, 'm4a');
    $metaRss = $feeds->metaRss([$series], $allEpisodes, 'https://example.test', $repository);
    $atom = $feeds->atom($series, $episodes, 'https://example.test', $repository);
    $json = json_decode($feeds->json($series, $episodes, 'https://example.test', $repository), true, flags: JSON_THROW_ON_ERROR);
    $cdnRss = $feeds->rss($series, $episodes, 'https://example.test', $cdnRepository);
    $chaptersJson = json_decode($feeds->chapters($episodes[0]), true, flags: JSON_THROW_ON_ERROR);
    $test(simplexml_load_string($rss) !== false, 'RSS is well-formed XML.');
    $test(str_contains($rss, '<enclosure url="https://example.test/content/test-cast/episode.mp3" length="13" type="audio/mpeg"'), 'Default RSS uses the default enclosure.');
    $test(substr_count($rss, '<podcast:alternateEnclosure') === 2, 'RSS exposes all formats as alternate enclosures.');
    $test(str_contains($m4aRss, '<enclosure url="https://example.test/content/test-cast/episode.m4a"'), 'Format-specific RSS selects its enclosure.');
    $test(str_contains($m4aRss, 'href="https://example.test/test-cast/feed.m4a.xml"'), 'Format-specific RSS has its own self URL.');
    $test(simplexml_load_string($metaRss) !== false, 'Meta RSS is well-formed XML.');
    $test(str_contains($metaRss, '<source url="https://example.test/test-cast/feed.xml">Test Cast</source>'), 'Meta RSS identifies the source series.');
    $test(simplexml_load_string($atom) !== false, 'Atom is well-formed XML.');
    $test(str_contains($rss, '<podcast:chapters'), 'RSS links Podcasting 2.0 chapters.');
    $test(str_contains($cdnRss, 'url="https://cdn.podcast.org/episode.mp3"'), 'RSS uses the configured CDN URL for generated relative enclosures.');
    $test($json['version'] === 'https://jsonfeed.org/version/1.1', 'JSON Feed uses version 1.1.');
    $test(count($json['items'][0]['attachments']) === 2, 'JSON Feed includes every media format.');
    $test(substr_count($atom, 'rel="enclosure"') === 2, 'Atom includes every media format.');
    $test($chaptersJson['chapters'][1]['startTime'] === 30, 'Chapter feed contains timestamps.');

    $appConfig = [
        'title' => 'Test Site',
        'language' => 'en',
        'language_code' => 'en',
        'timezone' => 'UTC',
        'base_url' => 'https://example.test',
        'cdn_url' => null,
        'content_path' => $fixture,
        'feed_limit' => 100,
        'development' => true,
    ];
    $english = new Localization('en', dirname(__DIR__) . '/system/lang');
    $application = new Application($appConfig, $repository, $markdown, $feeds, $english);
    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'HTTP_HOST' => 'example.test'];
    ob_start();
    $application->run();
    $homePage = (string) ob_get_clean();
    $test(str_contains($homePage, '<h2 id="episodes-title">Episodes of all podcasts</h2>'), 'Home page lists all episodes by default when show_home_episodes is absent.');
    $test(str_contains($homePage, 'https://example.test/test-cast/first/'), 'Home page links to episodes.');
    $test(str_contains($homePage, '<h1>Test Site</h1>'), 'Missing home.md falls back to the configured site title.');

    foreach ([true, false] as $showHomeEpisodes) {
        $configuredApplication = new Application(array_replace($appConfig, ['show_home_episodes' => $showHomeEpisodes]), $repository, $markdown, $feeds, $english);
        $_SERVER['REQUEST_URI'] = '/';
        ob_start();
        $configuredApplication->run();
        $configuredHomePage = (string) ob_get_clean();
        $test(str_contains($configuredHomePage, 'id="episodes-title"') === $showHomeEpisodes, 'Homepage episode heading follows show_home_episodes.');
        $test(str_contains($configuredHomePage, 'https://example.test/test-cast/first/') === $showHomeEpisodes, 'Homepage episode links follow show_home_episodes.');
        $test(str_contains($configuredHomePage, 'id="series-title"') && str_contains($configuredHomePage, '<h1>Test Site</h1>'), 'Homepage introduction and series remain visible for either episode setting.');
        foreach ([
            '/feed.xml' => '<source url="https://example.test/test-cast/feed.xml">Test Cast</source>',
            '/test-cast/' => 'https://example.test/test-cast/first/',
            '/test-cast/first/' => '<h1>First episode</h1>',
        ] as $path => $expectedContent) {
            $_SERVER['REQUEST_URI'] = $path;
            ob_start();
            $configuredApplication->run();
            $configuredBody = (string) ob_get_clean();
            $test(http_response_code() === 200 && str_contains($configuredBody, $expectedContent), 'Publisher RSS, series and episode pages remain available for either homepage episode setting.');
        }
    }

    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test-cast/first/', 'SCRIPT_NAME' => '/index.php', 'HTTP_HOST' => 'example.test'];
    ob_start();
    $application->run();
    $episodePage = (string) ob_get_clean();
    $test(str_contains($episodePage, '<h1>First episode</h1>'), 'Application routes episode pages.');
    $test(str_contains($episodePage, '<html lang="en">'), 'Application uses the localized HTML language code.');
    $test(str_contains($episodePage, 'January 1, 2025 at 10:00 am'), 'Application displays localized episode dates and times.');
    $test(str_contains($episodePage, 'Your browser does not support the audio player.'), 'Application renders localized layout strings.');
    $test(substr_count($episodePage, '<source src=') === 2, 'Episode pages offer every media format to the browser.');
    $test(str_contains($episodePage, '>MP3</a>') && str_contains($episodePage, '>M4A</a>'), 'Episode pages link every format for download.');

    $_SERVER['REQUEST_URI'] = '/test-cast/feed.xml';
    ob_start();
    $application->run();
    $routedRss = (string) ob_get_clean();
    $test(str_contains($routedRss, '<title>Test Cast</title>'), 'Application routes RSS feeds.');

    $_SERVER['REQUEST_URI'] = '/test-cast/feed.m4a.xml';
    ob_start();
    $application->run();
    $routedM4aRss = (string) ob_get_clean();
    $test(str_contains($routedM4aRss, 'episode.m4a'), 'Application routes format-specific RSS feeds.');

    $_SERVER['REQUEST_URI'] = '/feed.xml';
    ob_start();
    $application->run();
    $routedMetaRss = (string) ob_get_clean();
    $test(str_contains($routedMetaRss, '<source url="https://example.test/test-cast/feed.xml">Test Cast</source>'), 'Application routes the meta RSS feed.');

    // Exercise HTTP semantics without a web server; preserve and check caller buffers.
    $request = static function (string $path, array $headers = [], string $method = 'GET') use (&$application, $test): array {
        $_SERVER = array_replace([
            'REQUEST_METHOD' => $method, 'REQUEST_URI' => $path,
            'SCRIPT_NAME' => '/index.php', 'HTTP_HOST' => 'example.test',
        ], $headers);
        http_response_code(200);
        $level = ob_get_level();
        ob_start();
        $application->run();
        $test(ob_get_level() === $level + 1, 'Requests restore the caller output buffer level.');
        $body = (string) ob_get_clean();
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return [http_response_code(), $body];
    };
    [$status, $body] = $request('/test-cast/feed.xml');
    $etag = '"' . hash('sha256', $body) . '"';
    foreach ([$etag, 'W/' . $etag, '"other", W/' . $etag, '*'] as $condition) {
        [$status, $body] = $request('/test-cast/feed.xml', ['HTTP_IF_NONE_MATCH' => $condition]);
        $test($status === 304 && $body === '', 'Matching strong, weak, list and wildcard ETags return empty 304 responses.');
    }
    $futureSince = gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT';
    [$status, $body] = $request('/test-cast/feed.xml', [
        'HTTP_IF_NONE_MATCH' => '"stale"', 'HTTP_IF_MODIFIED_SINCE' => $futureSince,
    ]);
    $test($status === 200 && str_contains($body, '<rss'), 'A stale ETag takes precedence over If-Modified-Since.');
    [$status, $body] = $request('/test-cast/feed.xml', ['HTTP_IF_MODIFIED_SINCE' => $futureSince]);
    $test($status === 200 && str_contains($body, '<rss'), 'Date-only validators cannot suppress changed representations.');
    [$status, $body] = $request('/test-cast/feed.xml', [], 'HEAD');
    $test($status === 200 && $body === '', 'HEAD does not emit a feed body.');

    $episodeFile = $fixture . '/test-cast/2025-01-01-first.md';
    unlink($episodeFile);
    [$status, $body] = $request('/test-cast/feed.xml', [
        'HTTP_IF_NONE_MATCH' => $etag, 'HTTP_IF_MODIFIED_SINCE' => $futureSince,
    ]);
    $test($status === 200 && !str_contains($body, '<item>'), 'Deleting an episode invalidates the cached feed.');
    file_put_contents($episodeFile, $cdnFixtureSource);

    $seriesFile = $fixture . '/test-cast/index.md';
    $seriesSource = (string) file_get_contents($seriesFile);
    file_put_contents($seriesFile, str_replace('title: Test Cast', "title:\n  - Invalid title", $seriesSource));
    $test((new Validator())->validate($repository) !== [], 'Invalid series titles are rejected before sorting or rendering.');
    file_put_contents($seriesFile, $seriesSource);

    $mtime = filemtime($episodeFile);
    $futureDate = (new DateTimeImmutable('+1 day'))->format(DateTimeInterface::ATOM);
    file_put_contents($episodeFile, str_replace('2025-01-01T10:00:00+00:00', $futureDate, $cdnFixtureSource));
    touch($episodeFile, $mtime);
    [$status, $body] = $request('/test-cast/feed.xml');
    $unpublishedEtag = '"' . hash('sha256', $body) . '"';
    $test(!str_contains($body, '<item>'), 'Future episodes remain unpublished.');
    file_put_contents($episodeFile, $cdnFixtureSource);
    touch($episodeFile, $mtime);
    [$status, $body] = $request('/test-cast/feed.xml', [
        'HTTP_IF_NONE_MATCH' => $unpublishedEtag, 'HTTP_IF_MODIFIED_SINCE' => $futureSince,
    ]);
    $test($status === 200 && str_contains($body, '<item>'), 'Publication changes invalidate feeds even when file mtimes stay unchanged.');

    foreach ([
        'title: First episode' => "title:\n  - Invalid title",
        'guid: urn:uuid:420111d4-ff3a-425d-a921-2ab9a9054dd6' => "guid:\n  - Invalid GUID",
        'explicit: false' => 'explicit: maybe',
        'episode: 1' => 'episode: -1',
    ] as $original => $invalid) {
        file_put_contents($episodeFile, str_replace($original, $invalid, $cdnFixtureSource));
        $errors = (new Validator())->validate($repository);
        $test(count($errors) > 0 && str_contains(implode('\n', $errors), 'invalid type or value'), 'Validator rejects metadata types before rendering.');
    }
    foreach (['2025-02-30', '2025-02-29', '2025-01-01T24:00:00Z', '2025-01-01T10:60:00Z', 'tomorrow', 'false', 'null', "\n  - 2025-01-01"] as $invalidDate) {
        file_put_contents($episodeFile, str_replace('date: 2025-01-01T10:00:00+00:00', 'date: ' . $invalidDate, $cdnFixtureSource));
        $errors = (new Validator())->validate($repository);
        $test(str_contains(implode('\n', $errors), 'invalid or missing publication date'), 'Invalid calendar dates, times, relative dates and date types are rejected.');
    }
    foreach (['2024-02-29', '2025-01-01T10:00:00Z', '2025-01-01T10:00:00+02:00'] as $validDate) {
        file_put_contents($episodeFile, str_replace('2025-01-01T10:00:00+00:00', $validDate, $cdnFixtureSource));
        $test((new Validator())->validate($repository) === [], 'Valid date-only and timezone-qualified dates are accepted.');
    }
    file_put_contents($episodeFile, str_replace("date: 2025-01-01T10:00:00+00:00\n", '', $cdnFixtureSource));
    $test($repository->episodes('test-cast')[0]['date']->format('Y-m-d') === '2025-01-01', 'Missing explicit dates still use the filename date.');
    file_put_contents($episodeFile, $cdnFixtureSource);

    $previousLog = ini_get('error_log');
    ini_set('error_log', $fixture . '/errors.log');
    mkdir($fixture . '/pages');
    file_put_contents($fixture . '/pages/home.md', "---\ntitle: Welcome & hello\ndescription: Independent & personal\n---\nOur **podcasts**.\n\n[About us](/pages/valid/)\n\n<script>alert(1)</script>");
    [$status, $body] = $request('/');
    $test($status === 200 && str_contains($body, '<h1>Welcome &amp; hello</h1>') && str_contains($body, '<strong>podcasts</strong>'), 'Homepage renders home.md title and Markdown.');
    $test(strpos($body, '<strong>podcasts</strong>') < strpos($body, 'id="series-title"'), 'Homepage content appears above the series list.');
    $test(str_contains($body, '<a href="/pages/valid/">About us</a>') && !str_contains($body, '<script>'), 'Homepage content uses the safe Markdown renderer.');
    $test(!str_contains($body, '/pages/home/'), 'Home content is excluded from navigation.');
    $test(str_contains($body, '<meta name="description" content="Independent &amp; personal">'), 'Homepage front matter supplies the HTML meta description.');
    [$status, $body] = $request('/feed.xml');
    $test(str_contains($body, '<description>Independent &amp; personal</description>'), 'Homepage description supplies the publisher feed description.');
    file_put_contents($fixture . '/pages/home.md', 'A **personal** introduction.');
    [$status, $body] = $request('/');
    $test($status === 200 && str_contains($body, '<strong>personal</strong>') && str_contains($body, 'content="A personal introduction."'), 'Home content works without front matter and provides a plain text description.');
    [$status, $body] = $request('/feed.xml');
    $test(str_contains($body, '<description>A personal introduction.</description>'), 'Publisher feed falls back to the plain text home body.');
    file_put_contents($fixture . '/pages/home.md', "---\ntitle:\n  - Invalid title\n---\nIntroduction");
    [$status, $body] = $request('/');
    $test($status === 500 && substr_count($body, '<!doctype html>') === 1, 'Invalid home content returns a complete error page.');
    $test((new Validator())->validate($repository) !== [], 'Home content is included in validation.');
    unlink($fixture . '/pages/home.md');
    file_put_contents($fixture . '/pages/broken.md', "---\ntitle: Broken\nmalformed front matter\n---\nBroken page");
    file_put_contents($fixture . '/pages/valid.md', "---\ntitle: Valid page\n---\nValid body");
    [$status, $body] = $request('/');
    $test($status === 200 && str_contains($body, 'Valid page') && !str_contains($body, '>Broken<'), 'Broken navigation pages do not disable the homepage or valid navigation.');
    [$status, $body] = $request('/pages/valid/');
    $test($status === 200 && str_contains($body, 'Valid body'), 'Valid static pages remain accessible alongside a broken page.');
    [$status, $body] = $request('/pages/broken/');
    $test($status === 500 && substr_count($body, '<!doctype html>') === 1, 'Broken static pages produce a complete error page without recursion.');
    $test((new Validator())->validate($repository) !== [], 'Content validation includes static pages.');
    file_put_contents($fixture . '/pages/broken.md', "---\ntitle:\n  - Invalid title\n---\nBroken page");
    $test(str_contains(implode('\n', (new Validator())->validate($repository)), 'invalid type or value'), 'Static page metadata types are validated.');
    foreach (['/missing/', '/test-cast/missing/', '/pages/missing/', '/test-cast/feed.flac.xml'] as $missingPath) {
        [$status, $body] = $request($missingPath);
        $test($status === 404 && substr_count($body, '<!doctype html>') === 1, 'Missing resources return complete 404 responses even with broken navigation.');
    }
    unlink($fixture . '/test-cast/episode.mp3');
    [$status, $body] = $request('/test-cast/first/');
    $test($status === 500 && !str_contains($body, '<article>') && substr_count($body, '<!doctype html>') === 1, 'Failed episode rendering discards partial HTML and returns 500.');
    [$status, $body] = $request('/test-cast/feed.xml');
    $test($status === 500, 'Missing media is a server error rather than a missing route.');
    [$status, $body] = $request('/test-cast/first/', [], 'HEAD');
    $test($status === 500 && $body === '', 'HEAD errors do not leak partially rendered output.');
    $log = (string) file_get_contents($fixture . '/errors.log');
    $test(str_contains($log, 'broken.md') && str_contains($log, 'episode.mp3'), 'Content failures are recorded in the server log.');
    $application = new Application(array_replace($appConfig, ['development' => false]), $repository, $markdown, $feeds, $english);
    [$status, $body] = $request('/test-cast/first/');
    $test($status === 500 && !str_contains($body, $fixture) && !str_contains($body, 'episode.mp3'), 'Production error pages conceal filesystem paths and internal details.');
    ini_set('error_log', $previousLog);
    file_put_contents($fixture . '/test-cast/episode.mp3', 'fixture-audio');
} else {
    $failures[] = 'XMLWriter extension is required for feed tests.';
}

$remove = static function (string $directory) use (&$remove): void {
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
        $entry->isDir() ? $remove($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($directory);
};
$remove($fixture);

if ($failures !== []) {
    fwrite(STDERR, "Test failures:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "All tests passed.\n");

