<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\User;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\ConfigurationManager;
use FlatFileCms\Config\GlobalConfigurationInput;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Config\SiteTextRepository;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Media\MediaConfig;
use FlatFileCms\Media\MediaTypes;
use FlatFileCms\Navigation\NavigationManager;
use FlatFileCms\Rendering\LayoutRegistry;
use InvalidArgumentException;
use JsonException;

final readonly class AdminSettingsController
{
    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private LanguageRepository $languages,
        private PageRepository $pages,
        private CollectionRepository $collections,
        private NavigationManager $navigation,
        private ConfigurationManager $configuration,
        private SiteTextRepository $siteTexts,
        private LayoutRegistry $layouts,
        private AdminView $views,
        private AdminLayout $layout,
        private AuditLogger $audit,
    ) {}

    public function navigation(Request $request): Response
    {
        $this->requireUser();
        $document = $this->navigation->editable();
        $languages = $this->languages->get();
        $destinations = ['pages' => [], 'collections' => []];
        foreach ($this->pages->all($languages) as $page) {
            $destinations['pages'][] = [
                'id' => $page->identity()->value(),
                'label' => $page->title($languages->default(), $languages->default()),
            ];
        }
        foreach ($this->collections->all($languages) as $collection) {
            $destinations['collections'][] = [
                'id' => $collection->identity()->value(),
                'label' => $collection->title($languages->default(), $languages->default()),
            ];
        }

        $content = $this->views->render('settings/navigation', [
            'csrfToken' => $this->csrf->token(),
            'revision' => $document->revision()->value(),
            'navigation' => $document->data(),
            'languagesData' => [
                'default' => $languages->default(),
                'items' => $languages->languages(),
            ],
            'destinations' => $destinations,
        ]);

        return $this->layout->render('Nawigacja', $content, active: 'navigation', settingsScript: true);
    }

    public function updateNavigation(Request $request): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $payload = $request->parsedBody()['payload'] ?? null;
            if (!\is_string($payload) || $payload === '' || \strlen($payload) > 524_288) {
                throw new InvalidArgumentException('Navigation payload is missing or too large.');
            }
            $decoded = \json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            $data = $this->stringMapping($decoded, 'navigation');
            $this->navigation->update($data, $this->revision($request->parsedBody()['revision'] ?? null));
            $this->audit->log('navigation.updated', $actor->id(), 'config/navigation.yml', $request->clientIp());

            return Response::redirect('/admin/navigation?saved=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'NAVIGATION_REVISION_CONFLICT', 'Navigation changed in another session.', previous: $exception);
        } catch (InvalidArgumentException|InvalidContentException|JsonException $exception) {
            throw new HttpException(422, 'NAVIGATION_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function configuration(Request $request): Response
    {
        $this->requireUser();
        $document = $this->configuration->editable();
        $data = $document->data();
        $site = $this->mapping($data['site'] ?? []);
        $seo = $this->mapping($data['seo'] ?? []);
        $languages = $this->languages->get();
        $media = MediaConfig::fromDocument($document);
        $titleSuffix = $this->localizedStrings($seo['titleSuffix'] ?? '', $languages->codes());
        $description = $this->localizedStrings($seo['description'] ?? '', $languages->codes());
        $llms = $this->siteTexts->llms();
        $security = $this->siteTexts->security();
        $content = $this->views->render('settings/configuration', [
            'document' => $document,
            'site' => $site,
            'seo' => $seo,
            'languages' => $languages,
            'titleSuffix' => $titleSuffix,
            'description' => $description,
            'media' => $media,
            'layouts' => array_keys($this->layouts->all()),
            'mimeTypes' => MediaTypes::defaults(),
            'formats' => ['webp', 'avif'],
            'llms' => $llms,
            'security' => $security,
            'csrfToken' => $this->csrf->token(),
        ]);

        return $this->layout->render('Konfiguracja', $content, active: 'settings');
    }

    public function updateConfiguration(Request $request): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $body = $request->parsedBody();
            $languages = $this->languages->get();
            $input = new GlobalConfigurationInput(
                $this->requiredString($body['site_name'] ?? null, 'Site name'),
                $this->requiredString($body['site_url'] ?? null, 'Site URL'),
                $this->requiredString($body['default_layout'] ?? null, 'Default layout'),
                $this->localizedBody($body['seo_title_suffix'] ?? null, $languages),
                $this->localizedBody($body['seo_description'] ?? null, $languages),
                $this->optionalBodyString($body['seo_og_image'] ?? null),
                $this->jsonMapping($body['seo_open_graph'] ?? null, 'OpenGraph'),
                $this->jsonMapping($body['seo_twitter'] ?? null, 'Twitter'),
                $this->jsonArrayBody($body['seo_json_ld'] ?? null, 'JSON-LD'),
                $this->integer($body['media_max_upload_bytes'] ?? null, 'Maximum upload bytes'),
                $this->stringList($body['allowed_mime_types'] ?? null, 'Allowed MIME types'),
                ($body['strip_metadata'] ?? null) === '1',
                ($body['transformations_enabled'] ?? null) === '1',
                ($body['media_cache_enabled'] ?? null) === '1',
                $this->stringList($body['media_formats'] ?? null, 'Media formats'),
                $this->integer($body['media_quality'] ?? null, 'Media quality'),
                $this->integer($body['media_max_width'] ?? null, 'Maximum image width'),
                $this->integer($body['media_max_height'] ?? null, 'Maximum image height'),
                $this->integer($body['media_max_pixels'] ?? null, 'Maximum image pixels'),
            );
            $this->configuration->update($input, $this->revision($body['revision'] ?? null));
            $this->audit->log('config.updated', $actor->id(), 'config/setup.yml', $request->clientIp());

            return Response::redirect('/admin/settings?saved=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'CONFIG_REVISION_CONFLICT', 'Configuration changed in another session.', previous: $exception);
        } catch (InvalidArgumentException|InvalidContentException|JsonException $exception) {
            throw new HttpException(422, 'CONFIG_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function updateLlms(Request $request): Response
    {
        return $this->updateSiteText($request, 'llms');
    }

    public function updateSecurityText(Request $request): Response
    {
        return $this->updateSiteText($request, 'security');
    }

    private function updateSiteText(Request $request, string $type): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $body = $request->parsedBody();
            $contents = $body['contents'] ?? null;
            if (!\is_string($contents)) {
                throw new InvalidArgumentException('Text file contents are required.');
            }
            $revision = $this->revision($body['revision'] ?? null);
            if ($type === 'llms') {
                $this->siteTexts->updateLlms($contents, $revision);
                $resource = 'config/llms.txt';
            } else {
                $this->siteTexts->updateSecurity($contents, $revision);
                $resource = 'config/security.txt';
            }
            $this->audit->log("config.{$type}.updated", $actor->id(), $resource, $request->clientIp());

            return Response::redirect("/admin/settings?{$type}_saved=1#text-files", 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'TEXT_FILE_REVISION_CONFLICT', 'Text file changed in another session.', previous: $exception);
        } catch (InvalidArgumentException $exception) {
            throw new HttpException(422, 'TEXT_FILE_INVALID', $exception->getMessage(), previous: $exception);
        }
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

    private function revision(mixed $value): FileRevision
    {
        if (!\is_string($value)) {
            throw new InvalidArgumentException('File revision is required.');
        }

        return FileRevision::fromString($value);
    }

    /** @return array<string, mixed> */
    private function jsonMapping(mixed $value, string $label): array
    {
        if (!\is_string($value) || \strlen($value) > 131_072) {
            throw new InvalidArgumentException("{$label} must be a bounded JSON object.");
        }

        return $this->stringMapping(\json_decode($value, true, flags: JSON_THROW_ON_ERROR), $label);
    }

    /** @return array<mixed> */
    private function jsonArrayBody(mixed $value, string $label): array
    {
        if (!\is_string($value) || \strlen($value) > 131_072) {
            throw new InvalidArgumentException("{$label} must be bounded JSON.");
        }
        $decoded = \json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new InvalidArgumentException("{$label} must be a JSON object or array.");
        }

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function stringMapping(mixed $value, string $label): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException("{$label} must be an object.");
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException("{$label} contains a non-string key.");
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function localizedBody(mixed $value, LanguageConfig $languages): array
    {
        $mapping = $this->stringMapping($value, 'Localized value');
        $result = [];
        foreach ($languages->codes() as $locale) {
            $item = $mapping[$locale] ?? null;
            if (!\is_string($item)) {
                throw new InvalidArgumentException("Localized value for {$locale} is missing.");
            }
            $item = \trim($item);
            if ($item === '' && $locale !== $languages->default()) {
                continue;
            }
            $result[$locale] = $item;
        }

        return $result;
    }

    /**
     * @param list<string> $locales
     * @return array<string, string>
     */
    private function localizedStrings(mixed $value, array $locales): array
    {
        if (\is_string($value)) {
            return \array_fill_keys($locales, $value);
        }
        $mapping = $this->mapping($value);
        $result = [];
        foreach ($locales as $locale) {
            $item = $mapping[$locale] ?? '';
            $result[$locale] = \is_string($item) ? $item : '';
        }

        return $result;
    }

    /** @return list<string> */
    private function stringList(mixed $value, string $label): array
    {
        if (!\is_array($value) || !array_is_list($value) || $value === []) {
            throw new InvalidArgumentException("{$label} requires at least one selection.");
        }
        $result = [];
        foreach ($value as $item) {
            if (!\is_string($item) || $item === '') {
                throw new InvalidArgumentException("{$label} contains an invalid value.");
            }
            $result[] = $item;
        }

        return \array_values(\array_unique($result));
    }

    private function integer(mixed $value, string $label): int
    {
        if (!\is_string($value) || \preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new InvalidArgumentException("{$label} must be a positive integer.");
        }

        return (int) $value;
    }

    private function requiredString(mixed $value, string $label): string
    {
        if (!\is_string($value) || \trim($value) === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return \trim($value);
    }

    private function optionalBodyString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            throw new InvalidArgumentException('Optional string field is invalid.');
        }
        $value = \trim($value);

        return $value === '' ? null : $value;
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

}
