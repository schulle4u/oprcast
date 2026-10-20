# Metadaten für Podcast-Veröffentlichungen

[English version](metadata.md)

Diese Referenz beschreibt sämtliche vom aktuellen Publisher ausgewerteten Metadaten für Podcast-Serien und einzelne Shows (Episoden). Grundlage sind die Implementierung und der Inhaltsvalidator, insbesondere [Metadata.php](../system/modules/Metadata.php), [ContentRepository.php](../system/modules/ContentRepository.php), [Validator.php](../system/modules/Validator.php) und [FeedBuilder.php](../system/modules/FeedBuilder.php).

## Ablage und Schreibweise

Eine Serie liegt in `content/{serie}/index.md`, eine Show beispielsweise in `content/{serie}/2026-10-14-erste-show.md`. Der Serienordner und der Show-Dateiname ohne Datumspräfix liefern die URL-Slugs: `/serie/erste-show/`. Slugs dürfen nur kleine ASCII-Buchstaben, Ziffern und einzelne Bindestriche zwischen diesen Zeichen enthalten. Der Ordner `pages` ist für statische Seiten reserviert.

Metadaten stehen am Dateianfang zwischen zwei Zeilen mit `---`. Danach folgt der Markdown-Text. Das Format ist ein bewusst kleiner YAML-ähnlicher Parser, kein vollständiges YAML:

- Feldnamen bestehen aus Buchstaben, Ziffern und Unterstrichen und beginnen mit einem Buchstaben. Sie werden in Kleinbuchstaben umgewandelt. Doppelte Feldnamen werden abgelehnt.
- Texte stehen auf einer Zeile; äußere einfache oder doppelte Anführungszeichen sind möglich. Bei zitierten Werten interpretiert der Parser Backslash-Escapes.
- Unzitierte ganze Zahlen werden zu Integern; `true` und `false` zu Booleans. `null` und `~` werden als Nullwert gelesen, sind für die dokumentierten Veröffentlichungsfelder aber keine regulären Angaben.
- Rein numerische Texte müssen in Anführungszeichen stehen, beispielsweise `title: "123"`. Boolean-Felder wie `explicit` dürfen dagegen nicht zitiert werden.
- Listen verwenden einen leeren Feldwert und eingerückte Zeilen mit `- `. Inline-Listen wie `[mp3, ogg]`, verschachtelte Objekte und mehrzeilige YAML-Texte mit `|` oder `>` werden nicht unterstützt.
- Leerzeilen und ganze Kommentarzeilen mit `#` sind erlaubt. Inline-Kommentare werden nicht entfernt und gehören zum Wert.

„Pflicht“ in den folgenden Tabellen bezeichnet die Anforderungen von `php bin/oprcast validate`. Einige fehlende Werte erhalten beim Laden oder im Feed einen Ersatzwert; das ist jeweils gesondert angegeben. Die vollständige Inhaltsvalidierung wird durch diesen CLI-Befehl ausgeführt, während Typen, Datumswerte und Medienauflösung auch beim Laden beziehungsweise Veröffentlichen geprüft werden.

## Serie: `index.md`

