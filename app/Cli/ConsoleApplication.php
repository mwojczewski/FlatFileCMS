<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use Throwable;

final readonly class ConsoleApplication
{
    public function __construct(private BlockScaffolder $blocks) {}

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        $command = $arguments[1] ?? 'help';
        if (in_array($command, ['help', '--help', '-h'], true)) {
            $this->output($this->help());

            return 0;
        }
        if ($command !== 'block:create') {
            $this->error(sprintf("Unknown command \"%s\".\n\n%s", $command, $this->help()));

            return 2;
        }

        $type = null;
        $withAssets = false;
        foreach (array_slice($arguments, 2) as $argument) {
            if ($argument === '--with-assets') {
                $withAssets = true;

                continue;
            }
            if (str_starts_with($argument, '-')) {
                $this->error(sprintf("Unknown option \"%s\".\n", $argument));

                return 2;
            }
            if ($type !== null) {
                $this->error("Command block:create accepts exactly one block type.\n");

                return 2;
            }

            $type = $argument;
        }
        if ($type === null) {
            $this->error("Usage: php bin/cms block:create <type> [--with-assets]\n");

            return 2;
        }

        try {
            $files = $this->blocks->create($type, $withAssets);
        } catch (Throwable $exception) {
            $this->error('Error: ' . $exception->getMessage() . "\n");

            return 1;
        }

        $this->output(sprintf("Block \"%s\" created:\n", $type));
        foreach ($files as $file) {
            $this->output('  - ' . $file . "\n");
        }

        return 0;
    }

    private function help(): string
    {
        return <<<'TEXT'
FlatFile CMS CLI

Usage:
  php bin/cms <command> [arguments] [options]

Commands:
  block:create <type> [--with-assets]  Create a developer block package

TEXT;
    }

    private function output(string $message): void
    {
        fwrite(STDOUT, $message);
    }

    private function error(string $message): void
    {
        fwrite(STDERR, $message);
    }
}
