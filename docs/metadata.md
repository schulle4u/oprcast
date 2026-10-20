# Metadata for podcast publishing

[Deutsche Fassung](metadata-de.md)

This reference describes all metadata used by the current publisher for podcast series and individual shows (episodes). It is based on the implementation and content validator, particularly [Metadata.php](../system/modules/Metadata.php), [ContentRepository.php](../system/modules/ContentRepository.php), [Validator.php](../system/modules/Validator.php), and [FeedBuilder.php](../system/modules/FeedBuilder.php).

## File locations and syntax

A series is stored in `content/{series}/index.md`, and a show, for example, in `content/{series}/2026-10-14-first-show.md`. The series directory and the show filename without its date prefix provide the URL slugs: `/series/first-show/`. Slugs may contain only lowercase ASCII letters, digits, and single hyphens between those characters. The `pages` directory is reserved for static pages.

Metadata appears at the beginning of the file between two lines containing `---`. The Markdown body follows. The format uses a deliberately small YAML-like parser, rather than full YAML:

- Field names consist of letters, digits, and underscores and must begin with a letter. They are converted to lowercase. Duplicate field names are rejected.
- Strings occupy a single line; surrounding single or double quotes are supported. The parser interprets backslash escapes in quoted values.
- Unquoted whole numbers become integers; `true` and `false` become booleans. `null` and `~` are parsed as null, but are not regular values for the publishing fields documented here.
- Strings containing only digits must be quoted, for example `title: "123"`. Boolean fields such as `explicit`, however, must not be quoted.
- Lists use an empty field value followed by indented lines starting with `- `. Inline lists such as `[mp3, ogg]`, nested objects, and multiline YAML strings using `|` or `>` are not supported.
- Blank lines and full comment lines starting with `#` are allowed. Inline comments are not removed and become part of the value.

“Required” in the tables below refers to the requirements of `php bin/oprcast validate`. Some missing values receive a fallback during loading or feed generation; these cases are noted individually. This CLI command performs full content validation, while types, dates, and media resolution are also checked when content is loaded or published.

## Series: `index.md`

| Field | Type | Required / default | Meaning and usage |
| --- | --- | --- | --- |
| `title` | String | Required; derived from the directory name if the field is missing | Name on the website and in all series feeds. Derivation replaces hyphens with spaces and capitalizes the first letter. An explicitly empty title fails validation. |
| `description` | String | Required | Short description on the website; RSS `description`, `itunes:summary`, and JSON Feed `description`. If missing, only the RSS `description` falls back to the Markdown body as plain text; iTunes and JSON receive an empty string. |
| `language` | String | Required | Language code, such as `de-DE` or `en-US`; RSS `language` and JSON Feed `language`. If missing, the language code from the configured CMS language is used. The validator checks the string type, not the validity of the language code. |
| `author` | String | Required | `itunes:author` in series RSS and the Atom author name. Meta RSS uses the series author for each show. There is no corresponding author field in JSON Feed. |
| `image` | String | Required | Cover artwork as a relative media path or absolute HTTP(S) URL; used on the website, in RSS `image`, `itunes:image`, and JSON Feed `icon`. Local files are checked for existence unless a CDN is configured. Image dimensions and format are not validated. |
| `category` | String or list of strings | Required | One or more categories, such as `Technology`. Each becomes a separate, equally ranked `itunes:category` in series RSS. No nested subcategories or checks against a category catalog. |
| `explicit` | Boolean | Required; RSS fallback `false` | Explicit content flag, published as `itunes:explicit`. Provides the default for shows without their own `explicit` value. |
| `type` | String | Optional; `episodic` | `episodic` or `serial`; published as `itunes:type` in series RSS. Does not change the publisher's ordering: shows are still listed by publication date, newest first. |
| `copyright` | String | Optional; omitted if missing | Rights notice, such as `© 2026 Example Studio`; published as `copyright` in series RSS. |
| `formats` | List of strings | Required | At least one unique format name from `system/config/media.php`. Lowercase letters and digits only; no duplicates. Applies to every show in the series and determines audio files, download links, and feed enclosures. |
| `default_format` | String | Required | Must be included in `formats`. Determines the primary enclosure in `/{series}/feed.xml` and the site-wide meta RSS feed. |

The Markdown body after the front matter is the detailed series description on the series page. It is not included as full HTML content in series feeds.

