<?php

declare(strict_types=1);

namespace Oprcast;

/** Reads local companion transcripts without interpreting their text as HTML. */
final class Transcript
{
    /** @return array{content: string, text: string, type: string, extension: string} */
    public static function read(string $source, string $relative): array
    {
        if (preg_match('#^[^:/\\\\\x00]+(?:/[^:/\\\\\x00]+)*\.(txt|vtt)$#iD', $relative, $match) !== 1
            || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new ContentException("{$source}: transcript must be a relative .txt or .vtt file.");
        }
        $directory = realpath(dirname($source));
        $path = realpath(dirname($source) . '/' . $relative);
        if ($directory === false || $path === false || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new ContentException("{$source}: local transcript file does not exist or is outside the series directory.");
        }
        $content = file_get_contents($path);
        if ($content === false || trim($content) === '' || !mb_check_encoding($content, 'UTF-8')) {
            throw new ContentException("{$source}: transcript must contain UTF-8 text.");
        }
        $extension = strtolower($match[1]);
        $text = str_replace(["\r\n", "\r"], "\n", $content);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        if ($extension === 'vtt') {
            if (preg_match('/^WEBVTT(?:[ \t].*)?(?:\n|$)/', $text) !== 1) {
                throw new ContentException("{$source}: VTT transcript must begin with WEBVTT.");
            }
            $paragraphs = [];
            foreach (preg_split('/\n[ \t]*\n/', trim($text)) ?: [] as $block) {
                $lines = explode("\n", $block);
                $timing = null;
                if (preg_match('/^(?:WEBVTT|NOTE|STYLE|REGION)(?:\s|$)/', $lines[0]) === 1) {
                    continue;
                }
                foreach ($lines as $index => $line) {
                    if (str_contains($line, '-->')) {
                        $timing = $index;
                        break;
                    }
                }
                if ($timing === null) {
                    continue;
                }
                $cue = implode("\n", array_slice($lines, $timing + 1));
                $cue = preg_replace('/<v(?:\.[^ >]+)*\s+([^>]+)>/', '$1: ', $cue) ?? $cue;
                $paragraphs[] = html_entity_decode(strip_tags($cue), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $text = implode("\n\n", $paragraphs);
        }
        return ['content' => $content, 'text' => trim($text), 'type' => $extension === 'vtt' ? 'text/vtt' : 'text/plain', 'extension' => $extension];
    }
}
