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

$markdown = new Markdown();
$rendered = $markdown->render("## Heading\n\nHello **world**.\n\n<script>alert(1)</script>");
$test(str_contains($rendered, '<h2 id="heading">Heading</h2>'), 'Markdown renders headings.');
$test(str_contains($rendered, '<strong>world</strong>'), 'Markdown renders strong text.');
$test(!str_contains($rendered, '<script>'), 'Markdown escapes raw HTML.');
$chapters = $markdown->chapters("## Kapitel\n\n- 00:00 Intro\n- 01:02 Thema");
$test(count($chapters) === 2 && $chapters[1]['startTime'] === 62, 'Markdown extracts chapters.');

$german = new Localization('de', dirname(__DIR__) . '/system/lang');
$test($german->languageCode() === 'de-DE', 'Localization provides the HTML language code.');
$test($german->get('cover_alt', ['title' => 'Test']) === 'Cover von Test', 'Localization replaces named placeholders.');
$test(
    $german->formatEpisodeDate(new DateTimeImmutable('2025-01-01T10:05:00+01:00')) === '01.01.2025, 10:05 Uhr',
    'Localization formats episode dates and times.',
);

$fixture = sys_get_temp_dir() . '/oprcast-test-' . bin2hex(random_bytes(4));
mkdir($fixture . '/test-cast', 0777, true);
file_put_contents($fixture . '/test-cast/cover.jpg', 'fixture');
file_put_contents($fixture . '/test-cast/episode.mp3', 'fixture-audio');
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
---
About this podcast.
MD);
file_put_contents($fixture . '/test-cast/2025-01-01-first.md', <<<'MD'
---
title: First episode
date: 2025-01-01T10:00:00+00:00
guid: urn:uuid:420111d4-ff3a-425d-a921-2ab9a9054dd6
audio: episode.mp3
audio_type: audio/mpeg
audio_length: 13
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

$repository = new ContentRepository($fixture, $frontMatter, $markdown);
$series = $repository->seriesBySlug('test-cast');
$episodes = $repository->episodes('test-cast');
$test($series['title'] === 'Test Cast', 'Repository loads a series.');
$test(count($episodes) === 1 && $episodes[0]['date'] instanceof DateTimeImmutable, 'Repository loads and normalizes episodes.');
$allEpisodes = $repository->allEpisodes();
$test(count($allEpisodes) === 1 && $allEpisodes[0]['series_title'] === 'Test Cast', 'Repository loads episodes across all series.');
$test((new Validator())->validate($repository) === [], 'Validator accepts a complete fixture.');
$test(
    $repository->mediaUrl($episodes[0], 'audio', 'https://example.test') === 'https://example.test/content/test-cast/episode.mp3',
    'Repository resolves relative media against the local content directory.',
);

$cdnRepository = new ContentRepository($fixture, $frontMatter, $markdown, 'https://cdn.podcast.org/');
$cdnEpisode = $episodes[0];
$cdnEpisode['audio'] = 'mypodcast/first episode.mp3';
$test(
    $cdnRepository->mediaUrl($cdnEpisode, 'audio', 'https://example.test') === 'https://cdn.podcast.org/mypodcast/first%20episode.mp3',
    'Repository resolves relative media against the configured CDN URL.',
);
$cdnEpisode['audio'] = 'https://media.example.org/episode.mp3';
$test(
    $cdnRepository->mediaUrl($cdnEpisode, 'audio', 'https://example.test') === 'https://media.example.org/episode.mp3',
    'Repository preserves absolute media URLs when a CDN URL is configured.',
);

unlink($fixture . '/test-cast/episode.mp3');
$test((new Validator())->validate($cdnRepository) === [], 'Validator does not require CDN-backed media to exist locally.');
file_put_contents($fixture . '/test-cast/episode.mp3', 'fixture-audio');

if (class_exists(XMLWriter::class)) {
    $config = ['language' => 'en-US', 'feed_limit' => 100];
    $feeds = new FeedBuilder($config, $markdown);
    $rss = $feeds->rss($series, $episodes, 'https://example.test', $repository);
    $metaRss = $feeds->metaRss([$series], $allEpisodes, 'https://example.test', $repository);
    $atom = $feeds->atom($series, $episodes, 'https://example.test', $repository);
    $json = json_decode($feeds->json($series, $episodes, 'https://example.test', $repository), true, flags: JSON_THROW_ON_ERROR);
    $cdnRss = $feeds->rss($series, $episodes, 'https://example.test', $cdnRepository);
    $chaptersJson = json_decode($feeds->chapters($episodes[0]), true, flags: JSON_THROW_ON_ERROR);
    $test(simplexml_load_string($rss) !== false, 'RSS is well-formed XML.');
    $test(simplexml_load_string($metaRss) !== false, 'Meta RSS is well-formed XML.');
    $test(str_contains($metaRss, '<source url="https://example.test/test-cast/feed.xml">Test Cast</source>'), 'Meta RSS identifies the source series.');
    $test(simplexml_load_string($atom) !== false, 'Atom is well-formed XML.');
    $test(str_contains($rss, '<podcast:chapters'), 'RSS links Podcasting 2.0 chapters.');
    $test(str_contains($cdnRss, 'url="https://cdn.podcast.org/episode.mp3"'), 'RSS uses the configured CDN URL for relative enclosures.');
    $test($json['version'] === 'https://jsonfeed.org/version/1.1', 'JSON Feed uses version 1.1.');
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
    $test(str_contains($homePage, '<h2 id="episodes-title">All episodes</h2>'), 'Home page lists all episodes.');
    $test(str_contains($homePage, 'https://example.test/test-cast/first/'), 'Home page links to episodes.');

    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test-cast/first/', 'SCRIPT_NAME' => '/index.php', 'HTTP_HOST' => 'example.test'];
    ob_start();
    $application->run();
    $episodePage = (string) ob_get_clean();
    $test(str_contains($episodePage, '<h1>First episode</h1>'), 'Application routes episode pages.');
    $test(str_contains($episodePage, '<html lang="en">'), 'Application uses the localized HTML language code.');
    $test(str_contains($episodePage, 'January 1, 2025 at 10:00 am'), 'Application displays localized episode dates and times.');
    $test(str_contains($episodePage, 'Your browser does not support the audio player.'), 'Application renders localized layout strings.');

    $_SERVER['REQUEST_URI'] = '/test-cast/feed.xml';
    ob_start();
    $application->run();
    $routedRss = (string) ob_get_clean();
    $test(str_contains($routedRss, '<title>Test Cast</title>'), 'Application routes RSS feeds.');

    $_SERVER['REQUEST_URI'] = '/feed.xml';
    ob_start();
    $application->run();
    $routedMetaRss = (string) ob_get_clean();
    $test(str_contains($routedMetaRss, '<source url="https://example.test/test-cast/feed.xml">Test Cast</source>'), 'Application routes the meta RSS feed.');
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