Complete example containing every series field:

```markdown
---
title: Voices from the Studio
description: Conversations about technology and media.
language: en-US
author: Example Studio
image: cover.jpg
category:
  - Technology
  - News
explicit: false
type: episodic
copyright: © 2026 Example Studio
formats:
  - mp3
  - m4a
  - ogg
  - opus
  - flac
default_format: mp3
---

The detailed description with **Markdown formatting**.
```

For this example, each show must be available in all five formats. Remove any formats you do not need from the series list.

## Individual show: episode file

| Field | Type | Required / default | Meaning and usage |
| --- | --- | --- | --- |
| `title` | String | Required; derived from the show slug if the field is missing | Title on the website and in RSS, Atom, and JSON Feed. Derivation replaces hyphens with spaces and capitalizes the first letter. An explicitly empty title is invalid. |
| `date` | Date as a string | Required in the loaded record; optional in front matter if the filename has a date prefix | Publication date; see details below. Controls visibility, ordering, and the publication date in every feed. |
| `guid` | String | Required | Permanent identifier, unique across all series, such as `urn:uuid:550e8400-e29b-41d4-a716-446655440000`. Keep it unchanged after publication. RSS `guid` with `isPermaLink="false"`, Atom `id`, and JSON `id`. If missing, feeds fall back to the show URL, but the validator still reports the field as missing. UUID syntax is not enforced. |
| `audio` | String | Required | Shared filename stem without an extension for all series formats. A relative media path or absolute HTTP(S) URL; see the media section for details. |
| `duration` | String | Required | Duration as `MM:SS` or `HH:MM:SS`, such as `42:17` or `01:02:03`; the hours component may contain any number of digits. Minutes and seconds must each be 0–59, with seconds always written as two digits. RSS `itunes:duration`; converted to seconds per attachment in JSON Feed. Not extracted from the audio file. |
| `episode_type` | String | Required; RSS fallback `full` | `full`, `trailer`, or `bonus`; published as `itunes:episodeType` in RSS. |
| `description` | String | Optional; Markdown body as plain text | Short summary in show lists on the website, RSS `description`, and JSON `summary`. If missing, the entire Markdown body is converted to plain text. Does not replace the detailed show notes. |
| `episode` | Positive integer | Optional; omitted if missing | Episode number, at least `1`; RSS `itunes:episode`. No automatic numbering or sequence checks. |
| `season` | Positive integer | Optional; omitted if missing | Season number, at least `1`; RSS `itunes:season`. |
| `explicit` | Boolean | Optional; series value | Overrides the series default for this show in RSS `itunes:explicit`. A show value of `false` also overrides a series value of `true`. |
| `audio_length_<format>` | Positive integer | Conditionally required per format | File size in bytes, such as `audio_length_mp3: 12345678`. Required if the corresponding audio file is not available locally; see the media section. |

Additional optional episode fields:

| Field | Type | Meaning |
| --- | --- | --- |
| `draft` | Boolean; default `false` | `true` keeps the show unpublished regardless of its date. Must not be quoted. |
| `transcript` | String | Relative path to a local UTF-8 `.txt` or `.vtt` file; website and RSS `podcast:transcript`. |

### Publication dates and scheduling

Only `YYYY-MM-DD` or `YYYY-MM-DDTHH:MM:SS` followed by `Z` or a UTC offset such as `+02:00` is accepted. Examples:

```yaml
date: 2026-10-14
```

```yaml
date: 2026-10-14T18:00:00+02:00
```

A date without a time means midnight in the configured time zone (`timezone`, default `Europe/Berlin`). If `date` is missing, the first ten characters of the filename are read as the date. An explicit `date` takes precedence over the filename; it does not change the show slug derived from that filename. Invalid calendar dates, relative expressions, and times without a time zone are rejected.

Shows with a future date appear on the website and in feeds only once that time is reached; direct requests return 404 before then. The content validator already includes them. Additionally, `draft: true` keeps a show unpublished regardless of its date, including its chapter and transcript routes. Set `draft: false` or remove the field to publish. A future date still schedules publication.

`php bin/oprcast new my-podcast my-topic` creates a Markdown file in an existing series with the current timestamp in the configured time zone, a permanent UUID v4 GUID, a title derived from the slug, an audio stem, `episode_type: full` and `draft: true`. Existing slugs and files are never overwritten. Slugs use lowercase ASCII letters, digits and single hyphens; `index` is reserved. Add show notes, media and `duration`, remove draft status, and validate before publishing. Keep the GUID unchanged.

