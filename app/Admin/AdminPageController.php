<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\User;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\EditablePage;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageManager;
use FlatFileCms\Content\PageMetadata;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Rendering\LayoutRegistry;
use InvalidArgumentException;

final readonly class AdminPageController
{
    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private LanguageRepository $languages,
        private PageRepository $pages,
        private CollectionRepository $collections,
        private PageManager $manager,
        private LayoutRegistry $layouts,
        private ConfigurationRepository $configuration,
        private AdminView $views,
        private AdminLayout $layout,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->requireUser();
        $languages = $this->languages->get();
        /** @var array<string, array{identity: PageIdentity, title: string, enabled: bool, collection: bool}> $entries */
        $entries = [];
        foreach ($this->pages->all($languages) as $page) {
            $entries[$page->identity()->value()] = [
                'identity' => $page->identity(),
                'title' => $page->title($languages->default(), $languages->default()),
                'enabled' => $page->enabled(),
                'collection' => false,
            ];
        }
        foreach ($this->collections->all($languages) as $collection) {
            $entries[$collection->identity()->value()] = [
                'identity' => $collection->identity(),
                'title' => $collection->title($languages->default(), $languages->default()),
                'enabled' => $collection->enabled(),
                'collection' => true,
            ];
        }
        uksort($entries, static function (string $left, string $right): int {
            if ($left === 'homepage') {
                return -1;
            }
            if ($right === 'homepage') {
                return 1;
            }

            return $left <=> $right;
        });
        return $this->page('Strony', $this->views->render('pages/index', ['entries' => array_values($entries)]));
    }

    public function createForm(Request $request): Response
    {
        $this->requireUser();
        $languages = $this->languages->get();
        $parent = $request->query()['parent'] ?? '';
        if (!\is_string($parent)) {
            $parent = '';
        }

        $identityPrefix = $parent === '' ? '' : trim($parent, '/') . '/';

        return $this->page('Nowa strona', $this->views->render('pages/form', $this->formData(
            '/admin/pages/create',
            $languages,
            null,
            null,
            $identityPrefix,
        )), pageFormScript: true);
    }

    public function create(Request $request): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $languages = $this->languages->get();
            $identity = $this->identity($request->parsedBody()['identity'] ?? null);
            $this->manager->create($identity, $this->metadata($request, $languages, false), $languages);
            $this->audit->log('page.created', $actor->id(), "pages/{$identity->value()}", $request->clientIp());

            return Response::redirect('/admin/pages?created=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.', previous: $exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(422, 'PAGE_CREATE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function editForm(Request $request): Response
    {
        $this->requireUser();
        $identity = $this->queryIdentity($request);
        try {
            $editable = $this->manager->editable($identity);
        } catch (FilesystemException $exception) {
            throw new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found.', previous: $exception);
        }

        $languages = $this->languages->get();

        return $this->page('Edycja strony', $this->views->render('pages/edit', [
            'identity' => $identity,
            'form' => $this->views->render('pages/form', $this->formData(
                '/admin/pages/update',
                $languages,
                $editable,
                $identity,
            )),
        ]));
    }

    public function update(Request $request): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $languages = $this->languages->get();
            $identity = $this->identity($request->parsedBody()['identity'] ?? null);
            $revision = $this->revision($request->parsedBody()['revision'] ?? null);
            $this->manager->update(
                $identity,
                $this->metadata($request, $languages, $identity->isHomepage()),
                $revision,
                $languages,
            );
            $this->audit->log('page.updated', $actor->id(), "pages/{$identity->value()}", $request->clientIp());

            return Response::redirect('/admin/pages/edit?path=' . rawurlencode($identity->value()) . '&saved=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.', previous: $exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(422, 'PAGE_UPDATE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function move(Request $request): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $source = $this->identity($request->parsedBody()['identity'] ?? null);
            $destination = $this->identity($request->parsedBody()['destination'] ?? null);
            $this->manager->move(
                $source,
                $destination,
                $this->revision($request->parsedBody()['revision'] ?? null),
                $this->languages->get(),
            );
            $this->audit->log(
                'page.moved',
                $actor->id(),
                "pages/{$destination->value()}",
                $request->clientIp(),
                ['from' => $source->value()],
            );

            return Response::redirect('/admin/pages/edit?path=' . rawurlencode($destination->value()) . '&moved=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.', previous: $exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(422, 'PAGE_MOVE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function delete(Request $request): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            if (($request->parsedBody()['confirmation'] ?? null) !== 'delete') {
                throw new InvalidArgumentException('Deletion must be explicitly confirmed.');
            }
            $identity = $this->identity($request->parsedBody()['identity'] ?? null);
            $this->manager->delete(
                $identity,
                $this->revision($request->parsedBody()['revision'] ?? null),
            );
            $this->audit->log('page.deleted', $actor->id(), "pages/{$identity->value()}", $request->clientIp());

            return Response::redirect('/admin/pages?deleted=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.', previous: $exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(422, 'PAGE_DELETE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    private function queryIdentity(Request $request): PageIdentity
    {
        return $this->identity($request->query()['path'] ?? null);
    }

    private function identity(mixed $value): PageIdentity
    {
        if (!\is_string($value)) {
            throw new HttpException(400, 'PAGE_IDENTITY_REQUIRED', 'Page identity is required.');
        }

        try {
            return PageIdentity::fromString(trim($value, '/'));
        } catch (InvalidArgumentException $exception) {
            throw new HttpException(400, 'PAGE_IDENTITY_INVALID', 'Page identity is invalid.', previous: $exception);
        }
    }

    private function revision(mixed $value): FileRevision
    {
        if (!\is_string($value)) {
            throw new HttpException(400, 'PAGE_REVISION_REQUIRED', 'Page revision is required.');
        }

        try {
            return FileRevision::fromString($value);
        } catch (InvalidArgumentException $exception) {
            throw new HttpException(400, 'PAGE_REVISION_INVALID', 'Page revision is invalid.', previous: $exception);
        }
    }

    private function metadata(Request $request, LanguageConfig $languages, bool $homepage): PageMetadata
    {
        $data = $request->parsedBody();
        $layout = $data['layout'] ?? null;
        $canonical = $data['canonical'] ?? null;
        if (!\is_string($layout) || !\is_string($canonical)) {
            throw new InvalidArgumentException('Page form contains invalid scalar values.');
        }

        return new PageMetadata(
            ($data['enabled'] ?? null) === '1',
            $layout === '' ? null : $layout,
            $this->localizedStrings($data['title'] ?? null, $languages, true),
            $homepage ? [] : $this->localizedStrings($data['slug'] ?? null, $languages, true),
            $this->localizedStrings($data['seo_title'] ?? null, $languages, false),
            $this->localizedStrings($data['seo_description'] ?? null, $languages, false),
            trim($canonical) === '' ? null : trim($canonical),
            ($data['robots_index'] ?? null) === '1',
            ($data['robots_follow'] ?? null) === '1',
        );
    }

    /** @return array<string, string> */
    private function localizedStrings(mixed $value, LanguageConfig $languages, bool $required): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException('Localized form value must be a mapping.');
        }
        $result = [];
        foreach ($languages->codes() as $locale) {
            $localized = $value[$locale] ?? null;
            if (!\is_string($localized)) {
                throw new InvalidArgumentException('Localized form value is missing.');
            }
            $localized = trim($localized);
            if ($required && $locale === $languages->default() && $localized === '') {
                throw new InvalidArgumentException(\sprintf('Value for locale "%s" is required.', $locale));
            }
            if ($localized === '' && $locale !== $languages->default()) {
                continue;
            }
            $result[$locale] = $localized;
        }

        return $result;
    }

    private function validateCsrf(Request $request): void
    {
        try {
            $this->csrf->validate($request->parsedBody()['_csrf'] ?? null);
        } catch (AuthenticationException $exception) {
            throw new HttpException(403, 'CSRF_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    private function requireUser(): User
    {
        try {
            return $this->authenticator->requireUser();
        } catch (AuthenticationException $exception) {
            throw new HttpException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required.', previous: $exception);
        }
    }

    /** @return array<string, mixed> */
    private function formData(
        string $action,
        LanguageConfig $languages,
        ?EditablePage $editable,
        ?PageIdentity $identity,
        string $identityPrefix = '',
    ): array {
        $configuration = $this->configuration->get()->data();
        $site = $this->mapping($configuration['site'] ?? []);

        return [
            'action' => $action,
            'languages' => $languages,
            'editable' => $editable,
            'identity' => $identity,
            'identityPrefix' => $identityPrefix,
            'layouts' => array_keys($this->layouts->all()),
            'csrfToken' => $this->csrf->token(),
            'siteUrl' => \is_string($site['url'] ?? null) ? rtrim($site['url'], '/') : '',
            'canonicalBasePath' => $this->canonicalBasePath($identityPrefix, $languages),
        ];
    }

    private function canonicalBasePath(string $identityPrefix, LanguageConfig $languages): string
    {
        $prefix = $languages->isMultilingual() ? "/{$languages->default()}" : '';
        if ($identityPrefix === '') {
            return $prefix;
        }
        $parent = PageIdentity::fromString(trim($identityPrefix, '/'));
        $pages = $this->pages->all($languages);
        $collections = $this->collections->all($languages);
        $routes = PageRouteIndex::build($pages, $languages, $collections);
        foreach ($pages as $page) {
            if ($page->identity()->value() === $parent->value()) {
                return $prefix . '/' . $routes->pathFor($parent, $languages->default());
            }
        }
        foreach ($collections as $collection) {
            if ($collection->identity()->value() === $parent->value()) {
                return $prefix . '/' . $routes->collectionPathFor($parent, $languages->default());
            }
        }

        throw new HttpException(404, 'PARENT_NOT_FOUND', 'Parent page or collection not found.');
    }

    /** @return array<string, mixed> */
    private function mapping(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function page(string $title, string $content, bool $pageFormScript = false): Response
    {
        return $this->layout->render($title, $content, active: 'pages', pageFormScript: $pageFormScript);
    }
}
