<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

final readonly class PartialRenderer
{
    public function __construct(
        private PartialRegistry $registry,
        private OutputBuffer $buffer,
    ) {}

    /** @param array<string, mixed> $data */
    public function render(string $name, array $data, RenderContext $context): string
    {
        $template = $this->registry->get($name);

        return $this->buffer->capture(static function () use ($template, $data, $context): void {
            require $template;
        });
    }

    public function modifiedAt(): int
    {
        return $this->registry->modifiedAt();
    }
}