For drafts, validation checks metadata types, dates, duplicate slugs and any supplied GUIDs. Other publication fields, media and chapter rules are checked once draft status is removed. Directly uploaded media remain accessible at public content or CDN URLs; draft status is not access control for those files.

### Complete example

File `content/voices/2026-10-14-first-show.md`, matching the series above. The byte counts are placeholders for files actually hosted externally. The chapter heading below uses the default German CMS configuration; with `language: en` in `system/config/site.php`, use `## Chapters` instead.

```markdown
---
title: Our first conversation
description: How an idea becomes a podcast.
date: 2026-10-14T18:00:00+02:00
guid: urn:uuid:550e8400-e29b-41d4-a716-446655440000
audio: https://media.example.org/voices/first-show
audio_length_mp3: 12345678
audio_length_m4a: 10987654
audio_length_ogg: 9876543
audio_length_opus: 8765432
audio_length_flac: 45678901
duration: 42:17
episode_type: full
episode: 1
season: 1
explicit: false
---

Detailed show notes with **emphasis** and [links](https://example.org).

## Kapitel

- 00:00 Welcome
- 03:15 From idea to recording
- 39:40 Looking ahead
```

## Audio, cover artwork, and file sizes

The included central format configuration provides:

| Format name | Appended extension | MIME type | Size field for external files |
| --- | --- | --- | --- |
| `mp3` | `.mp3` | `audio/mpeg` | `audio_length_mp3` |
| `m4a` | `.m4a` | `audio/mp4` | `audio_length_m4a` |
| `ogg` | `.ogg` | `audio/ogg` | `audio_length_ogg` |
| `opus` | `.opus` | `audio/opus` | `audio_length_opus` |
| `flac` | `.flac` | `audio/flac` | `audio_length_flac` |

Additional formats can be defined in [system/config/media.php](../system/config/media.php) using `extension` and `mime_type`. The size field always uses the format name, which is not necessarily the file extension. Format definitions and byte counts are not read from ID3 tags or other embedded audio metadata.

- Without a CDN, `audio: first-show` with the `mp3` format refers to `content/{series}/first-show.mp3`; its public URL is `{base_url}/content/{series}/first-show.mp3`. Subdirectories such as `audio/first-show` are supported.
- The extension is always appended, including for absolute URLs. Specify the stem without `.mp3` or another extension. No extension is appended to `image`.
- For local audio files, the positive file size is read automatically and takes precedence over an explicit `audio_length_<format>`. Without a CDN, a size field cannot substitute for a missing or empty local audio file.
- For absolute HTTP(S) audio stems, a size must be provided for every enabled format. No remote size lookup is performed.
- With `cdn_url` or `OPRCAST_CDN_URL`, relative audio and cover paths are published below that base URL. For example, `audio: voices/first-show` and `https://cdn.example.org/` produce `https://cdn.example.org/voices/first-show.mp3`. The series directory is not added automatically.
- Even when publishing through a CDN, the size of an audio file available locally at the specified relative path beside the Markdown file is used automatically. Otherwise, the size field is required.
- Absolute HTTP(S) media URLs use neither the CDN base nor the series path. Other URL schemes and relative paths containing `..` or backslashes are forbidden. Relative paths are URL-encoded segment by segment for the public URL.
- Sizes are included in RSS `enclosure` and `podcast:alternateEnclosure`, Atom enclosure links, and JSON `size_in_bytes`. The validator reports duplicate audio URLs across all series.

## Markdown content and chapters

The show body after the front matter provides the show notes on the website, RSS `content:encoded`, Atom `content` with type `html`, and JSON `content_html`. Static pages and series bodies use the same bundled Parsedown 1.8.0 parser. Supported Markdown includes ATX and Setext headings, paragraphs, nested lists, tables, images, inline and reference links, emphasis, strikethrough, blockquotes, horizontal rules, hard line breaks, inline code, and indented or fenced code blocks with optional language hints. Headings receive fragment IDs; ATX headings require a space after `#`. Raw HTML is escaped and unsafe link schemes are filtered using safe mode. Short front matter `description` fields remain plain text.

