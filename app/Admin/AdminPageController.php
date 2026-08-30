<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
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
    ) {}

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
                : '<a class="button child" href="/admin/pages/create?parent=' . rawurlencode($identity)
                    . '">Dodaj podstronę</a>';
            $editAction = $entry['collection'] ? '<span class="muted">Edycja kolekcji w kolejnym etapie</span>'
                : '<a class="button secondary" href="/admin/pages/edit?path=' . rawurlencode($identity)
                    . '">Edytuj</a>';
            $items .= '<tr><td><span class="tree" style="--depth:' . $depth . '">'
                . self::escape($entry['title']) . '</span>'
                . '<small>' . self::escape($identity) . '</small></td><td>'
                . ($entry['collection'] ? '<span class="status collection">Kolekcja</span>'
                    : ($entry['enabled'] ? '<span class="status on">Aktywna</span>' : '<span class="status">Wyłączona</span>'))
                . '</td><td><div class="row-actions">' . $childAction . $editAction . '</div></td></tr>';
        }
        if ($items === '') {
            $items = '<tr><td colspan="3">Brak stron.</td></tr>';
        }

        return $this->page('Strony', '<div class="toolbar"><div><p class="eyebrow">Zawartość</p>'
            . '<p class="lead">Zarządzaj fizycznym drzewem katalogu <code>pages/</code>.</p></div>'
            . '<a class="button" href="/admin/pages/create">Dodaj stronę</a></div><div class="table-wrap"><table>'
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
        $this->requireUser();
        try {
            $this->validateCsrf($request);
            $languages = $this->languages->get();
            $identity = $this->identity($request->parsedBody()['identity'] ?? null);
            $this->manager->create($identity, $this->metadata($request, $languages, false), $languages);

            return Response::redirect('/admin/pages?created=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.', previous: $exception);
        } catch (InvalidArgumentException|InvalidContentException|FilesystemException $exception) {
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
            '<div class="toolbar"><p>Zarządzaj metadanymi albo przejdź do układu bloków tej strony.</p>'
                . '<a class="button child" href="/admin/pages/builder?path=' . rawurlencode($identity->value())
                . '">Otwórz page builder</a></div>'
                . $this->form('/admin/pages/update', $this->languages->get(), $editable, $identity),
        );
    }

    public function update(Request $request): Response
    {
        $this->requireUser();
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

            return Response::redirect('/admin/pages/edit?path=' . rawurlencode($identity->value()) . '&saved=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.', previous: $exception);
        } catch (InvalidArgumentException|InvalidContentException|FilesystemException $exception) {
            throw new HttpException(422, 'PAGE_UPDATE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function move(Request $request): Response
    {
        $this->requireUser();
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

            return Response::redirect('/admin/pages/edit?path=' . rawurlencode($destination->value()) . '&moved=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.', previous: $exception);
        } catch (InvalidArgumentException|InvalidContentException|FilesystemException $exception) {
            throw new HttpException(422, 'PAGE_MOVE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function delete(Request $request): Response
    {
        $this->requireUser();
        try {
            $this->validateCsrf($request);
            if (($request->parsedBody()['confirmation'] ?? null) !== 'delete') {
                throw new InvalidArgumentException('Deletion must be explicitly confirmed.');
            }
            $this->manager->delete(
                $this->identity($request->parsedBody()['identity'] ?? null),
                $this->revision($request->parsedBody()['revision'] ?? null),
            );

            return Response::redirect('/admin/pages?deleted=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.', previous: $exception);
        } catch (InvalidArgumentException|InvalidContentException|FilesystemException $exception) {
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

    private function requireUser(): void
    {
        try {
            $this->authenticator->requireUser();
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
            $fields .= '<fieldset><legend>' . self::escape($name) . ' <code>' . self::escape($locale) . '</code></legend>'
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
                . '<p><strong>Ścieżka techniczna:</strong> <code>' . self::escape($currentIdentity) . '</code></p>';
        $content = '<form class="stack" method="post" action="' . self::escape($action) . '">'
            . $this->csrfField() . ($revision === null ? '' : '<input type="hidden" name="revision" value="'
                . self::escape($revision) . '">') . $identityField
            . '<label class="check"><input type="checkbox" name="enabled" value="1"' . ($enabled ? ' checked' : '')
            . '> Strona dostępna publicznie</label><label>Layout<select name="layout">' . $layoutOptions
            . '</select></label>' . $fields . '<fieldset><legend>SEO wspólne</legend>'
            . $this->input('Canonical URL', 'canonical', $canonical, false, '/ścieżka lub https://example.com/ścieżka')
            . '<label class="check"><input type="checkbox" name="robots_index" value="1"'
            . (($robots['index'] ?? true) === true ? ' checked' : '') . '> Pozwól indeksować</label>'
            . '<label class="check"><input type="checkbox" name="robots_follow" value="1"'
            . (($robots['follow'] ?? true) === true ? ' checked' : '') . '> Pozwól śledzić linki</label></fieldset>'
            . '<div class="actions"><button type="submit">Zapisz</button><a class="button secondary" href="/admin/pages">Anuluj</a></div></form>';

        if ($editable === null || $homepage || $revision === null) {
            return $content;
        }

        return $content . '<section class="danger-zone"><h2>Operacje na katalogu</h2><form method="post" action="/admin/pages/move">'
            . $this->csrfField() . '<input type="hidden" name="identity" value="' . self::escape($currentIdentity)
            . '"><input type="hidden" name="revision" value="' . self::escape($revision) . '">'
            . $this->input('Nowa ścieżka techniczna', 'destination', $currentIdentity, true)
            . '<button type="submit" class="warning">Przenieś katalog</button></form><hr>'
            . '<form method="post" action="/admin/pages/delete">' . $this->csrfField()
            . '<input type="hidden" name="identity" value="' . self::escape($currentIdentity) . '">'
            . '<input type="hidden" name="revision" value="' . self::escape($revision) . '">'
            . '<label>Wpisz <code>delete</code>, aby usunąć stronę, jej podstrony i multimedia'
            . '<input name="confirmation" required autocomplete="off"></label>'
            . '<button type="submit" class="danger">Usuń katalog bezpowrotnie</button></form></section>';
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
        return Response::html('<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" '
            . 'content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'
            . self::escape($title) . ' — FlatFile CMS</title><style>:root{color-scheme:light;--ink:#101828;--muted:#667085;'
            . '--line:#e4e7ec;--accent:#3157d5;--accent-hover:#2848b8;--surface:#fff;--bg:#f4f6fa;--nav:#111827;--child:#087e8b;'
            . '--child-hover:#086b76}*{box-sizing:border-box}body{margin:0;font:15px/1.55 Inter,ui-sans-serif,system-ui,sans-serif;background:var(--bg);'
            . 'color:var(--ink);-webkit-font-smoothing:antialiased}header{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;'
            . 'min-height:4.25rem;padding:.75rem clamp(1rem,4vw,3rem);background:var(--nav);color:#fff;box-shadow:0 1px 0 #ffffff14}header>a{font-size:1.05rem;'
            . 'letter-spacing:-.015em}header a{color:inherit;text-decoration:none}nav{display:flex;align-items:center;gap:.35rem}nav>a{padding:.5rem .7rem;border-radius:.5rem;'
            . 'color:#d0d5dd}nav>a:hover{background:#ffffff12;color:#fff}nav form{margin:0}.nav-logout{margin-left:.35rem;padding:.45rem .75rem;background:transparent;'
            . 'border:1px solid #475467;color:#f2f4f7}.nav-logout:hover{background:#ffffff12;border-color:#667085}main{width:min(76rem,calc(100% - 2rem));margin:2rem auto;'
            . 'padding:clamp(1.25rem,3vw,2.25rem);background:var(--surface);border:1px solid var(--line);border-radius:1rem;box-shadow:0 18px 45px #1018280a}'
            . 'h1{margin:0 0 1.5rem;font-size:clamp(1.65rem,3vw,2rem);letter-spacing:-.025em}h2{font-size:1.15rem}p{color:#475467}.lead{margin:.2rem 0 0}'
            . '.eyebrow{margin:0;color:var(--accent);font-size:.75rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase}a{color:var(--accent)}'
            . '.toolbar,.actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}.button,button{display:inline-flex;align-items:center;'
            . 'justify-content:center;min-height:2.55rem;border:0;border-radius:.6rem;background:var(--accent);color:#fff;text-decoration:none;padding:.65rem .95rem;font:inherit;'
            . 'font-weight:700;line-height:1.2;cursor:pointer;transition:background .15s ease,box-shadow .15s ease,transform .15s ease}.button:hover,button:hover{background:var(--accent-hover);'
            . 'box-shadow:0 4px 12px #3157d529}.button:active,button:active{transform:translateY(1px)}.secondary{background:#eef2ff;color:#273a8a}.secondary:hover{'
            . 'background:#e0e7ff;box-shadow:none}.child{background:var(--child);color:#fff}.child:hover{background:var(--child-hover);box-shadow:0 4px 12px #087e8b29}'
            . '.warning{background:#b54708}.danger{background:#b42318}.row-actions{display:flex;align-items:center;justify-content:flex-end;gap:.55rem;white-space:nowrap}'
            . '.table-wrap{overflow:auto;margin-top:1.5rem;border:1px solid var(--line);border-radius:.8rem}table{width:100%;border-collapse:collapse}thead{background:#f9fafb}'
            . 'th,td{padding:.85rem 1rem;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}th{color:#475467;font-size:.78rem;letter-spacing:.045em;'
            . 'text-transform:uppercase}tbody tr:last-child td{border-bottom:0}tbody tr:hover{background:#fcfcfd}small{display:block;margin-top:.12rem;color:var(--muted)}'
            . '.tree{display:block;padding-left:calc(var(--depth)*1.25rem);font-weight:700}.status{display:inline-block;padding:.25rem .55rem;border-radius:999px;'
            . 'background:#f2f4f7;color:#475467;font-size:.8rem;font-weight:700}.status.on{background:#ecfdf3;color:#027a48}.status.collection{background:#eef4ff;color:#3538cd}'
            . '.muted{max-width:12rem;color:var(--muted);font-size:.85rem;white-space:normal}.stack{display:grid;gap:1.25rem}fieldset{border:1px solid var(--line);'
            . 'border-radius:.8rem;padding:1rem}legend{padding:0 .4rem;font-weight:700}label{display:grid;gap:.4rem;margin:.75rem 0;color:#344054;font-weight:600}'
            . 'input,textarea,select{width:100%;border:1px solid #cfd4dc;border-radius:.6rem;background:#fff;padding:.7rem;font:inherit;color:var(--ink);outline:none}'
            . 'input:focus,textarea:focus,select:focus{border-color:#6172f3;box-shadow:0 0 0 3px #6172f31f}textarea{min-height:7rem;resize:vertical}.check{display:flex;'
            . 'grid-template-columns:auto 1fr;align-items:center;justify-content:flex-start}.check input{width:auto}.danger-zone{margin-top:2rem;padding:1rem;border:1px solid #fecdca;'
            . 'border-radius:.8rem;background:#fffbfa}code{font:13px ui-monospace,SFMono-Regular,monospace}hr{border:0;border-top:1px solid #fecdca;margin:1.5rem 0}'
            . '@media(max-width:720px){header{position:static;align-items:flex-start;gap:.75rem}nav{justify-content:flex-end;flex-wrap:wrap}.row-actions{align-items:stretch;'
            . 'flex-direction:column;white-space:normal}.row-actions .button{width:100%}.muted{max-width:none;text-align:center}}@media(max-width:600px){main{width:100%;margin:0;'
            . 'border:0;border-radius:0;box-shadow:none}.toolbar,.actions{align-items:stretch;flex-direction:column}.toolbar>.button,.actions>.button,.actions>button{width:100%}'
            . 'th:nth-child(2),td:nth-child(2){display:none}.table-wrap{margin-inline:-.5rem}th,td{padding:.75rem}.tree{padding-left:calc(var(--depth)*.7rem)}}</style>'
            . '</head><body><header><a href="/admin"><strong>FlatFile CMS</strong></a><nav><a href="/admin">Panel</a>'
            . '<a href="/admin/pages">Strony</a><a href="/admin/security">Konto</a><form method="post" action="/admin/logout">'
            . $this->csrfField() . '<button type="submit" class="nav-logout">Wyloguj</button></form></nav></header><main><h1>'
            . self::escape($title) . '</h1>' . $content
            . '</main></body></html>', headers: ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache', 'X-Frame-Options' => 'DENY']);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
