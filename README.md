# OPRCast
OPRCast is a minimal, flatfile podcast publishing CMS for PHP webspaces. 

## Features

- Organise your podcasts into files and folders. There is no klunky admin panel to get in your way.
- One content folder for each series containing audio files, covers and metadata in Markdown format. 
- Extensive metadata support for iTunes and other podcast directories.
- Output options include RSS 2.0, Atom, JSON Feed 1.1 and Podcasting 2.0 chapter files.
- Multiple audio formats for each podcast, ranging from standard MP3 and M4A to lossless formats. Define your own if you need more.
- Support for absolute audio URLs and a CDN (content delivery network) to host your audio files outside your webspace.
- Browse and listen to all the available podcasts on a small website.
- Display podcast transcripts and jump directly to specific chapters.
- Add static pages with additional content for your podcasts, such as ways to get in touch.
- A command-line tool for validating podcasts and creating new episode files.

## Requirements

- PHP 8.3 or newer
- PHP extensions: XML/XMLWriter, mbstring, and fileinfo
- Apache 2.4 with `mod_rewrite` for production, or PHP's built-in server for development

No Composer packages, database, or JavaScript runtime are required in production.
Episode pages optionally use a small bundled browser script for chapter seeking, copying timestamp links, playback speed, skip buttons and locally remembered listening positions; playback, show notes and transcripts remain available without JavaScript.

## Quick start

Using a text editor, open the file `system/config/site.php` and configure the base URL for your podcasts (e.g. `https://podcasts.example.org`). Then, upload all the files to your webspace and open the website in your browser.

You can also start php's internal web server for local development: 

```sh
php -S localhost:8000 router.php
```

Open `http://localhost:8000` to access the site on your computer. 

## Content

See the complete metadata reference in [English](docs/metadata.md) or [German](docs/metadata-de.md) for every supported series and episode field, including optional metadata, defaults, media handling, and feed output.

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

Episode notes with **Markdown**.

## Chapters

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

To customize the introduction above the homepage's series list, create `content/pages/home.md`:

```markdown
---
title: Welcome to our podcasts
description: Independent podcasts about the things we care about.
---

Discover our **podcasts**, meet the hosts, and find your next episode.
```

The title and Markdown body replace the default introduction. Without this file, the homepage displays the configured site title. `home.md` is omitted from navigation, while remaining accessible at `/pages/home/`. Its optional `description` supplies the site's HTML meta description and publisher RSS description; if omitted, the Markdown body is converted to plain text. Without `home.md`, these descriptions are empty. Homepage content is also displayed when no series have been published yet.

