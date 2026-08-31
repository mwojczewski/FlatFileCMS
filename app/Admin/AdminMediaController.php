<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\User;
use FlatFileCms\Content\PageBlockManager;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Media\MediaException;
use FlatFileCms\Media\MediaManager;
use FlatFileCms\Media\MediaName;
use FlatFileCms\Media\MediaRepository;
use FlatFileCms\Media\MediaTypes;
use FlatFileCms\Media\MediaUrlGenerator;
use InvalidArgumentException;

final readonly class AdminMediaController
{
    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private PageBlockManager $pages,
        private MediaRepository $media,
        private MediaManager $manager,
        private MediaUrlGenerator $urls,
        private AdminView $views,
        private AdminLayout $layout,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->requireUser();
        $identity = $this->queryIdentity($request);
        $this->assertPage($identity);
        $items = [];
        foreach ($this->media->all($identity) as $item) {
            $url = $this->urls->original($identity, $item);
            $items[] = [
                'item' => $item,
                'url' => $url,
                'preview' => MediaTypes::isTransformable($item->mimeType()) ? "{$url}?w=480&h=320&format=webp" : $url,
                'size' => $this->size($item->size()),
            ];
        }
        $content = $this->views->render('media/index', [
            'identity' => $identity,
            'items' => $items,
            'csrfToken' => $this->csrf->token(),
        ]);

        return $this->layout->render('Multimedia strony', $content, active: 'pages', builderScript: true);
    }

    public function upload(Request $request): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $identity = $this->bodyIdentity($request);
            $upload = $request->file('media');
            if ($upload === null) {
                throw new MediaException('Wybierz plik do przesłania.');
            }
            $item = $this->manager->upload($identity, $upload);
            $this->audit->log(
                'media.uploaded',
                $actor->id(),
                "pages/{$identity->value()}/{$item->name()->value()}",
                $request->clientIp(),
                ['mime' => $item->mimeType(), 'size' => $item->size()],
            );

            return $this->redirect($identity, 'uploaded');
        } catch (MediaException | InvalidArgumentException $exception) {
            throw new HttpException(422, 'MEDIA_UPLOAD_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function delete(Request $request): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $identity = $this->bodyIdentity($request);
            $name = $this->name($request->parsedBody()['name'] ?? null);
            $this->manager->delete($identity, $name);
            $this->audit->log(
                'media.deleted',
                $actor->id(),
                "pages/{$identity->value()}/{$name->value()}",
                $request->clientIp(),
            );

            return $this->redirect($identity, 'deleted');
        } catch (MediaException | InvalidArgumentException $exception) {
            throw new HttpException(422, 'MEDIA_DELETE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    public function picker(Request $request): Response
    {
        $this->requireUser();
        $identity = $this->queryIdentity($request);
        $this->assertPage($identity);
        $kind = $request->query()['kind'] ?? 'file';
        if (!\is_string($kind) || !\in_array($kind, ['file', 'image'], true)) {
            throw new HttpException(400, 'MEDIA_KIND_INVALID', 'Media picker kind is invalid.');
        }
        $items = [];
        foreach ($this->media->all($identity) as $item) {
            if ($kind === 'image' && !$item->isImage()) {
                continue;
            }
            $url = $this->urls->original($identity, $item);
            $items[] = [
                'name' => $item->name()->value(),
                'mime' => $item->mimeType(),
                'size' => $item->size(),
                'url' => $url,
                'thumbnail' => MediaTypes::isTransformable($item->mimeType()) ? "{$url}?w=320&h=220&format=webp" : $url,
                'image' => $item->isImage(),
            ];
        }

        return Response::json(['items' => $items, 'manageUrl' => '/admin/media?path=' . rawurlencode($identity->value())], headers: [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function assertPage(PageIdentity $identity): void
    {
        try {
            $this->pages->editable($identity);
        } catch (\Throwable $exception) {
            throw new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found.', previous: $exception);
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

        return PageIdentity::fromString(trim($value, '/'));
    }

    private function name(mixed $value): MediaName
    {
        if (!\is_string($value)) {
            throw new InvalidArgumentException('Media filename is required.');
        }

        return MediaName::fromString($value);
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
        return Response::redirect('/admin/media?path=' . rawurlencode($identity->value()) . "&{$status}=1", 303);
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1_048_576
            ? number_format($bytes / 1_048_576, 1, ',', ' ') . ' MB'
            : number_format($bytes / 1024, 1, ',', ' ') . ' KB';
    }

}
