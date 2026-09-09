<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use Closure;
use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Core\Environment;
use FlatFileCms\Core\ProductionGuard;
use FlatFileCms\Navigation\NavigationRepository;
use FlatFileCms\Redirects\RedirectRepository;
use FlatFileCms\Rendering\LayoutRegistry;
use PDO;
use Throwable;

final readonly class ReleaseChecker
{
    public function __construct(
        private string $projectRoot,
        private Environment $environment,
        private ConfigurationRepository $configuration,
        private LanguageRepository $languages,
        private PageRepository $pages,
        private CollectionRepository $collections,
        private NavigationRepository $navigation,
        private RedirectRepository $redirects,
        private BlockRegistry $blocks,
        private BlockProcessor $blockProcessor,
        private LayoutRegistry $layouts,
        private PDO $database,
    ) {}

    /** @return list<ReleaseCheckResult> */
    public function run(): array
    {
        return [
            $this->check('PHP', static function (): string {
                return PHP_VERSION;
            }),
            $this->check('Extensions', function (): string {
                $required = ['ctype', 'dom', 'fileinfo', 'filter', 'gd', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_sqlite'];
                $missing = array_values(array_filter($required, static fn(string $name): bool => !\extension_loaded($name)));
                if ($missing !== []) {
                    throw new \RuntimeException('Missing: ' . implode(', ', $missing));
                }

                return 'all required extensions loaded';
            }),
            $this->check('Production environment', function (): string {
                ProductionGuard::assertProduction($this->environment);

                return 'debug off, strong secret, secure session cookie';
            }),
            $this->check('Filesystem permissions', fn(): string => $this->filesystem()),
            $this->check('Configuration and content', fn(): string => $this->content()),
            $this->check('Authentication database', fn(): string => $this->database()),
            $this->check('Release files', fn(): string => $this->releaseFiles()),
        ];
    }

    /** @param Closure(): string $callback */
    private function check(string $name, Closure $callback): ReleaseCheckResult
    {
        try {
            return new ReleaseCheckResult($name, true, $callback());
        } catch (Throwable $exception) {
            return new ReleaseCheckResult($name, false, $exception->getMessage());
        }
    }

    private function filesystem(): string
    {
        foreach ([
            'pages',
            'config',
            'storage/audit',
            'storage/cache',
            'storage/database',
            'storage/logs',
            'storage/sessions',
            'storage/tmp',
            'public/assets/blocks',
        ] as $relative) {
            $path = "{$this->projectRoot}/{$relative}";
            if (!is_dir($path) || is_link($path) || !is_writable($path)) {
                throw new \RuntimeException("Directory {$relative} must exist, be writable and not be a symlink.");
            }
        }

        return 'runtime and content directories writable';
    }

    private function content(): string
    {
        $configuration = $this->configuration->get();
        $site = $configuration->data()['site'] ?? null;
        if (!\is_array($site)) {
            throw new \RuntimeException('Site configuration is missing.');
        }
        $siteUrl = $site['url'] ?? null;
        if (!\is_string($siteUrl) || !str_starts_with($siteUrl, 'https://')) {
            throw new \RuntimeException('site.url must use HTTPS in production.');
        }
        $languages = $this->languages->get();
        $pages = $this->pages->all($languages);
        $collections = $this->collections->all($languages);
        $routes = PageRouteIndex::build($pages, $languages, $collections);
        foreach ($languages->codes() as $locale) {
            $this->navigation->resolve($locale, $languages, $routes);
        }
        foreach ($pages as $page) {
            foreach ($languages->codes() as $locale) {
                $this->blockProcessor->forPublicPage($page, $locale, $languages);
            }
        }
        $this->redirects->get();
        $this->blocks->all();
        $layouts = $this->layouts->all();
        if ($layouts === []) {
            throw new \RuntimeException('At least one layout is required.');
        }
        $defaultLayout = $site['defaultLayout'] ?? null;
        if (!\is_string($defaultLayout) || !isset($layouts[$defaultLayout])) {
            throw new \RuntimeException('Configured default layout does not exist.');
        }

        return \sprintf('%d page(s), %d collection(s), %d block type(s)', \count($pages), \count($collections), \count($this->blocks->all()));
    }

    private function database(): string
    {
        $tables = $this->database->query("SELECT name FROM sqlite_master WHERE type = 'table'");
        if ($tables === false) {
            throw new \RuntimeException('Unable to inspect SQLite schema.');
        }
        $names = $tables->fetchAll(PDO::FETCH_COLUMN);
        foreach (['users', 'auth_rate_limits', 'password_reset_tokens', 'webauthn_credentials'] as $required) {
            if (!\in_array($required, $names, true)) {
                throw new \RuntimeException("Database migration is missing table {$required}.");
            }
        }

        return 'authentication schema installed';
    }

    private function releaseFiles(): string
    {
        $requiredFiles = [
            'composer.lock',
            'config/redirects.yml',
            'public/index.php',
            'public/.htaccess',
            'public/assets/admin/admin.css',
            'public/assets/admin/admin.js',
            'public/assets/css/site.css',
            'public/assets/css/typography.css',
            'templates/admin/layout/authenticated.php',
            'templates/admin/layout/authentication.php',
            'templates/admin/layout/document.php',
            'deploy/nginx.conf.example',
        ];

        foreach ($requiredFiles as $relative) {
            $path = "{$this->projectRoot}/{$relative}";
            if (!is_file($path) || is_link($path)) {
                throw new \RuntimeException("Required release file {$relative} is missing or unsafe.");
            }
        }
        if (is_file("{$this->projectRoot}/public/.env") || is_file("{$this->projectRoot}/public/.env.local")) {
            throw new \RuntimeException('Environment files must not exist below public/.');
        }

        return 'entrypoint, admin views, assets, rewrites, lockfile and server example present';
    }
}
