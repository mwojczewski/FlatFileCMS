<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\PasswordHasher;
use FlatFileCms\Auth\WebAuthnCredentialRepository;
use FlatFileCms\Auth\WebAuthnService;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use JsonException;

final readonly class AdminAuthController
{
    public function __construct(
        private Authenticator $authenticator,
        private CsrfTokenManager $csrf,
        private PasswordHasher $passwords,
        private WebAuthnCredentialRepository $credentials,
        private WebAuthnService $webAuthn,
    ) {}

    public function loginForm(Request $request): Response
    {
        if ($this->authenticator->user() !== null) {
            return Response::redirect('/admin');
        }

        return $this->page('Logowanie', '<form method="post" action="/admin/login">'
            . $this->csrfField() . '<label>Email<input type="email" name="email" required autocomplete="username"></label>'
            . '<label>Hasło<input type="password" name="password" required autocomplete="current-password"></label>'
            . '<button type="submit">Zaloguj się</button></form>');
    }

    public function login(Request $request): Response
    {
        try {
            $this->csrf->validate($request->parsedBody()['_csrf'] ?? null);
            $email = $request->parsedBody()['email'] ?? null;
            $password = $request->parsedBody()['password'] ?? null;
            if (!is_string($email) || !is_string($password)) {
                throw new AuthenticationException('Email and password are required.');
            }
            $requiresSecondFactor = $this->authenticator->passwordLogin($email, $password);

            return Response::redirect($requiresSecondFactor ? '/admin/2fa' : '/admin', 303);
        } catch (AuthenticationException $exception) {
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

        return $this->page('Panel', '<p>Zalogowano jako <strong>' . self::escape($user->email()) . '</strong>.</p>'
            . '<p><a href="/admin/security">Bezpieczeństwo konta</a></p>'
            . '<form method="post" action="/admin/logout">' . $this->csrfField()
            . '<button type="submit">Wyloguj</button></form>');
    }

    public function security(Request $request): Response
    {
        $user = $this->requireUser();
        $items = '';
        foreach ($this->credentials->forUser($user->id()) as $credential) {
            $items .= '<li>' . self::escape($credential->name()) . '</li>';
        }
        if ($items === '') {
            $items = '<li>Brak zarejestrowanych kluczy — logowanie wymaga tylko hasła.</li>';
        }

        return $this->page('Bezpieczeństwo konta', '<h2>Klucze bezpieczeństwa</h2><ul>' . $items . '</ul>'
            . '<form data-webauthn-register><label>Nazwa klucza<input name="key_name" maxlength="80" required value="YubiKey"></label>'
            . '<label>Aktualne hasło<input type="password" name="current_password" required autocomplete="current-password"></label>'
            . '<button type="submit">Dodaj klucz</button></form><p class="error" data-auth-error></p>'
            . '<p><a href="/admin">Wróć do panelu</a></p>', scripts: true);
    }

    public function registrationOptions(Request $request): Response
    {
        $user = $this->requireUser();
        $this->validateJsonCsrf($request);
        $data = $this->json($request);
        $password = $data['password'] ?? null;
        if (!is_string($password) || !$this->passwords->verify($password, $user->passwordHash())) {
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
        if (!is_string($name) || !is_array($credential) || array_is_list($credential)) {
            throw new HttpException(400, 'WEBAUTHN_DATA_INVALID', 'Security key data is invalid.');
        }
        $normalizedCredential = [];
        foreach ($credential as $key => $value) {
            if (!is_string($key)) {
                throw new HttpException(400, 'WEBAUTHN_DATA_INVALID', 'Security key data is invalid.');
            }
            $normalizedCredential[$key] = $value;
        }
        $this->webAuthn->register($user, $name, $normalizedCredential);

        return $this->jsonResponse(['success' => true, 'redirect' => '/admin/security']);
    }

    public function logout(Request $request): Response
    {
        $this->csrf->validate($request->parsedBody()['_csrf'] ?? null);
        $this->authenticator->logout();

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

    /** @return array<string, mixed> */
    private function json(Request $request): array
    {
        try {
            $data = json_decode($request->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new HttpException(400, 'JSON_INVALID', 'Request JSON is invalid', previous: $exception);
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new HttpException(400, 'JSON_INVALID', 'Request JSON must be an object');
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
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
        $script = $scripts ? '<script src="/assets/admin/auth.js" defer></script>' : '';

        return Response::html(
            '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">'
            . '<meta name="csrf-token" content="' . self::escape($this->csrf->token()) . '"><title>' . self::escape($title)
            . ' — FlatFile CMS</title><style>body{font:16px system-ui;margin:0;background:#f4f5f7;color:#17191d}main{max-width:34rem;margin:8vh auto;padding:2rem;background:#fff;border-radius:1rem;box-shadow:0 1rem 3rem #0001}label{display:grid;gap:.4rem;margin:1rem 0}input,button{font:inherit;padding:.75rem;border:1px solid #ccd0d7;border-radius:.55rem}button{background:#17191d;color:#fff;cursor:pointer}.error{color:#b42318}</style>'
            . $script . '</head><body><main><h1>' . self::escape($title) . '</h1>' . $content . '</main></body></html>',
            $status,
            ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache', 'X-Frame-Options' => 'DENY'],
        );
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