Supported front matter values are strings, integers, booleans, and simple lists. Static pages (including the homepage introduction), series descriptions, and episode show notes use [Parsedown 1.8.0](https://github.com/erusev/parsedown/releases/tag/1.8.0). Supported Markdown includes ATX and Setext headings, nested ordered and unordered lists, tables, images, inline and reference links, emphasis, strikethrough, blockquotes, horizontal rules, hard line breaks, inline code, and indented or fenced code blocks with optional language hints. Headings retain fragment IDs; ATX headings require a space after `#`. Raw HTML is escaped and unsafe link schemes are filtered using Parsedown's safe mode. Short front matter `description` fields remain plain text.

Parsedown is bundled with its MIT license in `system/vendor/parsedown/`; its PHP class is unmodified and needs no Composer installation. See the README in that directory for update instructions.

Metadata fields are type-checked when loaded: text fields must be strings, `explicit` must be a boolean, and episode/season numbers and audio lengths must be positive integers. Publication dates accept `YYYY-MM-DD` or `YYYY-MM-DDTHH:MM:SS` followed by `Z` or an offset such as `+02:00`. Invalid calendar dates and relative expressions are rejected. If `date` is omitted, the date in the episode filename is used.

Feeds and chapter files use content-based ETags for conditional requests. Modification-date headers are deliberately omitted because filesystem timestamps cannot reliably represent deletions or scheduled publication. Invalid content is logged to PHP's error log and returns HTTP 500; missing resources return 404. Broken static pages are omitted from navigation so other pages remain available.

## Drafts and the episode CLI

```sh
php bin/oprcast new my-podcast my-next-episode
```

Creates `content/my-podcast/YYYY-MM-DD-my-next-episode.md` with the current date in the configured time zone, a permanent UUID v4 GUID, an audio stem and `draft: true`. The series must already exist. Existing episode slugs and files are never overwritten. Edit the title and show notes, add audio in the series formats and a `duration`, then run validation. Set `draft: false` (or remove it) and validate again before publishing; a future `date` still schedules publication.

Drafts never appear on public pages or in feeds, and their episode, chapter and transcript routes return 404. Validation checks their metadata types, date, slug and GUID uniqueness, while allowing missing publication fields and media. Draft status is not access control for media files: files uploaded to a public content directory or CDN remain accessible by their direct URLs.

## Transcripts and chapter links

Add an optional local companion file to an episode's front matter:

```yaml
transcript: my-next-episode.vtt
```

UTF-8 `.txt` and WebVTT `.vtt` files are supported, relative to the episode's directory, including subdirectories. Transcripts stay local even when audio/images use a CDN; remote transcript URLs are not supported. The episode page displays readable text and a download link. WebVTT timings and markup are omitted from the readable version, while the original timed file is preserved for download. Series RSS (including format variants) and publisher RSS advertise the file using `podcast:transcript`.

Existing Markdown chapter timestamps become links on episode pages, for example `/{series}/{episode}/?t=195`. With JavaScript enabled, clicking a timestamp seeks within the player and updates the URL. Each timestamp also has a copy-link button; if clipboard access is unavailable, use the timestamp link's context menu. Opening a timestamp link seeks after audio metadata loads, without starting playback automatically. Show notes in feeds remain unchanged.

Episode pages also provide links to the immediately previous and next published episode in the same series below the show notes and chapters. Links follow publication dates, as in the series index; drafts and scheduled episodes are excluded. At either end only the available direction is shown, and a single-episode series has no navigation block. This works without JavaScript.

## Player comfort controls

With JavaScript enabled, episode pages offer buttons to skip back 15 seconds or forward 30 seconds, plus a speed selector (0.75×, 1×, 1.25×, 1.5×, 2×). These supplement the native audio player and work with keyboard navigation. Speed changes made through the browser's native controls are reflected in the selector. Skip buttons become available when the audio metadata is ready and stay within the audio duration.

After playback starts, OPRCast remembers the listening position per episode GUID in this browser's local storage. Writes are limited to five-second intervals in the audio timeline, with exact positions saved when pausing, seeking, leaving or backgrounding the page. Revisiting an episode restores that position after metadata loads, without autoplay; an explicit valid `?t=` timestamp takes precedence, including `?t=0`. Completed episodes clear their saved position. Positions at or beyond the final second are discarded on restore.

Positions remain on this device in the same browser and website origin; they are not sent to the server or synchronized. Clearing the site's browser data removes them. If storage is blocked or unavailable, the playback controls continue to work without remembering positions. Without JavaScript, only the native player's controls are shown.

## Localization

The CMS language is selected on each request from the browser's `Accept-Language` header. Supported languages are tried in preference order (including `q` weights); regional variants such as `en-US` fall back to the base language `en`. Missing, invalid, or unsupported browser preferences fall back to the `language` setting in `system/config/site.php`. Its value is the filename below `system/lang` without the `.php` extension; for example, `de` loads `system/lang/de.php`. German and English translations are included. HTML responses declare `Vary: Accept-Language` for caches.

The configured language continues to determine feed metadata and the Markdown chapter heading, independently of the browser language.

Language files are PHP arrays containing simple string key-value pairs. In addition to the visible layout strings and ARIA labels, each file defines the language code for the HTML `lang` attribute, the date and time formats used for episodes, and the level-two Markdown heading that introduces chapter timestamps (`Kapitel` or `Chapters`). Publisher content is maintained in `content/pages/home.md`, independently of the browser language. Copy an existing file to add another CMS language and keep all keys intact.

## Validate and test

```sh
php bin/oprcast validate
php tests/run.php
node tests/player.mjs
```

The validator reports missing metadata, missing local media, duplicate GUIDs or audio paths, invalid durations, and malformed chapter order.
Node.js is only needed to run the optional player tests, not to serve OPRCast.

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
- `/{series}/{episode}/transcript.txt` or `transcript.vtt` – the configured transcript's original file
- `/pages/{slug}/` – static page
