<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\User;
use FlatFileCms\Collections\CollectionManager;
use FlatFileCms\Collections\CollectionSettings;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\InvalidContentException;
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
use JsonException;

final readonly class AdminCollectionController
{
    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private LanguageRepository $languages,
        private CollectionManager $manager,
        private LayoutRegistry $layouts,
        private AdminView $views,
        private AdminLayout $layout,
        private AuditLogger $audit,
    ) {}

    public function edit(Request $request): Response
    {
        $this->requireUser();
        $identity = $this->identity($request->query()['path'] ?? null);
        try {
            $editable = $this->manager->editable($identity);
        } catch (FilesystemException $exception) {
            throw new HttpException(404, 'COLLECTION_NOT_FOUND', 'Collection not found.', previous: $exception);
        }

        return $this->layout->render('Edycja kolekcji', $this->views->render('collections/form', [
            'identity' => $identity,
            'editable' => $editable,
            'languages' => $this->languages->get(),
            'layouts' => array_keys($this->layouts->all()),
            'csrfToken' => $this->csrf->token(),
        ]), active: 'pages');
    }

    public function update(Request $request): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $identity = $this->identity($request->parsedBody()['identity'] ?? null);
            $languages = $this->languages->get();
            $this->manager->update(
                $identity,
                $this->settings($request, $languages),
                $this->revision($request->parsedBody()['revision'] ?? null),
                $languages,
            );
            $this->audit->log('collection.updated', $actor->id(), "pages/{$identity->value()}", $request->clientIp());

            return Response::redirect('/admin/collections/edit?path=' . rawurlencode($identity->value()) . '&saved=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'COLLECTION_REVISION_CONFLICT', 'Collection changed in another session.', previous: $exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException | JsonException $exception) {
            throw new HttpException(422, 'COLLECTION_UPDATE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    private function settings(Request $request, LanguageConfig $languages): CollectionSettings
    {
        $body = $request->parsedBody();
        $layout = $this->string($body['layout'] ?? null, 'Layout');
        $sortField = $this->string($body['sort_field'] ?? null, 'Sort field');
        $sortDirection = $this->string($body['sort_direction'] ?? null, 'Sort direction');
        $perPageValue = $this->string($body['per_page'] ?? null, 'Items per page');
        if (preg_match('/^[1-9][0-9]{0,2}$/D', $perPageValue) !== 1) {
            throw new InvalidArgumentException('Items per page must be an integer between 1 and 100.');
        }
        $canonical = trim($this->string($body['canonical'] ?? null, 'Canonical'));
        if ($canonical !== '' && str_starts_with($canonical, '//')) {
            throw new InvalidArgumentException('Canonical site path cannot start with two slashes.');
        }
        if ($canonical !== '' && !str_starts_with($canonical, '/')) {
            $scheme = parse_url($canonical, PHP_URL_SCHEME);
            if (filter_var($canonical, FILTER_VALIDATE_URL) === false || !\in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException('Canonical URL must be an HTTP(S) URL or an absolute site path.');
            }
        }

        return new CollectionSettings(
            ($body['enabled'] ?? null) === '1',
            $layout,
            $this->localized($body['slug'] ?? null, $languages, true),
            $this->localized($body['title'] ?? null, $languages, true),
            $this->localized($body['seo_title'] ?? null, $languages, false),
            $this->localized($body['seo_description'] ?? null, $languages, false),
            $canonical === '' ? null : $canonical,
            ($body['robots_index'] ?? null) === '1',
            ($body['robots_follow'] ?? null) === '1',
            $sortField,
            $sortDirection,
            (int) $perPageValue,
            $this->filters($body['filters'] ?? null),
        );
    }

    /** @return list<array{parameter: string, field: string, allowedValues: list<string>}> */
    private function filters(mixed $value): array
    {
        if (!\is_string($value) || \strlen($value) > 131_072) {
            throw new InvalidArgumentException('Collection filters must be bounded JSON.');
        }
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($decoded) || !array_is_list($decoded)) {
            throw new InvalidArgumentException('Collection filters must be a JSON array.');
        }
        $filters = [];
        foreach ($decoded as $item) {
            if (!\is_array($item) || array_is_list($item)) {
                throw new InvalidArgumentException('Each collection filter must be an object.');
            }
            $parameter = $item['parameter'] ?? null;
            $field = $item['field'] ?? null;
            $allowed = $item['allowedValues'] ?? [];
            if (!\is_string($parameter) || !\is_string($field) || !\is_array($allowed) || !array_is_list($allowed)) {
                throw new InvalidArgumentException('Collection filter structure is invalid.');
            }
            $values = [];
            foreach ($allowed as $allowedValue) {
                if (!\is_string($allowedValue)) {
                    throw new InvalidArgumentException('Collection filter allowed values must be strings.');
                }
                $values[] = $allowedValue;
            }
            $filters[] = ['parameter' => $parameter, 'field' => $field, 'allowedValues' => $values];
        }

        return $filters;
    }

    /** @return array<string, string> */
    private function localized(mixed $value, LanguageConfig $languages, bool $required): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException('Localized value must be a mapping.');
        }
        $result = [];
        foreach ($languages->codes() as $locale) {
            $localized = $value[$locale] ?? null;
            if (!\is_string($localized)) {
                throw new InvalidArgumentException("Localized value for {$locale} is invalid.");
            }
            $localized = trim($localized);
            if ($required && $locale === $languages->default() && $localized === '') {
                throw new InvalidArgumentException("Localized value for {$locale} is invalid.");
            }
            if ($localized === '' && $locale !== $languages->default()) {
                continue;
            }
            $result[$locale] = $localized;
        }

        return $result;
    }

    private function identity(mixed $value): PageIdentity
    {
        if (!\is_string($value)) {
            throw new HttpException(400, 'COLLECTION_IDENTITY_REQUIRED', 'Collection identity is required.');
        }

        return PageIdentity::fromString(trim($value, '/'));
    }

    private function revision(mixed $value): FileRevision
    {
        if (!\is_string($value)) {
            throw new InvalidArgumentException('Collection revision is required.');
        }

        return FileRevision::fromString($value);
    }

    private function string(mixed $value, string $label): string
    {
        if (!\is_string($value)) {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return trim($value);
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
}
