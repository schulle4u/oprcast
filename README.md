# OPRCast
Flatfile podcast publisher

OPRCast is a minimal, database-free podcast publisher for PHP webspaces. Podcast series and episodes are Markdown files with a small, documented front matter format. It publishes accessible HTML plus RSS 2.0, Atom, JSON Feed 1.1, and Podcasting 2.0 chapter files.

## Requirements

- PHP 8.3 or newer
- PHP extensions: XML/XMLWriter, mbstring, and fileinfo
- Apache 2.4 with `mod_rewrite` for production, or PHP's built-in server for development

No Composer packages, database, or JavaScript runtime are required in production.

## Quick start

```sh
php -S localhost:8000 router.php
```

Open `http://localhost:8000`. For production, point the web root at this directory and set the canonical URL through the `OPRCAST_BASE_URL` environment variable.

## Content

Create `content/my-podcast/index.md`:

```markdown
---
title: My Podcast
description: A short description
language: en-US
author: Jane Doe
image: cover.jpg
category: Technology
explicit: false
type: episodic
formats:
  - mp3
  - m4a
  - ogg
default_format: mp3
---

Longer description of the podcast.
```

Create episodes as `content/my-podcast/YYYY-MM-DD-slug.md`:

```markdown
---
title: My first episode
date: 2026-10-13T18:00:00+02:00
guid: urn:uuid:replace-with-a-permanent-uuid
audio: first-episode
duration: 42:17
episode: 1
episode_type: full
explicit: false
---

Episode notes with **basic Markdown**.

## Kapitel

- 00:00 Introduction
- 03:15 Main topic
- 39:40 Closing
```

Put the cover and one audio file per configured format next to the Markdown files. In this example, the audio stem creates `first-episode.mp3`, `first-episode.m4a`, and `first-episode.ogg`. File sizes are read automatically. Format extensions and MIME types are configured centrally in `system/config/media.php`. A GUID must never change after publication.

If an audio stem is an absolute HTTP(S) URL, or a relative path served only by the configured CDN, provide the byte length for every file that is not also available locally:

```markdown
audio: https://media.example.org/first-episode
audio_length_mp3: 12345678
audio_length_m4a: 10987654
audio_length_ogg: 9876543
```

To serve media from a CDN, set `OPRCAST_CDN_URL` (or `cdn_url` in `system/config/site.php`), for example `https://cdn.podcast.org/`. Relative media paths are then resolved below that URL:

```markdown
audio: mypodcast/first-episode
```

For the MP3 format this produces `https://cdn.podcast.org/mypodcast/first-episode.mp3`. The setting applies to relative audio stems and image paths; absolute HTTP(S) URLs remain unchanged apart from the configured audio extension being appended to the stem.

Static pages such as an imprint can be placed in `content/pages/imprint.md`; their front matter only needs a `title`.

Supported front matter values are strings, integers, booleans, and simple lists. Supported Markdown includes headings, paragraphs, ordered and unordered lists, links, emphasis, blockquotes, inline code, and fenced code blocks. Raw HTML is escaped intentionally.

Metadata fields are type-checked when loaded: text fields must be strings, `explicit` must be a boolean, and episode/season numbers and audio lengths must be positive integers. Publication dates accept `YYYY-MM-DD` or `YYYY-MM-DDTHH:MM:SS` followed by `Z` or an offset such as `+02:00`. Invalid calendar dates and relative expressions are rejected. If `date` is omitted, the date in the episode filename is used.

Feeds and chapter files use content-based ETags for conditional requests. Modification-date headers are deliberately omitted because filesystem timestamps cannot reliably represent deletions or scheduled publication. Invalid content is logged to PHP's error log and returns HTTP 500; missing resources return 404. Broken static pages are omitted from navigation so other pages remain available.

## Localization

The `language` setting in `system/config/site.php` selects the CMS language. Its value is the filename below `system/lang` without the `.php` extension; for example, `de` loads `system/lang/de.php`. German and English translations are included.

Language files are PHP arrays containing simple string key-value pairs. In addition to the visible layout strings and ARIA labels, each file defines the site description, the language code for the HTML `lang` attribute, the date and time formats used for episodes, and the level-two Markdown heading that introduces chapter timestamps (`Kapitel` or `Chapters`). Copy an existing file to add another CMS language and keep all keys intact.

## Validate and test

```sh
php bin/oprcast validate
php tests/run.php
```

The validator reports missing metadata, missing local media, duplicate GUIDs or audio paths, invalid durations, and malformed chapter order.

## Published routes

- `/` – series overview
- `/feed.xml` – RSS meta feed containing episodes from all series
- `/{series}/` – series and episodes
- `/{series}/{episode}/` – episode page
- `/{series}/feed.xml` – podcast RSS feed
- `/{series}/feed.{media}.xml` – RSS feed for one configured media format, for example `feed.mp3.xml`
- `/{series}/feed.atom` – Atom feed
- `/{series}/feed.json` – JSON Feed
- `/{series}/{episode}/chapters.json` – Podcasting 2.0 chapters
- `/pages/{slug}/` – static page
