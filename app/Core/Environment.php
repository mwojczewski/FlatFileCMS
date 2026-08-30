<?php

declare(strict_types=1);

namespace FlatFileCms\Core;

use RuntimeException;

final readonly class Environment
{
    /** @param array<string, string> $values */
    private function __construct(
        private string $projectRoot,
        private array $values,
    ) {}

    public static function load(string $projectRoot): self
    {
        $values = [];
        $localFile = "{$projectRoot}/.env.local";

        if (is_file($localFile)) {
            $lines = file($localFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                throw new RuntimeException('Unable to read local environment file.');
            }

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                if ($key === '') {
                    continue;
                }

                $values[$key] = self::unquote(trim($value));
            }
        }

        foreach (getenv() as $key => $systemValue) {
            $values[$key] = $systemValue;
        }

        return new self(rtrim($projectRoot, DIRECTORY_SEPARATOR), $values);
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    public function name(): string
    {
        return $this->get('APP_ENV', 'production');
    }

    public function debug(): bool
    {
        return $this->boolean('APP_DEBUG', false);
    }

    public function boolean(string $key, bool $default): bool
    {
        $value = $this->get($key, $default ? '1' : '0');
        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new RuntimeException(\sprintf('Environment variable "%s" must be boolean.', $key));
        }

        return $parsed;
    }

    public function integer(string $key, int $default, int $minimum = 1): int
    {
        $value = $this->get($key, (string) $default);
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]);
        if (!\is_int($parsed)) {
            throw new RuntimeException(\sprintf('Environment variable "%s" must be an integer of at least %d.', $key, $minimum));
        }

        return $parsed;
    }

    public function get(string $key, ?string $default = null): string
    {
        if (\array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        if ($default !== null) {
            return $default;
        }

        throw new RuntimeException(\sprintf('Required environment variable "%s" is missing.', $key));
    }

    private static function unquote(string $value): string
    {
        if (\strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[\strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
