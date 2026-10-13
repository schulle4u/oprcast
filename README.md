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
---

Longer description of the podcast.
```

Create episodes as `content/my-podcast/YYYY-MM-DD-slug.md`:

```markdown
---
title: My first episode
date: 2026-10-13T18:00:00+02:00
guid: urn:uuid:replace-with-a-permanent-uuid
audio: first-episode.mp3
audio_type: audio/mpeg
audio_length: 12345678
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

Put local cover and audio files next to the Markdown files. Absolute HTTPS URLs are also supported. A GUID must never change after publication.

Static pages such as an imprint can be placed in `content/pages/imprint.md`; their front matter only needs a `title`.

Supported front matter values are strings, integers, booleans, and simple lists. Supported Markdown includes headings, paragraphs, ordered and unordered lists, links, emphasis, blockquotes, inline code, and fenced code blocks. Raw HTML is escaped intentionally.

## Validate and test

```sh
php bin/oprcast validate
php tests/run.php
```

The validator reports missing metadata, missing local media, duplicate GUIDs or audio paths, invalid durations, and malformed chapter order.

## Published routes

- `/` – series overview
- `/{series}/` – series and episodes
- `/{series}/{episode}/` – episode page
- `/{series}/feed.xml` – podcast RSS feed
- `/{series}/feed.atom` – Atom feed
- `/{series}/feed.json` – JSON Feed
- `/{series}/{episode}/chapters.json` – Podcasting 2.0 chapters
- `/pages/{slug}/` – static page
