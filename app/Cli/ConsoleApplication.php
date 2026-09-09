<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use Closure;
use FlatFileCms\Auth\Role;
use InvalidArgumentException;
use Throwable;

final readonly class ConsoleApplication
{
    /**
     * @param Closure(): UserCommandService $users
     * @param Closure(): ReleaseChecker $release
     */
    public function __construct(
        private BlockScaffolder $blocks,
        private Closure $users,
        private PasswordReader $passwords,
        private CacheClearer $cache,
        private CachePruner $cachePruner,
        private RuntimePruner $runtimePruner,
        private Closure $release,
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
                'cache:prune' => $this->pruneCache(\array_slice($arguments, 2)),
                'runtime:prune' => $this->pruneRuntime(\array_slice($arguments, 2)),
                'database:migrate' => $this->migrateDatabase(\array_slice($arguments, 2)),
                'release:check' => $this->releaseCheck(\array_slice($arguments, 2)),
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

    /** @param list<string> $arguments */
    private function pruneCache(array $arguments): int
    {
        $options = $this->pruneOptions($arguments, ['assets' => 604800, 'cache' => 2592000]);
        $result = $this->cachePruner->prune(
            $options['dryRun'],
            $options['ages']['assets'],
            $options['ages']['cache'],
        );
        $prefix = $options['dryRun'] ? 'Cache prune dry run' : 'Cache pruned';
        $this->output(\sprintf("%s. %d file(s), %d byte(s).\n", $prefix, $result->files, $result->bytes));

        return 0;
    }

    /** @param list<string> $arguments */
    private function pruneRuntime(array $arguments): int
    {
        $options = $this->pruneOptions($arguments, ['sessions' => 86400]);
        $result = $this->runtimePruner->prune($options['dryRun'], $options['ages']['sessions']);
        $prefix = $options['dryRun'] ? 'Runtime prune dry run' : 'Runtime pruned';
        $this->output(\sprintf("%s. %d file(s), %d byte(s).\n", $prefix, $result->files, $result->bytes));

        return 0;
    }

    /**
     * @param list<string> $arguments
     * @param array<string, int> $defaults
     * @return array{dryRun: bool, ages: array<string, int>}
     */
    private function pruneOptions(array $arguments, array $defaults): array
    {
        $dryRun = false;
        foreach ($arguments as $argument) {
            if ($argument === '--dry-run') {
                $dryRun = true;
                continue;
            }
            if (preg_match('/^--([a-z]+)-older-than=(.+)$/D', $argument, $matches) !== 1 || !isset($defaults[$matches[1]])) {
                throw new InvalidArgumentException('Invalid prune option. See php bin/cms help.');
            }
            $defaults[$matches[1]] = $this->duration($matches[2]);
        }

        return ['dryRun' => $dryRun, 'ages' => $defaults];
    }

    private function duration(string $value): int
    {
        if (preg_match('/^([1-9][0-9]*)([smhd])$/D', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Duration must use a positive integer followed by s, m, h or d.');
        }
        $multiplier = match ($matches[2]) {
            's' => 1,
            'm' => 60,
            'h' => 3600,
            'd' => 86400,
        };
        $seconds = (int) $matches[1] * $multiplier;
        if ($seconds < 1 || $seconds > 31536000) {
            throw new InvalidArgumentException('Duration must not exceed 365 days.');
        }

        return $seconds;
    }

    /** @param list<string> $arguments */
    private function migrateDatabase(array $arguments): int
    {
        if ($arguments !== []) {
            throw new InvalidArgumentException('Usage: php bin/cms database:migrate');
        }

        $this->users()->migrate();
        $this->output("Database schema is up to date.\n");

        return 0;
    }

    /** @param list<string> $arguments */
    private function releaseCheck(array $arguments): int
    {
        if ($arguments !== []) {
            throw new InvalidArgumentException('Usage: php bin/cms release:check');
        }

        $failed = false;
        foreach (($this->release)()->run() as $result) {
            $this->output(($result->passed ? '[OK]   ' : '[FAIL] ') . "{$result->name}: {$result->message}\n");
            $failed = $failed || !$result->passed;
        }

        return $failed ? 1 : 0;
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
        $help = <<<'TEXT'
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
  cache:prune [--dry-run]                  Remove expired block assets and cache files
    [--assets-older-than=7d] [--cache-older-than=30d]
  runtime:prune [--dry-run]                Remove expired session files
    [--sessions-older-than=1d]
  database:migrate                         Apply authentication database schema changes
  release:check                            Validate production runtime, content and deployment

Set CMS_PASSWORD for non-interactive use. Avoid shell history and process arguments.
TEXT;

        return $help . "\n";
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
