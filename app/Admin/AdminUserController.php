<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AdminUserManager;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\Role;
use FlatFileCms\Auth\User;
use FlatFileCms\Auth\UserNotFoundException;
use FlatFileCms\Auth\UserRepository;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use InvalidArgumentException;

final readonly class AdminUserController
{
    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private UserRepository $users,
        private AdminUserManager $manager,
        private AdminView $views,
        private AdminLayout $layout,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $this->requireUser();

        return $this->page('Administratorzy', 'users/index', [
            'users' => $this->users->visibleTo($actor),
            'actor' => $actor,
            'csrfToken' => $this->csrf->token(),
        ]);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->requireUser();

        return $this->page('Nowy administrator', 'users/form', [
            'user' => null,
            'actor' => $actor,
            'csrfToken' => $this->csrf->token(),
            'error' => '',
        ]);
    }

    public function create(Request $request): Response
    {
        $actor = $this->requireUser();
        $this->validateCsrf($request);
        try {
            $user = $this->manager->create(
                $actor,
                $this->bodyString($request, 'email'),
                $this->bodyString($request, 'password'),
                $this->bodyString($request, 'password_confirmation'),
            );
            $this->audit->log('user.created', $actor->id(), "users/{$user->id()}", $request->clientIp());

            return Response::redirect('/admin/users?created=1', 303);
        } catch (AuthenticationException|InvalidArgumentException $exception) {
            return $this->page('Nowy administrator', 'users/form', [
                'user' => null,
                'actor' => $actor,
                'email' => $this->optionalBodyString($request, 'email'),
                'csrfToken' => $this->csrf->token(),
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function editForm(Request $request): Response
    {
        $actor = $this->requireUser();
        $user = $this->visibleAdmin($this->queryId($request), $actor);

        return $this->page('Edycja administratora', 'users/form', [
            'user' => $user,
            'actor' => $actor,
            'csrfToken' => $this->csrf->token(),
            'error' => '',
        ]);
    }

    public function update(Request $request): Response
    {
        $actor = $this->requireUser();
        $this->validateCsrf($request);
        $id = $this->bodyId($request);
        try {
            $user = $this->manager->update(
                $actor,
                $id,
                $this->bodyString($request, 'email'),
                ($request->parsedBody()['enabled'] ?? null) === '1',
                $this->optionalBodyString($request, 'password'),
                $this->optionalBodyString($request, 'password_confirmation'),
            );
            $this->audit->log('user.updated', $actor->id(), "users/{$user->id()}", $request->clientIp());

            return Response::redirect("/admin/users/edit?id={$user->id()}&saved=1", 303);
        } catch (AuthenticationException|InvalidArgumentException|UserNotFoundException $exception) {
            $user = $this->visibleAdmin($id, $actor);

            return $this->page('Edycja administratora', 'users/form', [
                'user' => $user,
                'actor' => $actor,
                'email' => $this->optionalBodyString($request, 'email'),
                'enabled' => ($request->parsedBody()['enabled'] ?? null) === '1',
                'csrfToken' => $this->csrf->token(),
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function delete(Request $request): Response
    {
        $actor = $this->requireUser();
        $this->validateCsrf($request);
        $id = $this->bodyId($request);
        try {
            $this->manager->delete($actor, $id);
            $this->audit->log('user.deleted', $actor->id(), "users/{$id}", $request->clientIp());

            return Response::redirect('/admin/users?deleted=1', 303);
        } catch (AuthenticationException|InvalidArgumentException|UserNotFoundException $exception) {
            throw new HttpException(422, 'USER_DELETE_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    private function visibleAdmin(int $id, User $actor): User
    {
        try {
            $user = $this->users->getVisibleTo($id, $actor);
            if ($user->role() !== Role::Admin) {
                throw new UserNotFoundException('User not found.');
            }

            return $user;
        } catch (UserNotFoundException $exception) {
            throw new HttpException(404, 'USER_NOT_FOUND', 'User not found.', previous: $exception);
        }
    }

    private function queryId(Request $request): int
    {
        $value = $request->query()['id'] ?? null;

        return $this->id($value);
    }

    private function bodyId(Request $request): int
    {
        return $this->id($request->parsedBody()['id'] ?? null);
    }

    private function id(mixed $value): int
    {
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new HttpException(400, 'USER_ID_INVALID', 'User identifier is invalid.');
        }

        return (int) $value;
    }

    private function bodyString(Request $request, string $key): string
    {
        $value = $request->parsedBody()[$key] ?? null;
        if (!is_string($value)) {
            throw new InvalidArgumentException("Field {$key} is required.");
        }

        return $value;
    }

    private function optionalBodyString(Request $request, string $key): string
    {
        $value = $request->parsedBody()[$key] ?? '';

        return is_string($value) ? $value : '';
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

    /** @param array<string, mixed> $data */
    private function page(string $title, string $view, array $data, int $status = 200): Response
    {
        return $this->layout->render($title, $this->views->render($view, $data), $status, active: 'users');
    }
}