Chapters are not a front matter list. The publisher extracts them from the first matching Markdown section with a level-two heading. Its name comes from `chapters_keyword` in the configured CMS language file: by default, `## Kapitel` with `language: de` in the **site configuration**, or `## Chapters` with `en`. Neither the series language nor the browser language changes this keyword.

Each entry starts with `-` or `*`, a timestamp, and the title, for example `- 03:15 Main topic`. Timestamps with two or three components are recognized (minutes/seconds or hours/minutes/seconds); the leading components each have one or two digits, and seconds have exactly two. The first recognized entry must start at `00:00`, and subsequent entries must be strictly increasing. Extraction ends at the next level-one or level-two heading. Nonmatching lines are skipped; the validator checks neither chapter boundaries against the audio duration nor the minute/second ranges of chapter timestamps.

Only `startTime` (seconds) and `title` are published at `/{series}/{show}/chapters.json`, with document version `1.2.0`. When chapters exist, RSS links to them through `podcast:chapters`. Without chapters, the chapter route still returns a document with an empty list. Chapter images, chapter URLs, and a front matter field for an external chapter file are not used.

## Transcripts and website chapter links

`transcript: transcripts/first-show.vtt` associates a companion file relative to the episode file. Local UTF-8 `.txt` and `.vtt` files (with a `WEBVTT` header) are supported. Absolute URLs, parent paths and files outside the series directory are rejected. CDN configuration does not apply to transcripts.

Episode pages display escaped text and a download link. WebVTT timings, cue IDs, comments and markup are removed from the readable version; speaker names are retained. The original file is served at `/{series}/{show}/transcript.txt` or `transcript.vtt`, with ETag and HEAD support. Series RSS, format RSS and meta RSS link to it using `podcast:transcript` (`text/plain` or `text/vtt`). Atom and JSON Feed do not receive additional transcript fields.

Timestamps in the Markdown chapter section become links such as `?t=195` on episode pages. With JavaScript, clicking seeks within the player and updates the URL. A button beside each timestamp copies its link; if clipboard access is unavailable, use the link's context menu. Timestamp links seek after audio metadata loads, without starting playback automatically. Without JavaScript, playback, show notes and transcripts remain usable. Feed show notes and chapter JSON remain unchanged.

## Browsing between episodes

Below the show notes and chapter list, labeled navigation links lead to the immediately previous and next published episode in the same series and include the episode title. Order follows publication dates, as in the series index. Drafts and future episodes are skipped. The oldest and newest episodes show only the available direction; a series with one published episode has no navigation block. This works without JavaScript or additional metadata.

## Web player comfort controls

With JavaScript, labeled buttons skip back 15 seconds or forward 30 seconds, and a speed selector offers 0.75×, 1×, 1.25×, 1.5× and 2×. All controls are keyboard accessible. Skip buttons wait for audio metadata and stay within the audio duration. Native browser speed changes are reflected in the selector. Without JavaScript, the additional controls stay hidden and the native player remains usable.

After playback starts, listening positions are saved per episode GUID in this browser's local storage. During playback writes use five-second buckets in the audio timeline; pausing, seeking, leaving or backgrounding the page saves the exact position. Revisiting restores the position after metadata loads, without autoplay. A valid explicit `?t=` timestamp takes precedence, including `?t=0`. Completing an episode clears its position; saved positions within the final second or beyond the duration are discarded on restore.

Positions remain in the same browser on this device and website origin; they are not sent to the server or synchronized. Clearing the website's browser data removes them. If storage is blocked or unavailable, the controls remain usable without remembering positions. No additional front matter or configuration is needed.

## Which metadata appears in each feed?

The tables above list the exact destination fields. The following differences also apply:

| Output | Contents |
| --- | --- |
| Series RSS `/{series}/feed.xml` | Series metadata and shows with iTunes fields, HTML show notes, and chapter references. Primary audio follows `default_format`; all variants are also included as `podcast:alternateEnclosure`. |
| Format RSS `/{series}/feed.{format}.xml` | Same metadata; the primary enclosure and the alternative marked as default match the requested format enabled for the series. |
| Meta RSS `/feed.xml` | Shows from all series with the same show fields as series RSS, plus the series author and `source` containing the series title and series feed URL. Channel title and language come from the site configuration or its language file; the description comes from `content/pages/home.md`; series artwork, categories, series copyright, and series type are not included as separate channel metadata. |
| Atom `/{series}/feed.atom` | Series title, series URL as ID, and series author; each show includes its title, GUID, publication/modification times, URL, HTML show notes, and all audio enclosures. No separate summaries, artwork, categories, or iTunes fields. |
| JSON Feed `/{series}/feed.json` | Series title, description, language, and artwork as `icon`; each show includes its GUID, URL, title, show notes, summary, publication date, and all audio attachments including duration in seconds. No separate author, season, episode number, copyright, or explicit fields. |

