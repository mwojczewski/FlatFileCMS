<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Blocks\BlockDefinition;
use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BlockValidationException;
use FlatFileCms\Blocks\InvalidBlockDefinitionException;
use FlatFileCms\Blocks\ValidationError;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageBlockManager;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class AdminPageBuilderController
{
    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private LanguageRepository $languages,
        private PageBlockManager $manager,
        private BlockRegistry $registry,
        private BlockFormDataMapper $dataMapper,
        private BlockFormRenderer $forms,
    ) {}

    public function index(Request $request): Response
    {
        $this->requireUser();
        $identity = $this->queryIdentity($request);
        try {
            $editable = $this->manager->editable($identity);
            $blocks = $this->blocks($editable->data());
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found.', previous: $exception);
        }
        $items = '';
        foreach ($blocks as $position => $block) {
            $id = ContentData::string($block['id'] ?? null, 'block.id');
            $type = ContentData::string($block['type'] ?? null, 'block.type');
            $enabled = $block['enabled'] ?? true;
            if (!\is_bool($enabled)) {
                throw new HttpException(422, 'BLOCK_STATE_INVALID', 'Block enabled state is invalid.');
            }
            $definition = $this->registry->get($type);
            $name = $this->localized($definition->name(), $this->languages->get(), $type);
            $items .= '<article class="builder-item' . ($enabled ? '' : ' disabled') . '" draggable="true" data-block-id="'
                . self::escape($id) . '"><button type="button" class="drag-handle" aria-label="Przeciągnij blok">⋮⋮</button>'
                . '<div class="block-summary"><span class="position">' . ($position + 1) . '</span><div><strong>'
                . self::escape($name) . '</strong><small>' . self::escape($type) . ' · ' . ($enabled ? 'włączony' : 'wyłączony')
                . '</small></div></div><div class="block-actions"><a class="button secondary" href="/admin/pages/builder/edit?path='
                . rawurlencode($identity->value()) . '&id=' . rawurlencode($id) . '">Edytuj</a>'
                . $this->actionForm('/admin/pages/builder/toggle', $identity, $id, $editable->revision(), $enabled ? 'Wyłącz' : 'Włącz')
                . $this->actionForm('/admin/pages/builder/duplicate', $identity, $id, $editable->revision(), 'Duplikuj')
                . $this->actionForm('/admin/pages/builder/delete', $identity, $id, $editable->revision(), 'Usuń', 'danger-text')
                . '</div></article>';
        }
        if ($items === '') {
            $items = '<div class="empty-state"><strong>Ta strona nie ma jeszcze bloków.</strong>'
                . '<p>Dodaj pierwszy blok, a formularz zostanie wygenerowany z jego definicji YAML.</p></div>';
        }
        $orderFields = '';
        foreach ($blocks as $block) {
            $id = ContentData::string($block['id'] ?? null, 'block.id');
            $orderFields .= '<input type="hidden" name="order[]" value="' . self::escape($id) . '" data-order-field>';
        }
        $content = '<div class="toolbar"><div><p class="eyebrow">Page builder</p><p class="lead"><code>'
            . self::escape($identity->value()) . '</code></p></div><div class="actions"><a class="button secondary" href="/admin/pages/edit?path='
            . rawurlencode($identity->value()) . '">Ustawienia strony</a><a class="button" href="/admin/pages/builder/picker?path='
            . rawurlencode($identity->value()) . '">Dodaj blok</a></div></div><div class="builder-list" data-builder-list>'
            . $items . '</div><form class="order-form" method="post" action="/admin/pages/builder/reorder" data-order-form>'
            . $this->csrfField() . '<input type="hidden" name="identity" value="' . self::escape($identity->value())
            . '"><input type="hidden" name="revision" value="' . self::escape($editable->revision()->value()) . '">'
            . '<span data-order-fields>' . $orderFields . '</span><button type="submit" class="button child" disabled data-order-submit>'
            . 'Zapisz kolejność</button></form>';

        return $this->page('Bloki strony', $content, scripts: true);
    }

    public function picker(Request $request): Response
    {
        $this->requireUser();
        $identity = $this->queryIdentity($request);
        try {
            $this->manager->editable($identity);
        } catch (FilesystemException $exception) {
            throw new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found.', previous: $exception);
        }
        $languages = $this->languages->get();
        $cards = '';
        foreach ($this->registry->all() as $definition) {
            $name = $this->localized($definition->name(), $languages, $definition->type());
            $description = $this->localized($definition->description(), $languages, '');
            $preview = is_file($definition->directory() . '/preview.webp')
                ? '<img src="/admin/pages/builder/preview?type=' . rawurlencode($definition->type()) . '" alt="">'
                : '<span class="block-icon">' . self::escape($definition->icon() ?? 'block') . '</span>';
            $cards .= '<a class="picker-card" href="/admin/pages/builder/create?path=' . rawurlencode($identity->value())
                . '&type=' . rawurlencode($definition->type()) . '">' . $preview . '<span><strong>' . self::escape($name)
                . '</strong><small>' . self::escape($description) . '</small></span></a>';
        }

        return $this->page('Wybierz blok', '<div class="picker-grid">' . $cards . '</div><div class="actions footer-actions">'
            . '<a class="button secondary" href="/admin/pages/builder?path=' . rawurlencode($identity->value())
            . '">Wróć do bloków</a></div>');
    }

    public function preview(Request $request): Response
    {
        $this->requireUser();
        $type = $request->query()['type'] ?? null;
        if (!\is_string($type)) {
            throw new HttpException(400, 'BLOCK_TYPE_REQUIRED', 'Block type is required.');
        }
        $path = $this->registry->get($type)->directory() . '/preview.webp';
        if (!is_file($path) || is_link($path)) {
            throw new HttpException(404, 'BLOCK_PREVIEW_NOT_FOUND', 'Block preview not found.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new HttpException(500, 'BLOCK_PREVIEW_READ_FAILED', 'Block preview cannot be read.');
        }

        return new Response($contents, headers: [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function createForm(Request $request): Response
    {
        $this->requireUser();
        $identity = $this->queryIdentity($request);
        $definition = $this->queryDefinition($request);
        try {
            $editable = $this->manager->editable($identity);
        } catch (FilesystemException $exception) {
            throw new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found.', previous: $exception);
        }

        return $this->blockForm('Dodaj blok', '/admin/pages/builder/create', $identity, $definition, [], $editable->revision());
    }

    public function create(Request $request): Response
    {
        $this->requireUser();
        try {
            $this->validateCsrf($request);
            $identity = $this->bodyIdentity($request);
            $languages = $this->languages->get();
            $definition = $this->bodyDefinition($request);
            $data = $this->dataMapper->map($definition, $request->parsedBody()['data'] ?? [], $languages);
            $this->manager->add($identity, $definition->type(), $data, $this->bodyRevision($request), $languages);

            return $this->redirect($identity, 'created');
        } catch (BlockValidationException $exception) {
            throw $this->validationException($exception);
        } catch (RevisionConflictException $exception) {
            throw $this->conflict($exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(422, 'BLOCK_CREATE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function editForm(Request $request): Response
    {
        $this->requireUser();
        $identity = $this->queryIdentity($request);
        $id = $this->queryId($request);
        try {
            $editable = $this->manager->editable($identity);
        } catch (FilesystemException $exception) {
            throw new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found.', previous: $exception);
        }
        $block = $this->findBlock($editable->data(), $id);
        $definition = $this->registry->get(ContentData::string($block['type'] ?? null, 'block.type'));
        $data = ContentData::map($block['data'] ?? [], 'block.data');

        return $this->blockForm('Edytuj blok', '/admin/pages/builder/update', $identity, $definition, $data, $editable->revision(), $id);
    }

    public function update(Request $request): Response
    {
        $this->requireUser();
        try {
            $this->validateCsrf($request);
            $identity = $this->bodyIdentity($request);
            $id = $this->bodyId($request);
            $languages = $this->languages->get();
            $block = $this->manager->block($identity, $id);
            $definition = $this->registry->get(ContentData::string($block['type'] ?? null, 'block.type'));
            $data = $this->dataMapper->map($definition, $request->parsedBody()['data'] ?? [], $languages);
            $this->manager->update($identity, $id, $data, $this->bodyRevision($request), $languages);

            return $this->redirect($identity, 'updated');
        } catch (BlockValidationException $exception) {
            throw $this->validationException($exception);
        } catch (RevisionConflictException $exception) {
            throw $this->conflict($exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(422, 'BLOCK_UPDATE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function duplicate(Request $request): Response
    {
        return $this->simpleMutation($request, 'duplicate');
    }

    public function toggle(Request $request): Response
    {
        return $this->simpleMutation($request, 'toggle');
    }

    public function delete(Request $request): Response
    {
        return $this->simpleMutation($request, 'delete');
    }

    public function reorder(Request $request): Response
    {
        $this->requireUser();
        try {
            $this->validateCsrf($request);
            $identity = $this->bodyIdentity($request);
            $rawOrder = $request->parsedBody()['order'] ?? null;
            if (!\is_array($rawOrder) || !array_is_list($rawOrder)) {
                throw new InvalidArgumentException('Block order must be a list.');
            }
            $order = [];
            foreach ($rawOrder as $id) {
                if (!\is_string($id)) {
                    throw new InvalidArgumentException('Block order identifiers must be strings.');
                }
                $order[] = $id;
            }
            $this->manager->reorder($identity, $order, $this->bodyRevision($request), $this->languages->get());

            return $this->redirect($identity, 'reordered');
        } catch (RevisionConflictException $exception) {
            throw $this->conflict($exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(422, 'BLOCK_REORDER_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    private function simpleMutation(Request $request, string $operation): Response
    {
        $this->requireUser();
        try {
            $this->validateCsrf($request);
            $identity = $this->bodyIdentity($request);
            $id = $this->bodyId($request);
            $revision = $this->bodyRevision($request);
            $languages = $this->languages->get();
            match ($operation) {
                'duplicate' => $this->manager->duplicate($identity, $id, $revision, $languages),
                'toggle' => $this->manager->toggle($identity, $id, $revision, $languages),
                'delete' => $this->manager->delete($identity, $id, $revision, $languages),
                default => throw new InvalidArgumentException('Unknown block operation.'),
            };

            return $this->redirect($identity, $operation);
        } catch (RevisionConflictException $exception) {
            throw $this->conflict($exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(422, 'BLOCK_OPERATION_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function blocks(array $data): array
    {
        $blocks = [];
        foreach (ContentData::list($data['blocks'] ?? [], 'blocks') as $index => $block) {
            $blocks[] = ContentData::map($block, 'blocks.' . $index);
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function findBlock(array $data, string $id): array
    {
        foreach ($this->blocks($data) as $block) {
            if (($block['id'] ?? null) === $id) {
                return $block;
            }
        }

        throw new HttpException(404, 'BLOCK_NOT_FOUND', 'Block not found.');
    }

    /** @param array<string, string> $values */
    private function localized(array $values, LanguageConfig $languages, string $fallback): string
    {
        $localized = $values[$languages->default()] ?? null;
        if (\is_string($localized) && $localized !== '') {
            return $localized;
        }
        $first = reset($values);

        return \is_string($first) && $first !== '' ? $first : $fallback;
    }

    /** @param array<string, mixed> $data */
    private function blockForm(
        string $title,
        string $action,
        PageIdentity $identity,
        BlockDefinition $definition,
        array $data,
        FileRevision $revision,
        ?string $id = null,
    ): Response {
        $languages = $this->languages->get();
        $name = $this->localized($definition->name(), $languages, $definition->type());
        $hiddenId = $id === null ? '' : '<input type="hidden" name="id" value="' . self::escape($id) . '">';
        $content = '<p class="lead"><strong>' . self::escape($name) . '</strong> <code>'
            . self::escape($definition->type()) . '</code></p><form class="stack block-form" method="post" action="'
            . self::escape($action) . '">' . $this->csrfField() . '<input type="hidden" name="identity" value="'
            . self::escape($identity->value()) . '"><input type="hidden" name="type" value="'
            . self::escape($definition->type()) . '"><input type="hidden" name="revision" value="'
            . self::escape($revision->value()) . '">' . $hiddenId . $this->forms->render($definition, $languages, $data)
            . '<div class="actions footer-actions"><button type="submit">Zapisz blok</button><a class="button secondary" href="'
            . '/admin/pages/builder?path=' . rawurlencode($identity->value()) . '">Anuluj</a></div></form>';

        return $this->page($title, $content, scripts: true);
    }

    private function actionForm(
        string $action,
        PageIdentity $identity,
        string $id,
        FileRevision $revision,
        string $label,
        string $class = 'subtle',
    ): string {
        $confirmation = $label === 'Usuń' ? ' data-confirm="Usunąć ten blok bezpowrotnie?"' : '';

        return '<form method="post" action="' . self::escape($action) . '"' . $confirmation . '>' . $this->csrfField()
            . '<input type="hidden" name="identity" value="' . self::escape($identity->value()) . '">'
            . '<input type="hidden" name="id" value="' . self::escape($id) . '">'
            . '<input type="hidden" name="revision" value="' . self::escape($revision->value()) . '">'
            . '<button type="submit" class="button ' . self::escape($class) . '">' . self::escape($label) . '</button></form>';
    }

    private function queryDefinition(Request $request): BlockDefinition
    {
        $type = $request->query()['type'] ?? null;
        if (!\is_string($type)) {
            throw new HttpException(400, 'BLOCK_TYPE_REQUIRED', 'Block type is required.');
        }

        try {
            return $this->registry->get($type);
        } catch (InvalidBlockDefinitionException $exception) {
            throw new HttpException(400, 'BLOCK_TYPE_INVALID', 'Block type is invalid.', previous: $exception);
        }
    }

    private function bodyDefinition(Request $request): BlockDefinition
    {
        $type = $request->parsedBody()['type'] ?? null;
        if (!\is_string($type)) {
            throw new InvalidArgumentException('Block type is required.');
        }

        try {
            return $this->registry->get($type);
        } catch (InvalidBlockDefinitionException $exception) {
            throw new InvalidArgumentException('Block type is invalid.', previous: $exception);
        }
    }

    private function queryIdentity(Request $request): PageIdentity
    {
        return $this->identity($request->query()['path'] ?? null);
    }

    private function bodyIdentity(Request $request): PageIdentity
    {
        return $this->identity($request->parsedBody()['identity'] ?? null);
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

    private function queryId(Request $request): string
    {
        return $this->id($request->query()['id'] ?? null);
    }

    private function bodyId(Request $request): string
    {
        return $this->id($request->parsedBody()['id'] ?? null);
    }

    private function id(mixed $value): string
    {
        if (
            !\is_string($value)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) !== 1
        ) {
            throw new HttpException(400, 'BLOCK_ID_INVALID', 'Block identifier is invalid.');
        }

        return $value;
    }

    private function bodyRevision(Request $request): FileRevision
    {
        $revision = $request->parsedBody()['revision'] ?? null;
        if (!\is_string($revision)) {
            throw new HttpException(400, 'PAGE_REVISION_REQUIRED', 'Page revision is required.');
        }

        return FileRevision::fromString($revision);
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

    private function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::escape($this->csrf->token()) . '">';
    }

    private function redirect(PageIdentity $identity, string $status): Response
    {
        return Response::redirect('/admin/pages/builder?path=' . rawurlencode($identity->value()) . '&' . $status . '=1', 303);
    }

    private function conflict(RevisionConflictException $exception): HttpException
    {
        return new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.', previous: $exception);
    }

    private function validationException(BlockValidationException $exception): HttpException
    {
        $message = implode(' ', array_map(
            static fn(ValidationError $error): string => $error->path() . ': ' . $error->message(),
            $exception->errors(),
        ));

        return new HttpException(422, 'BLOCK_DATA_INVALID', $message, previous: $exception);
    }

    private function page(string $title, string $content, bool $scripts = false): Response
    {
        $script = $scripts ? '<script src="/assets/admin/page-builder.js" defer></script>' : '';

        return Response::html('<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" '
            . 'content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'
            . self::escape($title) . ' — FlatFile CMS</title><style>:root{color-scheme:light;--ink:#101828;--muted:#667085;--line:#e4e7ec;'
            . '--accent:#3157d5;--accent-hover:#2848b8;--surface:#fff;--bg:#f4f6fa;--nav:#111827;--child:#087e8b}*{box-sizing:border-box}'
            . 'body{margin:0;font:15px/1.55 Inter,ui-sans-serif,system-ui,sans-serif;background:var(--bg);color:var(--ink)}header{position:sticky;top:0;z-index:5;'
            . 'display:flex;align-items:center;justify-content:space-between;min-height:4.25rem;padding:.75rem clamp(1rem,4vw,3rem);background:var(--nav);color:#fff}'
            . 'header a{color:inherit;text-decoration:none}nav{display:flex;align-items:center;gap:.35rem}nav>a{padding:.5rem .7rem;border-radius:.5rem;color:#d0d5dd}'
            . 'nav>a:hover{background:#ffffff12;color:#fff}nav form{margin:0}.nav-logout{margin-left:.35rem;padding:.45rem .75rem;background:transparent;border:1px solid #475467;color:#fff}'
            . 'main{width:min(76rem,calc(100% - 2rem));margin:2rem auto;padding:clamp(1.25rem,3vw,2.25rem);background:var(--surface);border:1px solid var(--line);'
            . 'border-radius:1rem;box-shadow:0 18px 45px #1018280a}h1{margin:0 0 1.5rem;font-size:clamp(1.65rem,3vw,2rem)}p{color:#475467}.lead{margin:.2rem 0 0}'
            . '.eyebrow{margin:0;color:var(--accent);font-size:.75rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase}code{font:13px ui-monospace,monospace}'
            . '.toolbar,.actions{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap}.button,button{display:inline-flex;align-items:center;justify-content:center;'
            . 'min-height:2.45rem;border:0;border-radius:.6rem;background:var(--accent);color:#fff;text-decoration:none;padding:.6rem .9rem;font:inherit;font-weight:700;cursor:pointer}'
            . '.button:hover,button:hover{background:var(--accent-hover)}.secondary,.subtle{background:#eef2ff;color:#273a8a}.child{background:var(--child)}.danger-text{background:#fef3f2;color:#b42318}'
            . '.builder-list{display:grid;gap:.75rem;margin-top:1.5rem}.builder-item{display:grid;grid-template-columns:auto minmax(12rem,1fr) auto;align-items:center;gap:.85rem;padding:1rem;'
            . 'border:1px solid var(--line);border-radius:.8rem;background:#fff}.builder-item.dragging{opacity:.45}.builder-item.disabled{background:#f9fafb}.drag-handle{padding:.35rem;background:transparent;color:#98a2b3;cursor:grab}'
            . '.block-summary{display:flex;align-items:center;gap:.75rem}.block-summary small{display:block;color:var(--muted)}.position{display:grid;place-items:center;width:2rem;height:2rem;border-radius:.5rem;'
            . 'background:#f2f4f7;color:#475467;font-weight:750}.block-actions{display:flex;align-items:center;justify-content:flex-end;gap:.4rem;flex-wrap:wrap}.block-actions form{margin:0}'
            . '.block-actions .button{min-height:2.15rem;padding:.45rem .65rem;font-size:.86rem}.order-form{display:flex;justify-content:flex-end;margin-top:1rem}.order-form button:disabled{opacity:.45;cursor:not-allowed}'
            . '.empty-state{padding:2.5rem;text-align:center;border:1px dashed #cfd4dc;border-radius:.8rem;background:#fcfcfd}.picker-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr));gap:1rem}'
            . '.picker-card{display:grid;grid-template-rows:9rem auto;overflow:hidden;border:1px solid var(--line);border-radius:.8rem;color:var(--ink);text-decoration:none;background:#fff;transition:.15s}'
            . '.picker-card:hover{border-color:#9ba8ef;box-shadow:0 10px 24px #10182812;transform:translateY(-1px)}.picker-card img{width:100%;height:100%;object-fit:cover}.picker-card>span:last-child{display:grid;gap:.25rem;padding:1rem}'
            . '.picker-card small{color:var(--muted)}.block-icon{display:grid;place-items:center;background:#f2f4ff;color:#3538cd;font-size:1rem;font-weight:800}.stack{display:grid;gap:1rem}.generated-fields{display:grid;gap:1rem}'
            . '.field{display:grid;gap:.55rem;padding:1rem;border:1px solid var(--line);border-radius:.8rem}.field-heading{display:grid;gap:.15rem;font-weight:700}.field-heading small{color:var(--muted);font-weight:400}.required{color:#d92d20}'
            . 'input,textarea,select{width:100%;border:1px solid #cfd4dc;border-radius:.6rem;background:#fff;padding:.7rem;font:inherit;color:var(--ink);outline:none}input:focus,textarea:focus,select:focus{border-color:#6172f3;box-shadow:0 0 0 3px #6172f31f}'
            . 'textarea{min-height:8rem;resize:vertical}.markdown-input{min-height:14rem;font-family:ui-monospace,SFMono-Regular,monospace}.switch{display:flex;align-items:center;gap:.55rem}.switch input[type=checkbox]{width:auto}'
            . '.locale-tabs{display:flex;gap:.35rem;border-bottom:1px solid var(--line)}.locale-tab{min-height:2rem;padding:.35rem .6rem;border-radius:.4rem .4rem 0 0;background:transparent;color:var(--muted)}'
            . '.locale-tab.active{background:#eef2ff;color:#273a8a}.locale-panel{display:none}.locale-panel.active{display:block}.repeater{display:grid;gap:.75rem}.repeater-item{position:relative;margin:0;padding:1rem;border:1px solid var(--line);border-radius:.7rem}'
            . '.repeater-item>legend{font-weight:700}.repeater-item>.icon-button{position:absolute;top:.5rem;right:.5rem;min-height:2rem;padding:.3rem .5rem}.alt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:.7rem;margin-top:.6rem}'
            . '.alt-grid label{display:grid;gap:.3rem;color:#475467;font-size:.85rem}.footer-actions{margin-top:1.5rem}@media(max-width:760px){header{position:static;align-items:flex-start;gap:.75rem}nav{flex-wrap:wrap;justify-content:flex-end}'
            . 'main{width:100%;margin:0;border:0;border-radius:0;box-shadow:none}.builder-item{grid-template-columns:auto 1fr}.block-actions{grid-column:1/-1;justify-content:stretch}.block-actions>*{flex:1}.block-actions .button{width:100%}'
            . '.toolbar{align-items:stretch;flex-direction:column}.toolbar>.actions{justify-content:stretch}.toolbar>.actions .button{flex:1}.picker-grid{grid-template-columns:1fr}}</style>'
            . $script . '</head><body><header><a href="/admin"><strong>FlatFile CMS</strong></a><nav><a href="/admin">Panel</a>'
            . '<a href="/admin/pages">Strony</a><a href="/admin/security">Konto</a><form method="post" action="/admin/logout">'
            . $this->csrfField() . '<button type="submit" class="nav-logout">Wyloguj</button></form></nav></header><main><h1>'
            . self::escape($title) . '</h1>' . $content . '</main></body></html>', headers: [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
                'X-Frame-Options' => 'DENY',
            ]);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