| Feld | Typ | Pflicht / Standard | Bedeutung und Verwendung |
| --- | --- | --- | --- |
| `title` | Text | Pflicht; bei fehlendem Feld aus dem Ordnernamen abgeleitet | Name auf der Website und in allen Serienfeeds. Bei Ableitung werden Bindestriche durch Leerzeichen ersetzt und der erste Buchstabe großgeschrieben. Ein explizit leerer Titel besteht die Validierung nicht. |
| `description` | Text | Pflicht | Kurze Beschreibung auf der Website; RSS-`description`, `itunes:summary` und JSON-Feed-`description`. Ohne Feld verwendet nur die RSS-`description` ersatzweise den Markdown-Text als Klartext; iTunes und JSON erhalten einen leeren Text. |
| `language` | Text | Pflicht | Sprachcode, z. B. `de-DE` oder `en-US`; RSS-`language` und JSON-Feed-`language`. Ohne Feld wird der Sprachcode aus der konfigurierten CMS-Sprache verwendet. Der Validator prüft den Texttyp, nicht die Gültigkeit des Sprachcodes. |
| `author` | Text | Pflicht | `itunes:author` im Serien-RSS und Name des Atom-Autors. Der Meta-RSS nutzt den Serienautor pro Show. Kein entsprechendes Autorenfeld im JSON Feed. |
| `image` | Text | Pflicht | Cover als relativer Medienpfad oder absolute HTTP(S)-URL; Website, RSS-`image`, `itunes:image` und JSON-Feed-`icon`. Lokale Dateien werden auf Existenz geprüft, sofern kein CDN konfiguriert ist. Bildabmessungen und Bildformat werden nicht validiert. |
| `category` | Text oder Liste von Texten | Pflicht | Eine oder mehrere Kategorien, z. B. `Technology`. Jede wird als eigenes, gleichrangiges `itunes:category` im Serien-RSS ausgegeben. Keine verschachtelten Unterkategorien; kein Abgleich mit einem Kategorienkatalog. |
| `explicit` | Boolean | Pflicht; RSS-Ersatzwert `false` | Kennzeichnung expliziter Inhalte als `itunes:explicit`. Dient als Vorgabe für Shows ohne eigenes `explicit`. |
| `type` | Text | Optional; `episodic` | `episodic` oder `serial`; Ausgabe als `itunes:type` im Serien-RSS. Ändert nicht die Sortierung im Publisher: Shows werden weiterhin nach Veröffentlichungsdatum absteigend ausgegeben. |
| `copyright` | Text | Optional; keine Ausgabe ohne Feld | Rechtehinweis, z. B. `© 2026 Beispielstudio`; Ausgabe als `copyright` im Serien-RSS. |
| `formats` | Liste von Texten | Pflicht | Mindestens ein eindeutiger Formatname aus `system/config/media.php`. Nur Kleinbuchstaben und Ziffern; keine Duplikate. Gilt für sämtliche Shows der Serie und bestimmt Audiodateien, Downloadlinks und Feed-Enclosures. |
| `default_format` | Text | Pflicht | Muss in `formats` enthalten sein. Bestimmt das primäre Enclosure in `/{serie}/feed.xml` und im serienübergreifenden Meta-RSS. |

Der Markdown-Text nach dem Front Matter ist die ausführliche Serienbeschreibung auf der Serienseite. Er wird nicht als vollständiger HTML-Inhalt in die Serienfeeds übernommen.

Vollständiges Beispiel mit allen Serienfeldern:

```markdown
---
title: Stimmen aus dem Studio
description: Gespräche über Technik und Medien.
language: de-DE
author: Beispielstudio
image: cover.jpg
category:
  - Technology
  - News
explicit: false
type: episodic
copyright: © 2026 Beispielstudio
formats:
  - mp3
  - m4a
  - ogg
  - opus
  - flac
default_format: mp3
---

Die ausführliche Beschreibung mit **Markdown-Formatierung**.
```

Für dieses Beispiel muss jede Show in allen fünf Formaten verfügbar sein. Nicht benötigte Formate können aus der Serienliste entfernt werden.

## Einzelne Show: Episoden-Datei

