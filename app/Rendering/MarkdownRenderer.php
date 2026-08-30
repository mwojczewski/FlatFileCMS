<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

final readonly class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }
}
