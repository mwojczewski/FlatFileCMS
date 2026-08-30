<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use InvalidArgumentException;
use JsonException;

final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {}

    /** @param array<string, string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers]);
    }

    /** @param array<string, string> $headers */
    public static function redirect(string $location, int $status = 302, array $headers = []): self
    {
        if (!\in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new InvalidArgumentException('Invalid redirect status.');
        }

        return new self('', $status, ['Location' => $location, ...$headers]);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @throws JsonException
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8', ...$headers],
        );
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", true);
        }

        echo $this->body;
    }
}