| Feld | Typ | Pflicht / Standard | Bedeutung und Verwendung |
| --- | --- | --- | --- |
| `title` | Text | Pflicht; bei fehlendem Feld aus dem Show-Slug abgeleitet | Titel auf der Website und in RSS, Atom und JSON Feed. Die Ableitung ersetzt Bindestriche durch Leerzeichen und schreibt den ersten Buchstaben groß. Ein explizit leerer Titel ist ungültig. |
| `date` | Datum als Text | Pflicht im geladenen Datensatz; im Front Matter optional bei Datumspräfix im Dateinamen | Veröffentlichungsdatum; Details unten. Steuert Sichtbarkeit, Sortierung und das Veröffentlichungsdatum in allen Feeds. |
| `guid` | Text | Pflicht | Dauerhafte, über alle Serien eindeutige Kennung, z. B. `urn:uuid:550e8400-e29b-41d4-a716-446655440000`. Nach Veröffentlichung unverändert lassen. RSS-`guid` mit `isPermaLink="false"`, Atom-`id` und JSON-`id`. Ohne Feld verwenden Feeds ersatzweise die Show-URL, der Validator meldet das Feld dennoch als fehlend. Ein UUID-Format wird nicht erzwungen. |
| `audio` | Text | Pflicht | Gemeinsamer Dateistamm ohne Erweiterung für sämtliche Serienformate. Relativer Medienpfad oder absolute HTTP(S)-URL; Einzelheiten im Abschnitt Medien. |
| `duration` | Text | Pflicht | Dauer als `MM:SS` oder `HH:MM:SS`, z. B. `42:17` oder `01:02:03`; bei Stundenangabe beliebig viele Stundenziffern. Minuten und Sekunden jeweils 0–59, Sekunden immer zweistellig. RSS-`itunes:duration`; im JSON Feed als Sekunden pro Attachment. Wird nicht aus der Audiodatei ermittelt. |
| `episode_type` | Text | Pflicht; RSS-Ersatzwert `full` | `full`, `trailer` oder `bonus`; Ausgabe als `itunes:episodeType` in RSS. |
| `description` | Text | Optional; Markdown-Text als Klartext | Kurze Zusammenfassung in den Show-Listen auf der Website, RSS-`description` und JSON-`summary`. Ohne Feld wird der gesamte Markdown-Inhalt in Klartext umgewandelt. Ersetzt nicht die ausführlichen Shownotes. |
| `episode` | Positive Ganzzahl | Optional; keine Ausgabe ohne Feld | Folgennummer, mindestens `1`; RSS-`itunes:episode`. Keine automatische Nummerierung oder Prüfung der Reihenfolge. |
| `season` | Positive Ganzzahl | Optional; keine Ausgabe ohne Feld | Staffelnummer, mindestens `1`; RSS-`itunes:season`. |
| `explicit` | Boolean | Optional; Wert der Serie | Überschreibt für diese Show die Serienvorgabe in RSS-`itunes:explicit`. Ein eigenes `false` überschreibt auch ein Serien-`true`. |
| `audio_length_<format>` | Positive Ganzzahl | Bedingt Pflicht je Format | Dateigröße in Bytes, z. B. `audio_length_mp3: 12345678`. Erforderlich, wenn die jeweilige Audiodatei nicht lokal verfügbar ist; siehe Medien. |

Weitere optionale Episodenfelder:

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `draft` | Boolean; Standard `false` | `true` hält die Show unabhängig vom Datum unveröffentlicht. Nicht zitieren. |
| `transcript` | Text | Relativer Pfad zu einer lokalen UTF-8-Datei `.txt` oder `.vtt`; Website und RSS-`podcast:transcript`. |
| `image` | Text; optional | Episodencover als relativer Medienpfad oder absolute HTTP(S)-URL. Ohne eigenes Cover wird das Seriencover verwendet; Einzelheiten unten. |

### Veröffentlichungsdatum und Planung

Akzeptiert werden ausschließlich `YYYY-MM-DD` oder `YYYY-MM-DDTHH:MM:SS` mit anschließendem `Z` oder UTC-Offset wie `+02:00`. Beispiele:

```yaml
date: 2026-10-14
```

```yaml
date: 2026-10-14T18:00:00+02:00
```

Ein Datum ohne Uhrzeit bedeutet Mitternacht in der konfigurierten Zeitzone (`timezone`, standardmäßig `Europe/Berlin`). Fehlt `date`, werden die ersten zehn Zeichen des Dateinamens als Datum gelesen. Ein explizites `date` hat Vorrang vor dem Dateinamen; es ändert nicht den daraus abgeleiteten Show-Slug. Ungültige Kalenderdaten, relative Angaben und Uhrzeiten ohne Zeitzone werden abgelehnt.

Shows mit einem zukünftigen Datum erscheinen erst ab diesem Zeitpunkt auf der Website und in den Feeds; ein direkter Aufruf liefert vorher 404. Der Inhaltsvalidator berücksichtigt sie bereits. Zusätzlich hält `draft: true` eine Show unabhängig vom Datum zurück; auch Kapitel- und Transkriptrouten liefern dann 404. Zum Veröffentlichen `draft: false` setzen oder das Feld entfernen. Ein zukünftiges Datum gilt weiterhin.

Der Befehl `php bin/oprcast new stimmen mein-thema` legt eine neue Markdown-Datei in der vorhandenen Serie an: mit aktuellem Datum und Uhrzeit in der konfigurierten Zeitzone, dauerhaftem UUID-v4-GUID, Titel aus dem Slug, Audiostamm, `episode_type: full` und `draft: true`. Vorhandene Slugs und Dateien werden nicht überschrieben. Slugs verwenden kleine ASCII-Buchstaben, Ziffern und einzelne Bindestriche; `index` ist reserviert. Shownotes, Medien und `duration` ergänzen, Entwurfsstatus entfernen und vor der Veröffentlichung validieren. Die GUID beibehalten.

