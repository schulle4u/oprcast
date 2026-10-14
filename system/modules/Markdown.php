<?php

declare(strict_types=1);

namespace Oprcast;

final class Markdown
{
    public function __construct(private readonly string $chapterKeyword)
    {
        if (trim($chapterKeyword) === '') {
            throw new \InvalidArgumentException('The chapter keyword must not be empty.');
        }
    }

    public function render(string $markdown): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        $html = [];
        $paragraph = [];
        $list = null;
        $inCode = false;
        $code = [];

        $flushParagraph = function () use (&$html, &$paragraph): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . $this->inline(implode(' ', $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $closeList = static function () use (&$html, &$list): void {
            if ($list !== null) {
                $html[] = "</{$list}>";
                $list = null;
            }
        };

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                $flushParagraph();
                $closeList();
                if ($inCode) {
                    $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
                    $code = [];
                }
                $inCode = !$inCode;
                continue;
            }
            if ($inCode) {
                $code[] = $line;
                continue;
            }
            if (trim($line) === '') {
                $flushParagraph();
                $closeList();
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+)$/u', $line, $match) === 1) {
                $flushParagraph();
                $closeList();
                $level = strlen($match[1]);
                $id = $this->slug($match[2]);
                $html[] = "<h{$level} id=\"{$id}\">" . $this->inline($match[2]) . "</h{$level}>";
                continue;
            }
            if (preg_match('/^\s*([-*])\s+(.+)$/u', $line, $match) === 1 || preg_match('/^\s*\d+[.)]\s+(.+)$/u', $line, $match) === 1) {
                $flushParagraph();
                $wanted = preg_match('/^\s*\d/', $line) === 1 ? 'ol' : 'ul';
                if ($list !== $wanted) {
                    $closeList();
                    $html[] = "<{$wanted}>";
                    $list = $wanted;
                }
                $html[] = '<li>' . $this->inline($match[count($match) - 1]) . '</li>';
                continue;
            }
            if (preg_match('/^>\s?(.*)$/u', $line, $match) === 1) {
                $flushParagraph();
                $closeList();
                $html[] = '<blockquote><p>' . $this->inline($match[1]) . '</p></blockquote>';
                continue;
            }
            $paragraph[] = trim($line);
        }

        if ($inCode) {
            throw new ContentException('Unclosed fenced code block.');
        }
        $flushParagraph();
        $closeList();
        return implode("\n", $html);
    }

    /** @return list<array{startTime: int, title: string}> */
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

    public function text(string $markdown): string
    {
        return trim(html_entity_decode(strip_tags($this->render($markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace_callback('/\[([^\]]+)\]\(([^\s)]+)\)/u', static function (array $match): string {
            $url = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https', 'mailto'], true)) {
                return $match[1];
            }
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $match[1] . '</a>';
        }, $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
        return preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $escaped) ?? $escaped;
    }

    private function slug(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^\pL\pN]+/u', '-', $text) ?? '', '-'));
        return htmlspecialchars($slug !== '' ? $slug : 'abschnitt', ENT_QUOTES, 'UTF-8');
    }
}

