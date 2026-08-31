<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\User;
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
        private AdminView $views,
        private AdminLayout $layout,
        private AuditLogger $audit,
    ) {
    }

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
        $viewBlocks = [];
        $languages = $this->languages->get();
        foreach ($blocks as $position => $block) {
            $id = ContentData::string($block['id'] ?? null, 'block.id');
            $type = ContentData::string($block['type'] ?? null, 'block.type');
            $enabled = $block['enabled'] ?? true;
            if (!\is_bool($enabled)) {
                throw new HttpException(422, 'BLOCK_STATE_INVALID', 'Block enabled state is invalid.');
            }
            $definition = $this->registry->get($type);
            $viewBlocks[] = [
                'id' => $id,
                'type' => $type,
                'enabled' => $enabled,
                'position' => $position + 1,
                'name' => $this->localized($definition->name(), $languages, $type),
            ];
        }
        $content = $this->views->render('builder/index', [
            'identity' => $identity,
            'blocks' => $viewBlocks,
            'revision' => $editable->revision(),
            'csrfToken' => $this->csrf->token(),
        ]);

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
        $cards = [];
        foreach ($this->registry->all() as $definition) {
            $name = $this->localized($definition->name(), $languages, $definition->type());
            $description = $this->localized($definition->description(), $languages, '');
            $cards[] = [
                'definition' => $definition,
                'name' => $name,
                'description' => $description,
                'preview' => is_file($definition->directory() . '/preview.webp'),
            ];
        }

        return $this->page('Wybierz blok', $this->views->render('builder/picker', [
            'identity' => $identity,
            'cards' => $cards,
        ]));
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
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $identity = $this->bodyIdentity($request);
            $languages = $this->languages->get();
            $definition = $this->bodyDefinition($request);
            $data = $this->dataMapper->map($definition, $request->parsedBody()['data'] ?? [], $languages);
            $this->manager->add($identity, $definition->type(), $data, $this->bodyRevision($request), $languages);
            $this->audit->log(
                'block.created',
                $actor->id(),
                "pages/{$identity->value()}",
                $request->clientIp(),
                ['type' => $definition->type()],
            );

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
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $identity = $this->bodyIdentity($request);
            $id = $this->bodyId($request);
            $languages = $this->languages->get();
            $block = $this->manager->block($identity, $id);
            $definition = $this->registry->get(ContentData::string($block['type'] ?? null, 'block.type'));
            $data = $this->dataMapper->map($definition, $request->parsedBody()['data'] ?? [], $languages);
            $this->manager->update($identity, $id, $data, $this->bodyRevision($request), $languages);
            $this->audit->log(
                'block.updated',
                $actor->id(),
                "pages/{$identity->value()}/blocks/{$id}",
                $request->clientIp(),
            );

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
        $actor = $this->requireUser();
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
            $this->audit->log(
                'block.moved',
                $actor->id(),
                "pages/{$identity->value()}/blocks",
                $request->clientIp(),
                ['order' => $order],
            );

            return $this->redirect($identity, 'reordered');
        } catch (RevisionConflictException $exception) {
            throw $this->conflict($exception);
        } catch (InvalidArgumentException | InvalidContentException | FilesystemException $exception) {
            throw new HttpException(422, 'BLOCK_REORDER_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    private function simpleMutation(Request $request, string $operation): Response
    {
        $actor = $this->requireUser();
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
            $action = match ($operation) {
                'duplicate' => 'block.created',
                'toggle' => 'block.updated',
                'delete' => 'block.deleted',
            };
            $this->audit->log(
                $action,
                $actor->id(),
                "pages/{$identity->value()}/blocks/{$id}",
                $request->clientIp(),
                ['operation' => $operation],
            );

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
        $content = $this->views->render('builder/form', [
            'name' => $name,
            'action' => $action,
            'identity' => $identity,
            'definition' => $definition,
            'revision' => $revision,
            'id' => $id,
            'fields' => $this->forms->render($definition, $languages, $data),
            'csrfToken' => $this->csrf->token(),
        ]);

        return $this->page($title, $content, scripts: true);
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

    private function requireUser(): User
    {
        try {
            return $this->authenticator->requireUser();
        } catch (AuthenticationException $exception) {
            throw new HttpException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required.', previous: $exception);
        }
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
        return $this->layout->render(
            $title,
            $content,
            active: 'pages',
            builderScript: $scripts,
            markdownEditor: $scripts,
        );
    }

}
