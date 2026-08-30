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
        private AdminLayout $layout,
        private AuditLogger $audit,
    ) {}

    public function loginForm(Request $request): Response
    {
        if ($this->authenticator->user() !== null) {
            return Response::redirect('/admin');
        }

        $message = ($request->query()['password_reset'] ?? null) === '1'
            ? '<p class="success">Hasło zostało zmienione. Możesz się zalogować.</p>'
            : '';

        return $this->page('Logowanie', $message . '<form method="post" action="/admin/login">'
            . $this->csrfField() . '<label>Email<input type="email" name="email" required autocomplete="username"></label>'
            . '<label>Hasło<input type="password" name="password" required autocomplete="current-password"></label>'
            . '<button type="submit">Zaloguj się</button></form><p class="hint"><a href="/admin/password/forgot">Nie pamiętasz hasła?</a></p>');
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
            $requiresSecondFactor = $this->authenticator->passwordLogin($email, $password);
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
            return $this->page('Logowanie', '<p class="error">' . self::escape($exception->getMessage()) . '</p>'
                . '<p><a href="/admin/login">Spróbuj ponownie</a></p>', 401);
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
            '<p>Podłącz YubiKey i dotknij go po wyświetleniu komunikatu przeglądarki.</p>'
            . '<button type="button" data-webauthn-login>Użyj klucza</button><p class="error" data-auth-error></p>',
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

        return $this->page('Panel', '<p class="eyebrow">Pulpit</p><p class="lead">Zalogowano jako <strong>'
            . self::escape($user->email()) . '</strong>.</p><div class="dashboard-links">'
            . '<a href="/admin/pages"><span>Zarządzaj stronami</span><small>Treść, struktura i podstrony</small></a>'
            . '<a href="/admin/security"><span>Ustawienia konta</span><small>Hasło i klucze bezpieczeństwa</small></a></div>');
    }

    public function security(Request $request): Response
    {
        $user = $this->requireUser();
        $message = ($request->query()['password_changed'] ?? null) === '1'
            ? '<p class="success">Hasło zostało zmienione.</p>'
            : '';
        $items = '';
        foreach ($this->credentials->forUser($user->id()) as $credential) {
            $items .= '<li>' . self::escape($credential->name()) . '</li>';
        }
        if ($items === '') {
            $items = '<li>Brak zarejestrowanych kluczy — logowanie wymaga tylko hasła.</li>';
        }

        return $this->page('Konto', $message . '<div class="toolbar"><div><h2>Hasło</h2><p>Zmień hasło używane do logowania.</p></div>'
            . '<a class="button secondary" href="/admin/account/password">Zmień hasło</a></div>'
            . '<hr><h2>Klucze bezpieczeństwa</h2><ul>' . $items . '</ul>'
            . '<form data-webauthn-register><label>Nazwa klucza<input name="key_name" maxlength="80" required value="YubiKey"></label>'
            . '<label>Aktualne hasło<input type="password" name="current_password" required autocomplete="current-password"></label>'
            . '<button type="submit">Dodaj klucz</button></form><p class="error" data-auth-error></p>', scripts: true);
    }

    public function passwordForm(Request $request): Response
    {
        $this->requireUser();

        return $this->page('Zmiana hasła', $this->passwordFormHtml());
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
                '<p class="error">' . self::escape($exception->getMessage()) . '</p>' . $this->passwordFormHtml(),
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

        return $this->jsonResponse(['success' => true, 'redirect' => '/admin/security']);
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

    private function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::escape($this->csrf->token()) . '">';
    }

    private function passwordFormHtml(): string
    {
        return '<form method="post" action="/admin/account/password" class="stack">' . $this->csrfField()
            . '<label>Aktualne hasło<input type="password" name="current_password" required autocomplete="current-password"></label>'
            . '<label>Nowe hasło<input type="password" name="new_password" required autocomplete="new-password" minlength="8"></label>'
            . '<label>Powtórz nowe hasło<input type="password" name="new_password_confirmation" required autocomplete="new-password" minlength="8"></label>'
            . '<p class="hint">Minimum 8 znaków, mała i wielka litera, cyfra oraz znak specjalny.</p>'
            . '<div class="actions"><button type="submit">Zmień hasło</button>'
            . '<a class="button secondary" href="/admin/security">Anuluj</a></div></form>';
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
            'Konto', 'Zmiana hasła' => 'account',
            default => '',
        };

        return $this->layout->render($title, $content, $status, $active, authScript: $scripts);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