Bei Entwürfen prüft der Validator Feldtypen, Datum sowie doppelte Slugs und vorhandene GUIDs. Andere Pflichtfelder, Medien und Kapitelregeln werden erst nach Entfernen des Entwurfsstatus geprüft. Direkt hochgeladene Medien bleiben über öffentliche Inhalts- oder CDN-URLs abrufbar; der Entwurfsstatus ist kein Zugriffsschutz für diese Dateien.

### Vollständiges Beispiel

Datei `content/stimmen/2026-10-14-erste-show.md`, passend zur Serie oben. Die Bytewerte sind Platzhalter für tatsächlich extern bereitgestellte Dateien:

```markdown
---
title: Unser erstes Gespräch
description: Wie aus einer Idee ein Podcast wird.
date: 2026-10-14T18:00:00+02:00
guid: urn:uuid:550e8400-e29b-41d4-a716-446655440000
audio: https://media.example.org/stimmen/erste-show
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

Ausführliche Shownotes mit **Hervorhebungen** und [Links](https://example.org).

## Kapitel

- 00:00 Begrüßung
- 03:15 Von der Idee zur Aufnahme
- 39:40 Ausblick
```

## Audio, Cover und Dateigrößen

Die mitgelieferte zentrale Formatkonfiguration bietet:

| Formatname | Angehängte Erweiterung | MIME-Typ | Größenfeld bei externen Dateien |
| --- | --- | --- | --- |
| `mp3` | `.mp3` | `audio/mpeg` | `audio_length_mp3` |
| `m4a` | `.m4a` | `audio/mp4` | `audio_length_m4a` |
| `ogg` | `.ogg` | `audio/ogg` | `audio_length_ogg` |
| `opus` | `.opus` | `audio/opus` | `audio_length_opus` |
| `flac` | `.flac` | `audio/flac` | `audio_length_flac` |

Weitere Formate lassen sich in [system/config/media.php](../system/config/media.php) mit `extension` und `mime_type` definieren. Das Größenfeld verwendet immer den Formatnamen, nicht zwingend die Dateierweiterung. Formatdefinitionen und Bytegrößen werden nicht aus ID3-Tags oder anderen eingebetteten Audio-Metadaten gelesen.

- Ohne CDN verweist `audio: erste-show` beim Format `mp3` auf `content/{serie}/erste-show.mp3`; die öffentliche URL lautet `{base_url}/content/{serie}/erste-show.mp3`. Unterverzeichnisse wie `audio/erste-show` sind möglich.
- Die Erweiterung wird immer angehängt, auch bei absoluten URLs. Daher den Stamm ohne `.mp3` usw. angeben. Für `image` wird keine Erweiterung angehängt.
- Bei lokalen Audiodateien wird die positive Dateigröße automatisch gelesen und hat Vorrang vor einem angegebenen `audio_length_<format>`. Ohne CDN kann ein Größenfeld eine fehlende oder leere lokale Audiodatei nicht ersetzen.
- Bei absoluten HTTP(S)-Audiostämmen muss die Größe für jedes aktivierte Format angegeben werden. Es erfolgt keine entfernte Größenabfrage.
- Mit `cdn_url` beziehungsweise `OPRCAST_CDN_URL` werden relative Audio- und Coverpfade unter dieser Basis-URL veröffentlicht. Beispiel: `audio: stimmen/erste-show` und `https://cdn.example.org/` ergeben `https://cdn.example.org/stimmen/erste-show.mp3`. Der Serienordner wird dabei nicht automatisch ergänzt.
- Auch bei CDN-Veröffentlichung wird die Größe einer lokal unter dem angegebenen relativen Pfad neben der Markdown-Datei vorhandenen Audiodatei automatisch verwendet. Andernfalls ist das Größenfeld Pflicht.
- Absolute HTTP(S)-Medien-URLs verwenden weder die CDN-Basis noch den Serienpfad. Andere URL-Schemata sowie relative Pfade mit `..` oder Backslashes sind unzulässig. Relative Pfade werden für die öffentliche URL segmentweise URL-kodiert.
- Die Größen fließen in RSS-`enclosure` und `podcast:alternateEnclosure`, Atom-Enclosure-Links sowie JSON-`size_in_bytes` ein. Doppelt verwendete Audio-URLs werden serienübergreifend vom Validator gemeldet.

