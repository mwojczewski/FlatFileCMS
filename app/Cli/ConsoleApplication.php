<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use Closure;
use FlatFileCms\Auth\Role;
use InvalidArgumentException;
use Throwable;

final readonly class ConsoleApplication
{
    /** @param Closure(): UserCommandService $users */
    public function __construct(
        private BlockScaffolder $blocks,
        private Closure $users,
        private PasswordReader $passwords,
        private CacheClearer $cache,
    ) {}

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        $command = $arguments[1] ?? 'help';
        if (\in_array($command, ['help', '--help', '-h'], true)) {
            $this->output($this->help());

            return 0;
        }

        try {
            return match ($command) {
                'block:create' => $this->createBlock(\array_slice($arguments, 2)),
                'cache:clear' => $this->clearCache(\array_slice($arguments, 2)),
                'install' => $this->createUser($arguments[2] ?? null, Role::Superadmin, install: true),
                'user:create' => $this->createUser($arguments[2] ?? null, Role::Admin),
                'user:create-superadmin' => $this->createUser($arguments[2] ?? null, Role::Superadmin),
                'user:password' => $this->changePassword($arguments[2] ?? null),
                'user:security-keys:clear' => $this->clearSecurityKeys($arguments[2] ?? null),
                default => $this->unknown($command),
            };
        } catch (Throwable $exception) {
            $this->error('Error: ' . $exception->getMessage() . "\n");

            return 1;
        }
    }

    /** @param list<string> $arguments */
    private function createBlock(array $arguments): int
    {
        $type = null;
        $withAssets = false;
        foreach ($arguments as $argument) {
            if ($argument === '--with-assets') {
                $withAssets = true;
            } elseif (str_starts_with($argument, '-') || $type !== null) {
                throw new InvalidArgumentException('Usage: php bin/cms block:create <type> [--with-assets]');
            } else {
                $type = $argument;
            }
        }
        if ($type === null) {
            throw new InvalidArgumentException('Usage: php bin/cms block:create <type> [--with-assets]');
        }
        $files = $this->blocks->create($type, $withAssets);
        $this->output(\sprintf("Block \"%s\" created:\n", $type));
        foreach ($files as $file) {
            $this->output("  - {$file}\n");
        }

        return 0;
    }

    private function createUser(?string $email, Role $role, bool $install = false): int
    {
        $email ??= throw new InvalidArgumentException('Email argument is required.');
        $password = $this->passwords->read();
        if ($install) {
            $this->users()->install($email, $password);
            $this->output("CMS database installed and first superadmin created.\n");
        } else {
            $this->users()->create($email, $password, $role);
            $this->output(\sprintf("%s created.\n", $role->value));
        }

        return 0;
    }

    private function changePassword(?string $email): int
    {
        $email ??= throw new InvalidArgumentException('Email argument is required.');
        $this->users()->changePassword($email, $this->passwords->read());
        $this->output("Password changed.\n");

        return 0;
    }

    private function clearSecurityKeys(?string $email): int
    {
        $email ??= throw new InvalidArgumentException('Email argument is required.');
        $count = $this->users()->clearSecurityKeys($email);
        $this->output(\sprintf("Removed %d security key(s).\n", $count));

        return 0;
    }

    /** @param list<string> $arguments */
    private function clearCache(array $arguments): int
    {
        if ($arguments !== []) {
            throw new InvalidArgumentException('Usage: php bin/cms cache:clear');
        }

        $count = $this->cache->clear();
        $this->output(\sprintf("Cache cleared. Removed %d item(s).\n", $count));

        return 0;
    }

    private function unknown(string $command): int
    {
        $this->error(\sprintf("Unknown command \"%s\".\n\n%s", $command, $this->help()));

        return 2;
    }

    private function users(): UserCommandService
    {
        return ($this->users)();
    }

    private function help(): string
    {
        return <<<'TEXT'
FlatFile CMS CLI

Usage:
  php bin/cms <command> [arguments]

Commands:
  install <email>                         Install SQLite and create the first superadmin
  user:create <email>                     Create an admin
  user:create-superadmin <email>          Create a technical superadmin
  user:password <email>                   Change a user password
  user:security-keys:clear <email>         Remove all WebAuthn/YubiKey credentials
  block:create <type> [--with-assets]      Create a developer block package
  cache:clear                              Remove all generated cache entries

Set CMS_PASSWORD for non-interactive use. Avoid shell history and process arguments.
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
