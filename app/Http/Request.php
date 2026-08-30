<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $parsedBody
     * @param array<string, string> $attributes
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $headers = [],
        private array $query = [],
        private array $parsedBody = [],
        private string $rawBody = '',
        private array $attributes = [],
        private string $clientIp = 'unknown',
    ) {}

    public static function fromGlobals(): self
    {
        $serverMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $method = \is_string($serverMethod) ? strtoupper($serverMethod) : 'GET';
        $serverUri = $_SERVER['REQUEST_URI'] ?? null;
        $uri = \is_string($serverUri) ? $serverUri : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!\is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $serverKey => $name) {
            if (isset($_SERVER[$serverKey]) && \is_string($_SERVER[$serverKey])) {
                $headers[$name] = $_SERVER[$serverKey];
            }
        }

        $rawBody = file_get_contents('php://input');
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        return new self(
            method: $method,
            path: self::normalizePath(\is_string($path) ? $path : '/'),
            headers: $headers,
            query: self::stringKeyedArray($_GET),
            parsedBody: self::stringKeyedArray($_POST),
            rawBody: \is_string($rawBody) ? $rawBody : '',
            clientIp: \is_string($remoteAddress) && $remoteAddress !== '' ? $remoteAddress : 'unknown',
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }

    /** @return array<string, mixed> */
    public function parsedBody(): array
    {
        return $this->parsedBody;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function clientIp(): string
    {
        return $this->clientIp;
    }

    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    /** @param array<string, string> $attributes */
    public function withAttributes(array $attributes): self
    {
        return new self(
            method: $this->method,
            path: $this->path,
            headers: $this->headers,
            query: $this->query,
            parsedBody: $this->parsedBody,
            rawBody: $this->rawBody,
            attributes: [...$this->attributes, ...$attributes],
            clientIp: $this->clientIp,
        );
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Superglobals are typed as array<mixed> by static analysers. HTTP field
     * names are strings; numeric keys are not part of this request contract.
     *
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (\is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
