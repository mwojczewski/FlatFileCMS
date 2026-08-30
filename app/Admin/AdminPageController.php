<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\User;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\EditablePage;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageManager;
use FlatFileCms\Content\PageMetadata;
use FlatFileCms\Content\PageRepository;
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
        $items = '';
        foreach ($entries as $entry) {
            $identity = $entry['identity']->value();
            $depth = \count($entry['identity']->segments()) - 1;
            $childAction = $entry['identity']->isHomepage() ? ''
                : '<a class="button compact child" href="/admin/pages/create?parent=' . rawurlencode($identity)
                . '">Dodaj podstronę</a>';
            $editAction = $entry['collection'] ? '<span class="muted">Edycja kolekcji w kolejnym etapie</span>'
                : '<a class="button compact secondary" href="/admin/pages/edit?path=' . rawurlencode($identity)
                . '">Edytuj</a>';
            $items .= '<tr><td class="page-cell"><span class="tree" style="--depth:' . $depth . '">'
                . self::escape($entry['title']) . '</span>'
                . '<small>' . self::escape($identity) . '</small></td><td>'
                . ($entry['collection'] ? '<span class="status collection">Kolekcja</span>'
                    : ($entry['enabled'] ? '<span class="status on">Aktywna</span>' : '<span class="status">Wyłączona</span>'))
                . '</td><td><div class="row-actions">' . $childAction . $editAction . '</div></td></tr>';
        }
        if ($items === '') {
            $items = '<tr><td class="table-empty" colspan="3">Brak stron. Dodaj pierwszą stronę, aby rozpocząć.</td></tr>';
        }

        return $this->page('Strony', '<div class="toolbar crud-toolbar"><div><p class="eyebrow">Zawartość</p>'
            . '<p class="lead">Zarządzaj fizycznym drzewem katalogu <code>pages/</code>.</p></div>'
            . '<a class="button" href="/admin/pages/create">Dodaj stronę</a></div><div class="table-wrap crud-table"><table>'
            . '<thead><tr><th>Strona</th><th>Stan</th><th></th></tr></thead><tbody>' . $items
            . '</tbody></table></div>');
    }

    public function createForm(Request $request): Response
    {
        $this->requireUser();
        $languages = $this->languages->get();
        $parent = $request->query()['parent'] ?? '';
        if (!\is_string($parent)) {
            $parent = '';
        }

        return $this->page('Nowa strona', $this->form(
            '/admin/pages/create',
            $languages,
            null,
            null,
            $parent === '' ? '' : trim($parent, '/') . '/',
        ));
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

        return $this->page(
            'Edycja strony',
            '<div class="toolbar crud-intro"><p>Zarządzaj metadanymi albo przejdź do układu bloków tej strony.</p>'
            . '<a class="button child" href="/admin/pages/builder?path=' . rawurlencode($identity->value())
            . '">Otwórz page builder</a></div>'
            . $this->form('/admin/pages/update', $this->languages->get(), $editable, $identity),
        );
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
            if ($required && $localized === '') {
                throw new InvalidArgumentException(\sprintf('Value for locale "%s" is required.', $locale));
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

    private function form(
        string $action,
        LanguageConfig $languages,
        ?EditablePage $editable,
        ?PageIdentity $identity,
        string $identityPrefix = '',
    ): string {
        $data = $editable?->data() ?? [];
        $homepage = $identity?->isHomepage() ?? false;
        $currentIdentity = $identity?->value() ?? $identityPrefix;
        $enabled = ($data['enabled'] ?? true) === true;
        $layout = \is_string($data['layout'] ?? null) ? $data['layout'] : '';
        $title = $this->stringMapping($data['title'] ?? []);
        $slug = $this->stringMapping($data['slug'] ?? []);
        $seo = $this->mapping($data['seo'] ?? []);
        $seoTitle = $this->stringMapping($seo['title'] ?? []);
        $seoDescription = $this->stringMapping($seo['description'] ?? []);
        $robots = $this->mapping($seo['robots'] ?? []);
        $canonical = \is_string($seo['canonical'] ?? null) ? $seo['canonical'] : '';
        $fields = '';
        foreach ($languages->languages() as $locale => $name) {
            $fields .= '<fieldset class="form-card locale-card"><legend>' . self::escape($name) . ' <code>'
                . self::escape($locale) . '</code></legend>'
                . $this->input('Tytuł strony', 'title[' . $locale . ']', $title[$locale] ?? '', true)
                . ($homepage ? '' : $this->input('Publiczny slug', 'slug[' . $locale . ']', $slug[$locale] ?? '', true))
                . $this->input('Tytuł SEO', 'seo_title[' . $locale . ']', $seoTitle[$locale] ?? '')
                . '<label>Opis SEO<textarea name="seo_description[' . self::escape($locale) . ']" maxlength="500">'
                . self::escape($seoDescription[$locale] ?? '') . '</textarea></label></fieldset>';
        }
        $layoutOptions = '<option value="">Domyślny z setup.yml</option>';
        foreach (array_keys($this->layouts->all()) as $name) {
            $layoutOptions .= '<option value="' . self::escape($name) . '"'
                . ($name === $layout ? ' selected' : '') . '>' . self::escape($name) . '</option>';
        }
        $revision = $editable?->revision()->value();
        $identityField = $editable === null
            ? $this->input('Ścieżka techniczna', 'identity', $currentIdentity, true, 'np. blog/nowy-wpis')
            : '<input type="hidden" name="identity" value="' . self::escape($currentIdentity) . '">'
            . '<div class="technical-path"><span>Ścieżka techniczna</span><code>'
            . self::escape($currentIdentity) . '</code></div>';
        $content = '<form class="stack crud-form" method="post" action="' . self::escape($action) . '">'
            . $this->csrfField() . ($revision === null ? '' : '<input type="hidden" name="revision" value="'
            . self::escape($revision) . '">')
            . '<section class="form-section"><div class="section-heading"><div><p class="eyebrow">Podstawowe</p>'
            . '<h2>Ustawienia strony</h2></div><p>Widoczność, położenie i szablon dokumentu.</p></div>'
            . '<div class="settings-grid"><div>' . $identityField . '</div>'
            . '<div><label>Layout<select name="layout">' . $layoutOptions . '</select></label></div>'
            . '<label class="check toggle-card"><input type="checkbox" name="enabled" value="1"'
            . ($enabled ? ' checked' : '') . '><span><strong>Strona dostępna publicznie</strong>'
            . '<small>Wyłączenie ukrywa stronę w publicznym API i renderowaniu HTML.</small></span></label></div></section>'
            . '<section class="form-section"><div class="section-heading"><div><p class="eyebrow">Języki</p>'
            . '<h2>Treść i adresy</h2></div><p>Uzupełnij dane niezależnie dla każdej aktywnej wersji językowej.</p></div>'
            . '<div class="locale-grid">' . $fields . '</div></section>'
            . '<section class="form-section"><div class="section-heading"><div><p class="eyebrow">Wyszukiwarki</p>'
            . '<h2>Ustawienia SEO</h2></div><p>Wartości wspólne dla wszystkich wersji językowych.</p></div>'
            . '<fieldset class="form-card seo-card"><legend>SEO wspólne</legend>'
            . $this->input('Canonical URL', 'canonical', $canonical, false, '/ścieżka lub https://example.com/ścieżka')
            . '<div class="robots-grid"><label class="check"><input type="checkbox" name="robots_index" value="1"'
            . (($robots['index'] ?? true) === true ? ' checked' : '') . '><span><strong>Pozwól indeksować</strong>'
            . '<small>Wyszukiwarki mogą umieścić stronę w wynikach.</small></span></label>'
            . '<label class="check"><input type="checkbox" name="robots_follow" value="1"'
            . (($robots['follow'] ?? true) === true ? ' checked' : '') . '><span><strong>Pozwól śledzić linki</strong>'
            . '<small>Roboty mogą przechodzić do odnośników na stronie.</small></span></label></div></fieldset></section>'
            . '<div class="actions form-actions"><a class="button secondary" href="/admin/pages">Anuluj</a>'
            . '<button type="submit">Zapisz zmiany</button></div></form>';

        if ($editable === null || $homepage || $revision === null) {
            return $content;
        }

        return $content . '<section class="danger-zone"><div class="section-heading"><div><p class="eyebrow">Zaawansowane</p>'
            . '<h2>Operacje na katalogu</h2></div><p>Te działania zmieniają fizyczną strukturę plików strony.</p></div>'
            . '<div class="danger-grid"><form class="danger-action" method="post" action="/admin/pages/move">'
            . $this->csrfField() . '<input type="hidden" name="identity" value="' . self::escape($currentIdentity)
            . '"><input type="hidden" name="revision" value="' . self::escape($revision) . '">'
            . '<h3>Przenieś stronę</h3><p>Zmień ścieżkę techniczną bez utraty zawartości.</p>'
            . $this->input('Nowa ścieżka techniczna', 'destination', $currentIdentity, true)
            . '<button type="submit" class="warning">Przenieś katalog</button></form>'
            . '<form class="danger-action delete-action" method="post" action="/admin/pages/delete">' . $this->csrfField()
            . '<input type="hidden" name="identity" value="' . self::escape($currentIdentity) . '">'
            . '<input type="hidden" name="revision" value="' . self::escape($revision) . '">'
            . '<h3>Usuń stronę</h3><p>Usunięty zostanie cały katalog wraz z podstronami i multimediami.</p>'
            . '<label>Wpisz <code>delete</code>, aby usunąć stronę, jej podstrony i multimedia'
            . '<input name="confirmation" required autocomplete="off"></label>'
            . '<button type="submit" class="danger">Usuń katalog bezpowrotnie</button></form></div></section>';
    }

    private function input(
        string $label,
        string $name,
        string $value,
        bool $required = false,
        string $placeholder = '',
    ): string {
        return '<label>' . self::escape($label) . '<input name="' . self::escape($name) . '" value="'
            . self::escape($value) . '"' . ($required ? ' required' : '') . ($placeholder === '' ? '' : ' placeholder="'
            . self::escape($placeholder) . '"') . '></label>';
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

    /** @return array<string, string> */
    private function stringMapping(mixed $value): array
    {
        $result = [];
        foreach ($this->mapping($value) as $key => $item) {
            if (\is_string($item)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::escape($this->csrf->token()) . '">';
    }

    private function page(string $title, string $content): Response
    {
        return $this->layout->render($title, $content, active: 'pages');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
