<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\User;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Redirects\RedirectManager;
use FlatFileCms\Redirects\RedirectRepository;
use InvalidArgumentException;

final readonly class AdminRedirectController
{
    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private RedirectRepository $redirects,
        private RedirectManager $manager,
        private AdminView $views,
        private AdminLayout $layout,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->requireUser();
        $document = $this->redirects->get();

        return $this->layout->render('Przekierowania', $this->views->render('redirects/index', [
            'rules' => $document->rules(),
            'revision' => $document->revision()->value(),
            'csrfToken' => $this->csrf->token(),
        ]), active: 'redirects');
    }

    public function create(Request $request): Response
    {
        return $this->change($request, 'create');
    }

    public function update(Request $request): Response
    {
        return $this->change($request, 'update');
    }

    public function delete(Request $request): Response
    {
        return $this->change($request, 'delete');
    }

    private function change(Request $request, string $operation): Response
    {
        $actor = $this->requireUser();
        try {
            $this->validateCsrf($request);
            $body = $request->parsedBody();
            $revision = $this->revision($body['revision'] ?? null);
            if ($operation === 'delete') {
                $id = $this->requiredString($body['id'] ?? null, 'Redirect ID');
                $this->manager->delete($id, $revision);
            } else {
                $source = $this->requiredString($body['source'] ?? null, 'Source');
                $target = $this->requiredString($body['target'] ?? null, 'Target');
                $status = $this->status($body['status'] ?? null);
                $enabled = ($body['enabled'] ?? null) === '1';
                if ($operation === 'create') {
                    $this->manager->create($source, $target, $status, $enabled, $revision);
                } else {
                    $id = $this->requiredString($body['id'] ?? null, 'Redirect ID');
                    $this->manager->update($id, $source, $target, $status, $enabled, $revision);
                }
            }
            $this->audit->log("redirect.{$operation}d", $actor->id(), 'config/redirects.yml', $request->clientIp());

            return Response::redirect('/admin/redirects?saved=1', 303);
        } catch (RevisionConflictException $exception) {
            throw new HttpException(409, 'REDIRECT_REVISION_CONFLICT', 'Redirect rules changed in another session.', previous: $exception);
        } catch (InvalidArgumentException|InvalidContentException $exception) {
            throw new HttpException(422, 'REDIRECT_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    private function status(mixed $value): int
    {
        if (!\is_string($value) || \preg_match('/^30[12378]$/D', $value) !== 1) {
            throw new InvalidArgumentException('Redirect status is invalid.');
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

    private function revision(mixed $value): FileRevision
    {
        if (!\is_string($value)) {
            throw new InvalidArgumentException('Redirect revision is required.');
        }

        return FileRevision::fromString($value);
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
