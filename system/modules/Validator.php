<?php

declare(strict_types=1);

namespace Oprcast;

/** Performs cross-file and field-level checks on all podcast content. */
final class Validator
{
    /**
     * Validates series, episodes, media references, GUIDs and chapter ordering.
     *
     * All discoverable errors are returned together so editors can fix a content
     * set in one pass instead of failing at the first invalid record.
     *
     * @return list<string>
     */
    public function validate(ContentRepository $repository): array
    {
        $errors = [];
        $guids = [];
        $enclosures = [];

        try {
            $repository->pages();
        } catch (ContentException $exception) {
            $errors[] = $exception->getMessage();
        }

        try {
            $allSeries = $repository->series();
        } catch (ContentException $exception) {
            return [...$errors, $exception->getMessage()];
        }

        foreach ($allSeries as $series) {
            $slugs = [];
            $this->required($series, ['title', 'description', 'language', 'author', 'image', 'category', 'explicit', 'formats', 'default_format'], $errors);
            $this->localFile($repository, $series, 'image', $errors);
            try {
                $repository->seriesFormats($series);
                $repository->defaultFormat($series);
            } catch (ContentException $exception) {
                $errors[] = $exception->getMessage();
            }
            if (isset($series['type']) && !in_array($series['type'], ['episodic', 'serial'], true)) {
                $errors[] = "{$series['source']}: type must be episodic or serial.";
            }

            try {
                $episodes = $repository->episodes((string) $series['slug'], true, true);
            } catch (ContentException $exception) {
                $errors[] = $exception->getMessage();
                continue;
            }
            foreach ($episodes as $episode) {
                if (isset($slugs[$episode['slug']])) {
                    $errors[] = "{$episode['source']}: duplicate episode slug also used in {$slugs[$episode['slug']]}.";
                }
                $slugs[$episode['slug']] = $episode['source'];
                if (isset($episode['guid'])) {
                    $guid = (string) $episode['guid'];
                    if (isset($guids[$guid])) {
                        $errors[] = "{$episode['source']}: duplicate GUID also used in {$guids[$guid]}.";
                    }
                    $guids[$guid] = $episode['source'];
                }
                // Drafts may be incomplete, but identities must remain unique.
                if ($episode['draft'] ?? false) {
                    continue;
                }
                $this->required($episode, ['title', 'date', 'guid', 'audio', 'duration', 'episode_type'], $errors);
                $this->localFile($repository, $episode, 'image', $errors);
                try {
                    $repository->transcript($episode, 'https://validator.invalid');
                } catch (ContentException $exception) {
                    $errors[] = $exception->getMessage();
                }
                try {
                    foreach ($repository->enclosures($series, $episode, 'https://validator.invalid') as $enclosure) {
                        $audio = $enclosure['url'];
                        if (isset($enclosures[$audio])) {
                            $errors[] = "{$episode['source']}: duplicate audio URL/path also used in {$enclosures[$audio]}.";
                        }
                        $enclosures[$audio] = $episode['source'];
                    }
                } catch (ContentException $exception) {
                    $errors[] = $exception->getMessage();
                }
                if (isset($episode['episode_type']) && !in_array($episode['episode_type'], ['full', 'trailer', 'bonus'], true)) {
                    $errors[] = "{$episode['source']}: episode_type must be full, trailer, or bonus.";
                }
                if (isset($episode['duration']) && preg_match('/^(?:\d+:)?[0-5]?\d:[0-5]\d$/', (string) $episode['duration']) !== 1) {
                    $errors[] = "{$episode['source']}: duration must use MM:SS or HH:MM:SS.";
                }
                $previous = -1;
                foreach ($episode['chapters'] as $chapter) {
                    if ($chapter['startTime'] <= $previous) {
                        $errors[] = "{$episode['source']}: chapter timestamps must be strictly increasing.";
                        break;
                    }
                    $previous = $chapter['startTime'];
                }
                if ($episode['chapters'] !== [] && $episode['chapters'][0]['startTime'] !== 0) {
                    $errors[] = "{$episode['source']}: the first chapter must start at 00:00.";
                }
            }
        }
        return $errors;
    }

    /**
     * Adds an error for every required field that has no meaningful value.
     *
     * @param array<string, mixed> $item
     * @param list<string> $fields
     * @param list<string> $errors
     */
    private function required(array $item, array $fields, array &$errors): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $item) || $item[$field] === '' || $item[$field] === null || $item[$field] === []) {
                $errors[] = "{$item['source']}: missing required field '{$field}'.";
            }
        }
    }

    /**
     * Verifies that relative media exists locally unless a CDN supplies it.
     *
     * @param array<string, mixed> $item
     * @param list<string> $errors
     */
    private function localFile(ContentRepository $repository, array $item, string $field, array &$errors): void
    {
        try {
            $repository->mediaUrl($item, $field, 'https://validator.invalid');
        } catch (ContentException $exception) {
            $errors[] = $exception->getMessage();
            return;
        }
        if (!isset($item[$field]) || !is_string($item[$field]) || trim($item[$field]) === '' || filter_var($item[$field], FILTER_VALIDATE_URL) !== false || $repository->usesCdnForRelativeMedia()) {
            return;
        }
        $path = dirname((string) $item['source']) . '/' . ltrim((string) $item[$field], '/');
        if (!is_file($path)) {
            $errors[] = "{$item['source']}: local {$field} file does not exist: {$item[$field]}.";
        }
    }
}

