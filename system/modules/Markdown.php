<?php

declare(strict_types=1);

namespace Oprcast;

/**
 * Renders Markdown with Parsedown and extracts podcast chapters.
 *
 * Parsedown safe mode escapes raw HTML and filters links so content files cannot
 * inject arbitrary HTML or unsafe link schemes.
 */
final class Markdown
{
    /** Creates a renderer using the localized heading that identifies chapters. */
    public function __construct(private readonly string $chapterKeyword)
    {
        if (trim($chapterKeyword) === '') {
            throw new \InvalidArgumentException('The chapter keyword must not be empty.');
        }
    }

    /** Converts Markdown to HTML while escaping raw HTML and unsafe links. */
    public function render(string $markdown): string
    {
        $parser = new ParsedownRenderer();
        $parser->setSafeMode(true);
        $parser->setStrictMode(true);
        return $parser->text($markdown);
    }

    /**
     * Extracts timestamped entries from the configured chapter section.
     *
     * @return list<array{startTime: int, title: string}>
     */
    public function chapters(string $markdown): array
    {
        $keyword = preg_quote($this->chapterKeyword, '/');
        if (preg_match('/^##\s+' . $keyword . '\s*$([\s\S]*?)(?=^#{1,2}\s|\z)/miu', $markdown, $section) !== 1) {
            return [];
        }

        $chapters = [];
        foreach (explode("\n", $section[1]) as $line) {
            if (preg_match('/^\s*[-*]\s+((?:\d{1,2}:)?\d{1,2}:\d{2})\s+(.+)$/u', $line, $match) !== 1) {
                continue;
            }
            $parts = array_map('intval', explode(':', $match[1]));
            $seconds = count($parts) === 3 ? $parts[0] * 3600 + $parts[1] * 60 + $parts[2] : $parts[0] * 60 + $parts[1];
            $chapters[] = ['startTime' => $seconds, 'title' => trim($match[2])];
        }
        return $chapters;
    }

    /** Produces plain text from Markdown for feed summaries and metadata. */
    public function text(string $markdown): string
    {
        return trim(html_entity_decode(strip_tags($this->render($markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
