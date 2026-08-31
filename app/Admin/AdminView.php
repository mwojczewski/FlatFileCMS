<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Rendering\OutputBuffer;
use InvalidArgumentException;

final readonly class AdminView
{
    private string $root;

    public function __construct(string $projectRoot, private OutputBuffer $buffer)
    {
        $this->root = "{$projectRoot}/templates/admin";
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = []): string
    {
        if (preg_match('/^[a-z0-9]+(?:[\/_-][a-z0-9]+)*$/D', $view) !== 1) {
            throw new InvalidArgumentException('Admin view name is invalid.');
        }

        $path = "{$this->root}/{$view}.php";
        if (!is_file($path) || is_link($path)) {
            throw new InvalidArgumentException("Admin view \"{$view}\" does not exist.");
        }

        $escape = self::escape(...);

        return $this->buffer->capture(static function () use ($path, $data, $escape): void {
            extract($data, EXTR_SKIP);
            require $path;
        });
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
