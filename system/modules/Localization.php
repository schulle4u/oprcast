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

    /** Selects an installed catalogue by browser preference, or the configured fallback. */
    public static function browserLanguage(string $acceptLanguage, string $fallback, string $directory): string
    {
        $available = [];
        foreach (glob(rtrim($directory, '/\\') . '/*.php') ?: [] as $file) {
            $language = pathinfo($file, PATHINFO_FILENAME);
            $available[strtolower(str_replace('_', '-', $language))] = $language;
        }

        $preferences = [];
        foreach (explode(',', $acceptLanguage) as $position => $preference) {
            if (preg_match('/^\s*([a-z]{1,8}(?:-[a-z0-9]{1,8})*|\*)\s*(?:;\s*q\s*=\s*(0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?))?\s*$/i', $preference, $match) !== 1) {
                continue;
            }
            $quality = isset($match[2]) ? (float) $match[2] : 1.0;
            if ($quality > 0) {
                $preferences[] = ['language' => strtolower($match[1]), 'quality' => $quality, 'position' => $position];
            }
        }
        usort($preferences, static fn (array $a, array $b): int =>
            ($b['quality'] <=> $a['quality']) ?: ($a['position'] <=> $b['position']));

        foreach ($preferences as $preference) {
            $language = $preference['language'];
            if ($language === '*') {
                return $fallback;
            }
            while ($language !== '') {
                if (isset($available[$language])) {
                    return $available[$language];
                }
                $separator = strrpos($language, '-');
                $language = $separator === false ? '' : substr($language, 0, $separator);
            }
        }
        return $fallback;
    }

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
