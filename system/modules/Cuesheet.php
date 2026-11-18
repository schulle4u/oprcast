<?php

declare(strict_types=1);

namespace Oprcast;

/** Exports chapter tracks for a downloaded MP3 stored beside the CUE file. */
final class Cuesheet
{
    /** CUE supports at most 99 tracks; compressed audio uses the MP3 file type. */
    public static function available(array $series, array $episode): bool
    {
        $count = count($episode['chapters']);
        return $count > 0 && $count <= 99 && in_array('mp3', $series['formats'] ?? [], true);
    }

    /** Builds UTF-8 text with BOM using minute/second/frame indices (75 frames per second). */
    public function render(array $episode, string $audioUrl): string
    {
        $filename = rawurldecode(basename((string) parse_url($audioUrl, PHP_URL_PATH)));
        if ($filename === '' || preg_match('/["\\\\\/\x00-\x1f\x7f]/', $filename) === 1) {
            throw new NotFoundException('Audio filename cannot be represented in a cuesheet.');
        }
        $quote = static fn (string $value): string => '"' . str_replace('"', "'", preg_replace('/[\x00-\x1f\x7f]+/', ' ', $value) ?? '') . '"';
        $lines = ['TITLE ' . $quote($episode['title']), 'FILE ' . $quote($filename) . ' MP3'];
        foreach ($episode['chapters'] as $index => $chapter) {
            $seconds = $chapter['startTime'];
            $lines[] = sprintf('  TRACK %02d AUDIO', $index + 1);
            $lines[] = '    TITLE ' . $quote($chapter['title']);
            $lines[] = sprintf('    INDEX 01 %02d:%02d:00', intdiv($seconds, 60), $seconds % 60);
        }
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }
}
