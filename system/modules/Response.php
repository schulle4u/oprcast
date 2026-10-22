<?php

declare(strict_types=1);

namespace Oprcast;

/** A rendered representation shared by HTTP delivery and optional static builds. */
final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(public string $body, public int $status = 200, public array $headers = [])
    {
    }

    public function emit(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD' && $this->status !== 304) {
            echo $this->body;
        }
    }
}