All feeds are limited to `feed_limit` entries (default `100`), newest shows first. The HTML audio player offers sources in the order of `formats`; `default_format` does not reorder that list.

Customize the introduction above the series list with `content/pages/home.md`. Its front matter can contain `title` and `description`; the Markdown body appears below the title. The file is excluded from navigation. Without the file, the configured website title is displayed.

The HTML meta description and meta RSS channel description come from `description` in `content/pages/home.md`, falling back to its Markdown body converted to plain text. Without the file, these descriptions are empty. A series or show's `description` does not replace this HTML meta tag. Homepage content remains independent of the browser language.

## Global configuration and generated values

The publisher homepage displays an HTML list of episodes from all series by default. To hide this list, set `'show_home_episodes' => false,` in `system/config/site.php`; set it to `true` to show it again. If the setting is absent, the list remains enabled. The homepage introduction and series overview, individual series and episode pages, and all RSS, Atom, and JSON feeds remain available.

The following settings in [system/config/site.php](../system/config/site.php) affect publication. They do not belong in front matter:

| Setting | Default / source | Effect |
| --- | --- | --- |
| `title` | `OPRCast` | Website title and meta RSS channel title. |
| `language` | `de` | CMS language file in `system/lang`, fallback for browser language detection, feed language fallback, and selection of the chapter heading. |
| `timezone` | `Europe/Berlin` | Time zone, particularly for dates without an offset. |
| `base_url` | `OPRCAST_BASE_URL`, otherwise derived from the request | Canonical base for page, feed, chapter, and local media URLs. Configure explicitly for production. |
| `cdn_url` | `OPRCAST_CDN_URL`, otherwise no CDN | Base URL for relative media paths. |
| `content_path` | Project directory plus `/content` | Root directory for series and static pages. |
| `show_home_episodes` | `true` | Show the HTML episode list across all series on the publisher homepage. Set to `false` to hide it. |
| `feed_limit` | `100` | Maximum entries per feed, including meta RSS. |
| `development` | `OPRCAST_DEBUG`, otherwise `false` | Controls detailed error messages; not published podcast metadata. |

Startup also adds `media_formats` from `media.php` and `language_code` from the configured language file. The website language and HTML `lang` follow browser preferences with a fallback to the configured language; feed metadata remains independent of these preferences.

The publisher generates the following record fields automatically; they are not additional editorial input fields:

- `slug`, `series_slug`: derived from the directory and filename; used to build page, feed, source, and chapter URLs.
- `body`, `html`: the Markdown content and its rendered HTML version.
- `source`, `modified`: source file and its filesystem modification time. Modification times provide Atom `updated` and RSS `lastBuildDate`; they do not replace the publication date.
- `chapters`: chapters extracted from Markdown.
- `series_title`: added from the associated series when combining all shows.
- Audio enclosures containing `format`, `url`, `mime_type`, `length`, and `default`: calculated from series formats, audio stem, and file size; `audio_file` is used internally for URL resolution.

## Support limits and validation

Unknown front matter keys are not generally rejected, but they produce no output without a corresponding implementation. Similarly, fields such as `image` or `author` on a show are accepted as strings, but the current publisher does not publish them as show artwork or a show author. There is no general inheritance of all series fields; show-specific inheritance applies to `explicit`, while formats and the default format are taken from the series.

Other podcast metadata, such as owner/contact email, subtitles, people, funding/value information, block/complete flags, or redirect URLs, is not currently implemented. Adding front matter fields alone cannot enable these features.

Before publishing, run this command from the project directory:

```sh
php bin/oprcast validate
```

Checks include required fields, field types, allowed series/show types, media formats, default format, duration, date, local series artwork and audio files, required external byte counts, duplicate show slugs within a series, duplicate GUIDs and audio URLs across all series, and chapter start/order. This does not constitute a complete check of external podcast directory requirements.
