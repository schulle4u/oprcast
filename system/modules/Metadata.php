<?php

declare(strict_types=1);

namespace Oprcast;

/** Checks field types before metadata reaches templates or feed serializers. */
final class Metadata
{
    /** @param array<string, mixed> $meta */
    public static function validate(array $meta, string $source): void
    {
        foreach ($meta as $field => $value) {
            $valid = true;
            if (in_array($field, ['title', 'description', 'language', 'author', 'image', 'copyright', 'type', 'default_format', 'guid', 'audio', 'duration', 'episode_type'], true)) {
                // A blank cover is reported as missing by the content validator.
                $valid = is_string($value) || ($field === 'image' && ($value === [] || $value === null));
            } elseif ($field === 'explicit') {
                $valid = is_bool($value);
            } elseif (in_array($field, ['episode', 'season'], true) || str_starts_with($field, 'audio_length_')) {
                $valid = is_int($value) && $value > 0;
            } elseif (in_array($field, ['category', 'formats'], true)) {
                $valid = ($field === 'category' && is_string($value))
                    || (is_array($value) && array_is_list($value) && count(array_filter($value, 'is_string')) === count($value));
            }
            if (!$valid) {
                throw new ContentException("{$source}: invalid type or value for '{$field}'.");
            }
        }
    }
}
