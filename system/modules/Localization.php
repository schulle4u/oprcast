<?php

declare(strict_types=1);

namespace Oprcast;

use DateTimeInterface;
use RuntimeException;

/** Loads a language catalogue and formats translated user-facing strings. */
final class Localization
{
    /** @var array<string, string> */
    private readonly array $strings;

    /** Loads and validates the selected PHP language file from the given directory. */
    public function __construct(string $language, string $directory)
    {
        if (preg_match('/^[a-z0-9_-]+$/i', $language) !== 1) {
            throw new RuntimeException("Invalid CMS language '{$language}'.");
        }

        $file = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $language . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Language file '{$file}' does not exist.");
        }

        $strings = require $file;
        if (!is_array($strings)) {
            throw new RuntimeException("Language file '{$file}' must return an array.");
        }
        foreach ($strings as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new RuntimeException("Language file '{$file}' may only contain string key-value pairs.");
            }
        }

        $this->strings = $strings;
    }

    /**
     * Returns a translation and replaces named placeholders such as `{date}`.
     *
     * @param array<string, scalar> $replacements
     */
    public function get(string $key, array $replacements = []): string
    {
        if (!array_key_exists($key, $this->strings)) {
            throw new RuntimeException("Missing translation '{$key}'.");
        }

        $replace = [];
        foreach ($replacements as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }
        return strtr($this->strings[$key], $replace);
    }

    /** Returns the catalogue's language code for feeds and document metadata. */
    public function languageCode(): string
    {
        return $this->get('language_code');
    }

    /** Formats an episode date and time according to the active catalogue. */
    public function formatEpisodeDate(DateTimeInterface $date): string
    {
        return $this->get('episode_datetime', [
            'date' => $date->format($this->get('date_format')),
            'time' => $date->format($this->get('time_format')),
        ]);
    }
}
