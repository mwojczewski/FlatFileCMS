<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\PasswordChanger;
use FlatFileCms\Auth\PasswordHasher;
use FlatFileCms\Auth\WebAuthnCredentialRepository;
use FlatFileCms\Auth\WebAuthnService;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use InvalidArgumentException;
use JsonException;

final readonly class AdminAuthController
{
    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private PasswordChanger $passwordChanger,
        private PasswordHasher $passwords,
        private WebAuthnCredentialRepository $credentials,
        private WebAuthnService $webAuthn,
        private AdminView $views,
        private AdminLayout $layout,
        private AuditLogger $audit,
    ) {}

    public function loginForm(Request $request): Response
    {
        if ($this->authenticator->user() !== null) {
            return Response::redirect('/admin');
        }

        return $this->page('Logowanie', $this->views->render('auth/login', [
            'csrfToken' => $this->csrf->token(),
            'passwordReset' => ($request->query()['password_reset'] ?? null) === '1',
            'error' => '',
        ]));
    }

    public function login(Request $request): Response
    {
        try {
            $this->csrf->validate($request->parsedBody()['_csrf'] ?? null);
            $email = $request->parsedBody()['email'] ?? null;
            $password = $request->parsedBody()['password'] ?? null;
            if (!\is_string($email) || !\is_string($password)) {
                throw new AuthenticationException('Email and password are required.');
            }
            $requiresSecondFactor = $this->authenticator->passwordLogin($email, $password, $request->clientIp());
            if (!$requiresSecondFactor) {
                $this->audit->log(
                    'auth.login',
                    $this->authenticator->requireUser()->id(),
                    'auth/session',
                    $request->clientIp(),
                );
            }

            return Response::redirect($requiresSecondFactor ? '/admin/2fa' : '/admin', 303);
        } catch (AuthenticationException $exception) {
            $this->audit->log('auth.login_failed', null, 'auth/session', $request->clientIp());
            return $this->page('Logowanie', $this->views->render('auth/login', [
                'csrfToken' => $this->csrf->token(),
                'passwordReset' => false,
                'error' => $exception->getMessage(),
            ]), 401);
        }
    }

    public function secondFactor(Request $request): Response
    {
        try {
            $this->authenticator->pendingUser();
        } catch (AuthenticationException) {
            return Response::redirect('/admin/login');
        }

        return $this->page(
            'Klucz bezpieczeństwa',
            $this->views->render('auth/second-factor'),
            scripts: true,
        );
    }

    public function authenticationOptions(Request $request): Response
    {
        try {
            $this->csrf->validate($request->header('x-csrf-token'));

            return $this->jsonResponse($this->webAuthn->authenticationOptions($this->authenticator->pendingUser()));
        } catch (AuthenticationException $exception) {
            throw new HttpException(401, 'WEBAUTHN_AUTHENTICATION_FAILED', $exception->getMessage());
        }
    }

    public function authenticationVerify(Request $request): Response
    {
        try {
            $this->csrf->validate($request->header('x-csrf-token'));
            $user = $this->authenticator->pendingUser();
            $this->webAuthn->authenticate($user, $this->json($request));
            $this->authenticator->complete($user);
            $this->audit->log('auth.login', $user->id(), 'auth/session', $request->clientIp(), ['second_factor' => true]);

            return $this->jsonResponse(['success' => true, 'redirect' => '/admin']);
        } catch (AuthenticationException $exception) {
            throw new HttpException(401, 'WEBAUTHN_AUTHENTICATION_FAILED', $exception->getMessage());
        }
    }

    public function dashboard(Request $request): Response
    {
        $user = $this->authenticator->user();
        if ($user === null) {
            return Response::redirect('/admin/login');
        }

        return $this->page('Panel', $this->views->render('dashboard', ['user' => $user]));
    }

    public function security(Request $request): Response
    {
        $user = $this->requireUser();
        return $this->page('Konto', $this->views->render('account/index', [
            'passwordChanged' => ($request->query()['password_changed'] ?? null) === '1',
            'credentialCount' => \count($this->credentials->forUser($user->id())),
        ]));
    }

    public function securityKeys(Request $request): Response
    {
        $user = $this->requireUser();

        return $this->page('Klucze bezpieczeństwa', $this->views->render('account/security-keys', [
            'credentials' => $this->credentials->forUser($user->id()),
            'csrfToken' => $this->csrf->token(),
        ]), scripts: true);
    }

    public function deleteSecurityKey(Request $request): Response
    {
        $user = $this->requireUser();
        $this->validateFormCsrf($request);
        $id = $request->parsedBody()['id'] ?? null;
        if (!\is_string($id) || preg_match('/^[1-9][0-9]*$/D', $id) !== 1) {
            throw new HttpException(400, 'WEBAUTHN_CREDENTIAL_INVALID', 'Security key identifier is invalid.');
        }
        $this->credentials->deleteForUser((int) $id, $user->id());
        $this->audit->log('auth.security_key_deleted', $user->id(), "users/{$user->id()}/credentials/{$id}", $request->clientIp());

        return Response::redirect('/admin/account/security-keys?deleted=1', 303);
    }

    public function passwordForm(Request $request): Response
    {
        $this->requireUser();

        return $this->page('Zmiana hasła', $this->views->render('account/password', [
            'csrfToken' => $this->csrf->token(),
            'error' => '',
        ]));
    }

    public function changePassword(Request $request): Response
    {
        $user = $this->requireUser();
        $this->validateFormCsrf($request);
        try {
            $currentPassword = $request->parsedBody()['current_password'] ?? null;
            $newPassword = $request->parsedBody()['new_password'] ?? null;
            $confirmation = $request->parsedBody()['new_password_confirmation'] ?? null;
            if (!\is_string($currentPassword) || !\is_string($newPassword) || !\is_string($confirmation)) {
                throw new InvalidArgumentException('All password fields are required.');
            }
            $updatedUser = $this->passwordChanger->change($user, $currentPassword, $newPassword, $confirmation);
            $this->authenticator->complete($updatedUser);
            $this->audit->log('auth.password_changed', $user->id(), "users/{$user->id()}", $request->clientIp());

            return Response::redirect('/admin/security?password_changed=1', 303);
        } catch (AuthenticationException | InvalidArgumentException $exception) {
            return $this->page(
                'Zmiana hasła',
                $this->views->render('account/password', [
                    'csrfToken' => $this->csrf->token(),
                    'error' => $exception->getMessage(),
                ]),
                422,
            );
        }
    }

    public function registrationOptions(Request $request): Response
    {
        $user = $this->requireUser();
        $this->validateJsonCsrf($request);
        $data = $this->json($request);
        $password = $data['password'] ?? null;
        if (!\is_string($password) || !$this->passwords->verify($password, $user->passwordHash())) {
            throw new HttpException(401, 'PASSWORD_INVALID', 'Current password is invalid.');
        }

        return $this->jsonResponse($this->webAuthn->registrationOptions($user));
    }

    public function registrationVerify(Request $request): Response
    {
        $user = $this->requireUser();
        $this->validateJsonCsrf($request);
        $data = $this->json($request);
        $name = $data['name'] ?? null;
        $credential = $data['credential'] ?? null;
        if (!\is_string($name) || !\is_array($credential) || array_is_list($credential)) {
            throw new HttpException(400, 'WEBAUTHN_DATA_INVALID', 'Security key data is invalid.');
        }
        $normalizedCredential = [];
        foreach ($credential as $key => $value) {
            if (!\is_string($key)) {
                throw new HttpException(400, 'WEBAUTHN_DATA_INVALID', 'Security key data is invalid.');
            }
            $normalizedCredential[$key] = $value;
        }
        $this->webAuthn->register($user, $name, $normalizedCredential);

        return $this->jsonResponse(['success' => true, 'redirect' => '/admin/account/security-keys']);
    }

    public function logout(Request $request): Response
    {
        $this->validateFormCsrf($request);
        $user = $this->authenticator->user();
        $this->authenticator->logout();
        $this->audit->log('auth.logout', $user?->id(), 'auth/session', $request->clientIp());

        return Response::redirect('/admin/login', 303);
    }

    private function requireUser(): \FlatFileCms\Auth\User
    {
        try {
            return $this->authenticator->requireUser();
        } catch (AuthenticationException) {
            throw new HttpException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required');
        }
    }

    private function validateJsonCsrf(Request $request): void
    {
        try {
            $this->csrf->validate($request->header('x-csrf-token'));
        } catch (AuthenticationException $exception) {
            throw new HttpException(403, 'CSRF_INVALID', $exception->getMessage());
        }
    }

    private function validateFormCsrf(Request $request): void
    {
        try {
            $this->csrf->validate($request->parsedBody()['_csrf'] ?? null);
        } catch (AuthenticationException $exception) {
            throw new HttpException(403, 'CSRF_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    /** @return array<string, mixed> */
    private function json(Request $request): array
    {
        try {
            $data = json_decode($request->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new HttpException(400, 'JSON_INVALID', 'Request JSON is invalid', previous: $exception);
        }
        if (!\is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new HttpException(400, 'JSON_INVALID', 'Request JSON must be an object');
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                throw new HttpException(400, 'JSON_INVALID', 'Request JSON object keys must be strings');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $data */
    private function jsonResponse(array $data): Response
    {
        return Response::json($data, headers: [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function page(string $title, string $content, int $status = 200, bool $scripts = false): Response
    {
        $active = match ($title) {
            'Panel' => 'dashboard',
            'Konto', 'Zmiana hasła', 'Klucze bezpieczeństwa' => 'account',
            default => '',
        };

        return $this->layout->render($title, $content, $status, $active, authScript: $scripts);
    }

}
