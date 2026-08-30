<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

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
            if (!\is_string($email) || !\is_string($password)) {
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
        $script = $scripts ? '<script src="/assets/admin/auth.js" defer></script>' : '';
        $authenticated = $this->authenticator->user() !== null;
        $header = $authenticated ? '<header><a href="/admin"><strong>FlatFile CMS</strong></a><nav>'
            . '<a href="/admin">Panel</a><a href="/admin/pages">Strony</a><a href="/admin/security">Konto</a>'
            . '<form method="post" action="/admin/logout">' . $this->csrfField()
            . '<button type="submit" class="nav-logout">Wyloguj</button></form></nav></header>' : '';
        $mainClass = $authenticated ? 'admin-main' : 'auth-main';

        return Response::html(
            '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">'
            . '<meta name="csrf-token" content="' . self::escape($this->csrf->token()) . '"><title>' . self::escape($title)
            . ' — FlatFile CMS</title><style>:root{color-scheme:light;--ink:#101828;--muted:#667085;--line:#e4e7ec;--accent:#3157d5;'
            . '--accent-hover:#2848b8;--bg:#f4f6fa;--nav:#111827}*{box-sizing:border-box}body{font:15px/1.55 Inter,ui-sans-serif,system-ui,sans-serif;'
            . 'margin:0;background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased}header{position:sticky;top:0;z-index:2;display:flex;align-items:center;'
            . 'justify-content:space-between;min-height:4.25rem;padding:.75rem clamp(1rem,4vw,3rem);background:var(--nav);color:#fff;box-shadow:0 1px 0 #ffffff14}'
            . 'header>a{font-size:1.05rem;letter-spacing:-.015em}header a{color:inherit;text-decoration:none}nav{display:flex;align-items:center;gap:.35rem}nav>a{padding:.5rem .7rem;'
            . 'border-radius:.5rem;color:#d0d5dd}nav>a:hover{background:#ffffff12;color:#fff}nav form{margin:0}.nav-logout{margin-left:.35rem;padding:.45rem .75rem;'
            . 'background:transparent;border:1px solid #475467;color:#f2f4f7}.nav-logout:hover{background:#ffffff12;border-color:#667085;box-shadow:none}main{background:#fff;'
            . 'border:1px solid var(--line);border-radius:1rem;box-shadow:0 18px 45px #1018280a}.auth-main{max-width:34rem;margin:8vh auto;padding:2rem}'
            . '.admin-main{width:min(76rem,calc(100% - 2rem));margin:2rem auto;padding:clamp(1.25rem,3vw,2.25rem)}h1{margin:0 0 1.5rem;'
            . 'font-size:clamp(1.65rem,3vw,2rem);letter-spacing:-.025em}h2{letter-spacing:-.01em}p{color:#475467}.lead{margin:.2rem 0 0}.eyebrow{margin:0;'
            . 'color:var(--accent);font-size:.75rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase}label{display:grid;gap:.4rem;margin:1rem 0;color:#344054;'
            . 'font-weight:600}input,button{font:inherit;padding:.75rem;border:1px solid #cfd4dc;border-radius:.6rem}input{width:100%;outline:none}input:focus{border-color:#6172f3;'
            . 'box-shadow:0 0 0 3px #6172f31f}button,.button{display:inline-flex;align-items:center;justify-content:center;min-height:2.55rem;background:var(--accent);'
            . 'color:#fff;cursor:pointer;text-decoration:none;padding:.65rem .95rem;border:0;border-radius:.6rem;font-weight:700;line-height:1.2;transition:background .15s ease,box-shadow .15s ease,transform .15s ease}'
            . 'button:hover,.button:hover{background:var(--accent-hover);box-shadow:0 4px 12px #3157d529}button:active,.button:active{transform:translateY(1px)}'
            . '.secondary{background:#eef2ff;color:#273a8a}.secondary:hover{background:#e0e7ff;box-shadow:none}.error{padding:.7rem .85rem;border-radius:.6rem;background:#fef3f2;'
            . 'color:#b42318}.error:empty{display:none}.success{padding:.75rem 1rem;border-radius:.6rem;background:#ecfdf3;color:#027a48}.hint{color:var(--muted)}'
            . '.toolbar,.actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}.stack{display:grid;gap:.5rem}'
            . '.dashboard-links{display:grid;grid-template-columns:repeat(auto-fit,minmax(15rem,1fr));gap:1rem;margin-top:1.75rem}.dashboard-links a{display:grid;gap:.25rem;'
            . 'padding:1.3rem;border:1px solid var(--line);border-radius:.8rem;color:var(--ink);text-decoration:none;background:#fff;transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease}'
            . '.dashboard-links a:hover{border-color:#b2bafc;box-shadow:0 10px 24px #10182812;transform:translateY(-1px)}.dashboard-links span{font-weight:750}'
            . '.dashboard-links small{color:var(--muted);font-size:.87rem;font-weight:400}hr{border:0;border-top:1px solid var(--line);margin:2rem 0}'
            . '@media(max-width:650px){header{position:static;align-items:flex-start;gap:.75rem}nav{flex-wrap:wrap;justify-content:flex-end}.admin-main{width:100%;margin:0;'
            . 'border:0;border-radius:0;box-shadow:none}.auth-main{margin:0;min-height:100vh;border:0;border-radius:0;box-shadow:none}.toolbar,.actions{align-items:stretch;'
            . 'flex-direction:column}.toolbar>.button,.actions>.button,.actions>button{width:100%}}</style>'
            . $script . '</head><body>' . $header . '<main class="' . $mainClass . '"><h1>' . self::escape($title) . '</h1>'
            . $content . '</main></body></html>',
            $status,
            ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache', 'X-Frame-Options' => 'DENY'],
        );
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