### Episodencover und Linkvorschauen

Ein optionales `image: episoden-cover.jpg` in der Episoden-Datei überschreibt das Seriencover. Ohne Feld oder bei leerem Wert wird das Seriencover verwendet. Relative Pfade werden neben der Episoden-Datei aufgelöst; absolute HTTP(S)-URLs und die CDN-Konfiguration funktionieren wie beim Seriencover. Auf der Episodenseite wird das Bild mit Alternativtext angezeigt. Serien-RSS, Format-RSS und Meta-RSS veröffentlichen es als `itunes:image` am Item; JSON Feed als `image` pro Item. Atom erhält kein zusätzliches Coverfeld. Der Validator prüft lokale Episodencover auf Existenz, sofern kein CDN konfiguriert ist; Bildformat und Abmessungen werden nicht geprüft.

Erfolgreiche HTML-Seiten enthalten einen kanonischen Link sowie `og:title`, `og:description`, `og:type` (`website`), `og:url` und `og:site_name` nach dem [Open-Graph-Protokoll](https://ogp.me/). Serien- und Episodenseiten ergänzen `og:image` und `og:image:alt` mit dem jeweils wirksamen Cover. Bild-URLs sind absolut. Kanonische URLs enthalten keine Abfrageparameter, also beispielsweise kein `?t=195`. Startseite und statische Seiten erhalten kein automatisch ausgewähltes Podcastbild. Fehlerseiten erzeugen keine kanonischen Links oder OG-Metatags. Die tatsächliche Vorschau und deren Aktualisierung bestimmt der jeweilige externe Dienst.

Für die HTML-Metabeschreibung und `og:description` gilt das `description`-Feld des jeweiligen Inhalts: Episode, Serie, statische Seite oder Startseite. Ohne Feld wird dessen Markdown-Text als einzeiliger Klartext auf höchstens 240 Anzeigespalten gekürzt. Explizite Beschreibungen bleiben unverändert. Feed-Beschreibungen und vollständige Shownotes behalten ihr bisheriges Verhalten.

## Markdown-Inhalt und Kapitel

Der Show-Text nach dem Front Matter bildet die Shownotes auf der Website, RSS-`content:encoded`, Atom-`content` mit Typ `html` und JSON-`content_html`. Statische Seiten und Serientexte nutzen denselben mitgelieferten Parser Parsedown 1.8.0. Unterstützt werden ATX- und Setext-Überschriften, Absätze, verschachtelte Listen, Tabellen, Bilder, Inline- und Referenzlinks, Hervorhebungen, Durchstreichungen, Blockzitate, horizontale Linien, harte Zeilenumbrüche, Inline-Code sowie eingerückte oder eingezäunte Codeblöcke mit optionaler Sprachangabe. Überschriften erhalten Fragment-IDs; ATX-Überschriften benötigen ein Leerzeichen nach `#`. Der Safe Mode maskiert rohes HTML und filtert unsichere Linkschemata. Kurze `description`-Felder im Front Matter bleiben Klartext.

Kapitel sind keine Front-Matter-Liste. Der Publisher extrahiert sie aus dem ersten passenden Markdown-Abschnitt mit einer Überschrift zweiter Ebene. Deren Name stammt aus `chapters_keyword` der konfigurierten CMS-Sprachdatei: standardmäßig `## Kapitel` bei `language: de` in der **Site-Konfiguration**, bei `en` entsprechend `## Chapters`. Die Sprache der Serie und die Browsersprache ändern diesen Schlüssel nicht.

Jeder Eintrag beginnt mit `-` oder `*`, einem Zeitstempel und dem Titel, etwa `- 03:15 Hauptthema`. Erkannt werden Zeitstempel mit zwei oder drei Teilen (Minuten/Sekunden beziehungsweise Stunden/Minuten/Sekunden); die ersten Teile haben jeweils ein bis zwei Ziffern, Sekunden genau zwei. Der erste erkannte Eintrag muss bei `00:00` beginnen, weitere müssen streng aufsteigend sein. Die Extraktion endet an der nächsten Überschrift erster oder zweiter Ebene. Nicht passende Zeilen werden übersprungen; der Validator prüft weder die Kapitelgrenze gegen die Audiodauer noch die Minuten-/Sekundenbereiche der Kapitelzeitstempel.

Ausgegeben werden ausschließlich `startTime` (Sekunden) und `title` unter `/{serie}/{show}/chapters.json`, mit Dokumentversion `1.2.0`. Bei vorhandenen Kapiteln verweist RSS über `podcast:chapters` darauf. Ohne Kapitel liefert die Kapitelroute weiterhin ein Dokument mit leerer Liste. Kapitelbilder, Kapitel-URLs oder ein Front-Matter-Feld für eine externe Kapiteldatei werden nicht ausgewertet.

## Transkripte und Kapitel auf der Website

`transcript: transkripte/erste-show.vtt` ordnet eine Begleitdatei relativ zur Episoden-Datei zu. Unterstützt sind lokale UTF-8-Dateien `.txt` und `.vtt` (mit `WEBVTT`-Kopf). Absolute URLs, übergeordnete Pfade und Dateien außerhalb des Serienverzeichnisses sind unzulässig. Die CDN-Einstellung gilt nicht für Transkripte.

Die Episodenseite zeigt maskierten Text und einen Downloadlink. Bei WebVTT entfallen in der lesbaren Darstellung Zeitstempel, Cue-IDs, Kommentare und Markup; Sprecherbezeichnungen bleiben erhalten. Die Originaldatei wird unter `/{serie}/{show}/transcript.txt` beziehungsweise `transcript.vtt` mit ETag- und HEAD-Unterstützung bereitgestellt. Serien-RSS, Format-RSS und Meta-RSS verweisen per `podcast:transcript` darauf (`text/plain` oder `text/vtt`). Atom und JSON Feed erhalten kein zusätzliches Transkriptfeld.

Zeitstempel im Markdown-Kapitelabschnitt werden auf Episodenseiten zu Links wie `?t=195`. Mit JavaScript springen diese im Player zur passenden Stelle und aktualisieren die URL. Je Zeitstempel gibt es eine Kopierschaltfläche; ohne Zwischenablagezugriff bleibt das Kontextmenü des Links verfügbar. Zeitlinks springen nach Laden der Audiometadaten, ohne automatische Wiedergabe. Ohne JavaScript bleiben Player, Shownotes und Transkript nutzbar. Shownotes in Feeds und Kapitel-JSON bleiben unverändert.

## Zwischen Episoden blättern

Unter den Shownotes einschließlich der Kapitelliste erscheinen Links zur unmittelbar vorherigen und nächsten veröffentlichten Episode derselben Serie. Die beschriftete Navigation nennt jeweils den Episodentitel und folgt der Reihenfolge nach Veröffentlichungsdatum im Serienindex. Entwürfe und zukünftige Episoden werden übersprungen. Bei der ältesten beziehungsweise neuesten Episode erscheint nur die verfügbare Richtung; bei nur einer veröffentlichten Episode entfällt die Navigation. Sie funktioniert ohne JavaScript und ohne zusätzliche Metadaten.

## Komfortfunktionen des Webplayers

Mit JavaScript ergänzen beschriftete Schaltflächen für 15 Sekunden zurück und 30 Sekunden vor sowie eine Geschwindigkeitsauswahl (0,75×, 1×, 1,25×, 1,5×, 2×) den nativen Player. Sie sind über die Tastatur erreichbar. Sprungtasten sind erst nach Laden der Audiometadaten verfügbar und bleiben innerhalb der Audiodauer. Änderungen der Geschwindigkeit über native Browserfunktionen werden in die Auswahl übernommen. Ohne JavaScript bleiben die Zusatzfunktionen ausgeblendet; der native Player ist weiterhin bedienbar.

Nach Beginn der Wiedergabe wird die Hörposition je Episoden-GUID lokal im Browser gespeichert. Während des Hörens erfolgt dies in Fünf-Sekunden-Abschnitten der Audiozeit, bei Pause, Positionswechsel, Verlassen oder Wechsel in den Hintergrund mit der aktuellen Position. Beim nächsten Besuch wird die Position nach Laden der Metadaten ohne automatische Wiedergabe wiederhergestellt. Ein gültiger Zeitlink `?t=` hat Vorrang, auch `?t=0`. Am Episodenende wird die gespeicherte Position gelöscht; Positionen in der letzten Sekunde oder hinter dem Ende werden beim Wiederherstellen verworfen.

Die Speicherung gilt nur für denselben Browser auf demselben Gerät und derselben Website-Origin; es erfolgt keine Übermittlung an den Server oder Synchronisierung. Durch Löschen der Website-Daten im Browser lassen sich die Positionen entfernen. Bei blockiertem oder nicht verfügbarem Browserspeicher funktionieren die Bedienelemente weiterhin, ohne Positionen zu speichern. Zusätzliche Front-Matter-Felder oder Konfiguration sind nicht nötig.

## Welche Metadaten erscheinen in welchem Feed?

Die Tabellen oben nennen die genauen Zielfelder. Ergänzend gelten folgende Unterschiede:

| Ausgabe | Umfang |
| --- | --- |
| Serien-RSS `/{serie}/feed.xml` | Serienmetadaten und Shows mit iTunes-Feldern, HTML-Shownotes und Kapitelverweisen. Primäres Audio gemäß `default_format`; alle Varianten zusätzlich als `podcast:alternateEnclosure`. |
| Format-RSS `/{serie}/feed.{format}.xml` | Gleiche Metadaten; primäres Enclosure und als Standard markierte Alternative entsprechen dem angeforderten, für die Serie aktivierten Format. |
| Meta-RSS `/feed.xml` | Shows aller Serien mit denselben Show-Feldern wie Serien-RSS, zusätzlich Serienautor und `source` mit Serientitel und Serienfeed-URL. Kanal-Titel und Sprache stammen aus der Site-Konfiguration beziehungsweise deren Sprachdatei, die Beschreibung aus `content/pages/home.md`; Seriencover, Kategorien, Serien-Copyright und Serientyp werden nicht als eigene Kanalmetadaten übernommen. |
| Atom `/{serie}/feed.atom` | Serientitel, Serien-URL als ID und Serienautor; pro Show Titel, GUID, Veröffentlichungs-/Änderungszeit, URL, HTML-Shownotes und alle Audio-Enclosures. Keine separaten Kurzbeschreibungen, Cover, Kategorien oder iTunes-Felder. |
| JSON Feed `/{serie}/feed.json` | Serientitel, Beschreibung, Sprache und Cover als `icon`; pro Show GUID, URL, Titel, Shownotes, Kurzbeschreibung, Cover als `image`, Veröffentlichungsdatum und alle Audio-Attachments einschließlich Dauer in Sekunden. Keine separaten Autoren-, Staffel-, Folgen-, Copyright- oder Explicit-Felder. |

Alle Feeds sind auf `feed_limit` Einträge begrenzt (standardmäßig `100`), neueste Shows zuerst. Der HTML-Audioplayer bietet Quellen in der Reihenfolge von `formats` an; `default_format` sortiert diese Liste nicht um.

Die Einleitung oberhalb der Serienliste lässt sich mit `content/pages/home.md` gestalten. Das Front Matter kann `title` und `description` enthalten; der Markdown-Inhalt erscheint unter dem Titel. Die Datei erscheint nicht zusätzlich in der Navigation. Ohne Datei wird der konfigurierte Website-Titel angezeigt.

Die HTML-Metabeschreibung der Startseite und die Kanalbeschreibung des Meta-RSS stammen aus `description` in `content/pages/home.md`, ersatzweise aus dessen Markdown-Inhalt als Klartext. Die automatisch erzeugte HTML-Vorschau wird wie oben beschrieben gekürzt; die Feed-Beschreibung bleibt vollständig. Ohne Datei bleiben diese Beschreibungen leer. Andere HTML-Seiten verwenden ihre eigenen Inhalte für die Metabeschreibung. Der Inhalt von `home.md` bleibt unabhängig von der Browsersprache.

## Globale Konfiguration und automatisch erzeugte Angaben

Die Publisher-Startseite zeigt standardmäßig eine HTML-Liste der Episoden aller Serien. Zum Ausblenden in `system/config/site.php` den Eintrag `'show_home_episodes' => false,` setzen; mit `true` wird die Liste wieder eingeblendet. Fehlt die Einstellung, bleibt die Liste aktiviert. Einleitung und Serienübersicht der Startseite, einzelne Serien- und Episodenseiten sowie alle RSS-, Atom- und JSON-Feeds bleiben verfügbar.

In [system/config/site.php](../system/config/site.php) beeinflussen folgende Einstellungen die Veröffentlichung. Sie gehören nicht in das Front Matter:

| Einstellung | Standard / Herkunft | Wirkung |
| --- | --- | --- |
| `title` | `OPRCast` | Website-Titel und Kanal-Titel des Meta-RSS. |
| `language` | `de` | CMS-Sprachdatei in `system/lang`, Fallback bei Browser-Spracherkennung, Feed-Sprachfallback und Auswahl der Kapitelüberschrift. |
| `timezone` | `Europe/Berlin` | Zeitzone insbesondere für reine Datumsangaben ohne Offset. |
| `base_url` | `OPRCAST_BASE_URL`, sonst aus der Anfrage | Kanonische Basis für Seiten-, Feed-, Kapitel- und lokale Medien-URLs. Für Produktion ausdrücklich konfigurieren. |
| `cdn_url` | `OPRCAST_CDN_URL`, sonst kein CDN | Basis-URL für relative Medienpfade. |
| `content_path` | Projektverzeichnis plus `/content` | Wurzel der Serien und statischen Seiten. |
| `show_home_episodes` | `true` | HTML-Episodenliste aller Serien auf der Publisher-Startseite anzeigen. Mit `false` ausblenden. |
| `feed_limit` | `100` | Maximale Anzahl der Einträge je Feed, auch im Meta-RSS. |
| `development` | `OPRCAST_DEBUG`, sonst `false` | Steuert die Anzeige detaillierter Fehlermeldungen; kein veröffentlichtes Podcast-Metadatum. |

Beim Start werden außerdem `media_formats` aus `media.php` und `language_code` aus der konfigurierten Sprachdatei ergänzt. Website-Sprache und HTML-`lang` folgen den Browserpräferenzen mit Rückfall auf die konfigurierte Sprache; die Feed-Metadaten bleiben davon unabhängig.

Folgende Datensatzfelder erzeugt der Publisher automatisch; sie sind keine zusätzlichen redaktionellen Eingabefelder:

- `slug`, `series_slug`: aus Ordner und Dateiname; daraus entstehen Seiten-, Feed-, Quellen- und Kapitel-URLs.
- `body`, `html`: Markdown-Inhalt und dessen gerenderte HTML-Fassung.
- `source`, `modified`: Quelldatei und deren Dateisystem-Änderungszeit. Änderungszeiten liefern Atom-`updated` und RSS-`lastBuildDate`; sie ersetzen nicht das Veröffentlichungsdatum.
- `chapters`: aus dem Markdown extrahierte Kapitel.
- `series_title`: beim Zusammenführen aller Shows aus der zugehörigen Serie ergänzt.
- Audio-Enclosures mit `format`, `url`, `mime_type`, `length` und `default`: aus Serienformaten, Audiostamm und Dateigröße berechnet; `audio_file` wird intern zur URL-Auflösung verwendet.

## Unterstützungsgrenzen und Validierung

Unbekannte Front-Matter-Schlüssel werden nicht generell zurückgewiesen, erzeugen aber ohne entsprechende Implementierung keine Ausgabe. Beispielsweise wird `author` auf Show-Ebene zwar als Text akzeptiert, dort aber nicht als Show-Autor veröffentlicht. Es gibt keine allgemeine Vererbung sämtlicher Serienfelder; `explicit` und das Cover verwenden die Serienvorgabe, wenn keine eigene Angabe vorliegt. Formate und Standardformat stammen aus der Serie.

Weitere Podcast-Metadaten wie Owner-/Kontakt-E-Mail, Untertitel, Personen, Funding-/Value-Angaben, Block-/Complete-Kennzeichen oder Weiterleitungs-URLs sind derzeit nicht implementiert. Sie lassen sich nicht allein durch zusätzliche Front-Matter-Felder aktivieren.

Vor der Veröffentlichung aus dem Projektverzeichnis ausführen:

```sh
php bin/oprcast validate
```

Geprüft werden unter anderem Pflichtfelder, Feldtypen, erlaubte Serien-/Show-Typen, Medienformate, Standardformat, Dauer, Datum, lokale Seriencover und Audiodateien, erforderliche externe Bytegrößen, doppelte Show-Slugs innerhalb einer Serie, serienübergreifend doppelte GUIDs und Audio-URLs sowie Kapitelbeginn und -reihenfolge. Die Prüfung ist keine vollständige Prüfung der Anforderungen externer Podcast-Verzeichnisse.
