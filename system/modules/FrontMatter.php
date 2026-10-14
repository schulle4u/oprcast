<?php

declare(strict_types=1);

namespace Oprcast;

/** Parses the intentionally small YAML-like front matter format used by content files. */
final class FrontMatter
{
    /**
     * Separates metadata from the Markdown body and validates supported syntax.
     *
     * Only scalar values and top-level lists are supported; duplicate keys and
     * malformed lines are rejected with a source-aware exception.
     *
     * @return array{meta: array<string, mixed>, body: string}
     */
    public function parse(string $source, string $name = 'content'): array
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        if (!str_starts_with($source, "---\n")) {
            return ['meta' => [], 'body' => trim($source)];
        }

        $end = strpos($source, "\n---\n", 4);
        if ($end === false) {
            throw new ContentException("{$name}: front matter is not closed with ---. ");
        }

        $header = substr($source, 4, $end - 4);
        $body = trim(substr($source, $end + 5));
        $meta = [];
        $listKey = null;

        foreach (explode("\n", $header) as $number => $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match('/^\s+-\s+(.+)$/u', $line, $match) === 1 && $listKey !== null) {
                $meta[$listKey][] = $this->scalar($match[1]);
                continue;
            }

            if (preg_match('/^([a-z][a-z0-9_]*)\s*:\s*(.*)$/i', $line, $match) !== 1) {
                throw new ContentException(sprintf('%s:%d: unsupported front matter syntax.', $name, $number + 2));
            }

            $key = strtolower($match[1]);
            if (array_key_exists($key, $meta)) {
                throw new ContentException("{$name}: duplicate front matter key '{$key}'.");
            }

            if ($match[2] === '') {
                $meta[$key] = [];
                $listKey = $key;
            } else {
                $meta[$key] = $this->scalar($match[2]);
                $listKey = null;
            }
        }

        return ['meta' => $meta, 'body' => $body];
    }

    /** Converts a front matter scalar to its string, integer, boolean or null value. */
    private function scalar(string $value): mixed
    {
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            return stripcslashes(substr($value, 1, -1));
        }

        $lower = strtolower($value);
        return match ($lower) {
            'true' => true,
            'false' => false,
            'null', '~' => null,
            default => preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value,
        };
    }
}

