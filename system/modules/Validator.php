<?php

declare(strict_types=1);

namespace Oprcast;

final class Validator
{
    /** @return list<string> */
    public function validate(ContentRepository $repository): array
    {
        $errors = [];
        $guids = [];
        $enclosures = [];

        try {
            $allSeries = $repository->series();
        } catch (ContentException $exception) {
            return [$exception->getMessage()];
        }

        foreach ($allSeries as $series) {
            $slugs = [];
            $this->required($series, ['title', 'description', 'language', 'author', 'image', 'category', 'explicit'], $errors);
            $this->localFile($series, 'image', $errors);
            if (isset($series['type']) && !in_array($series['type'], ['episodic', 'serial'], true)) {
                $errors[] = "{$series['source']}: type must be episodic or serial.";
            }

            try {
                $episodes = $repository->episodes((string) $series['slug'], true);
            } catch (ContentException $exception) {
                $errors[] = $exception->getMessage();
                continue;
            }
            foreach ($episodes as $episode) {
                $this->required($episode, ['title', 'date', 'guid', 'audio', 'audio_type', 'audio_length', 'duration', 'episode_type'], $errors);
                $this->localFile($episode, 'audio', $errors);
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
                if (isset($episode['audio'])) {
                    $audio = (string) $episode['audio'];
                    if (isset($enclosures[$audio])) {
                        $errors[] = "{$episode['source']}: duplicate audio URL/path also used in {$enclosures[$audio]}.";
                    }
                    $enclosures[$audio] = $episode['source'];
                }
                if (isset($episode['episode_type']) && !in_array($episode['episode_type'], ['full', 'trailer', 'bonus'], true)) {
                    $errors[] = "{$episode['source']}: episode_type must be full, trailer, or bonus.";
                }
                if (isset($episode['audio_length']) && (!is_int($episode['audio_length']) || $episode['audio_length'] < 1)) {
                    $errors[] = "{$episode['source']}: audio_length must be a positive integer.";
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

    /** @param list<string> $fields @param list<string> $errors */
    private function required(array $item, array $fields, array &$errors): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $item) || $item[$field] === '' || $item[$field] === null || $item[$field] === []) {
                $errors[] = "{$item['source']}: missing required field '{$field}'.";
            }
        }
    }

    /** @param list<string> $errors */
    private function localFile(array $item, string $field, array &$errors): void
    {
        if (!isset($item[$field]) || filter_var($item[$field], FILTER_VALIDATE_URL) !== false) {
            return;
        }
        $path = dirname((string) $item['source']) . '/' . ltrim((string) $item[$field], '/');
        if (!is_file($path)) {
            $errors[] = "{$item['source']}: local {$field} file does not exist: {$item[$field]}.";
        }
    }
}

