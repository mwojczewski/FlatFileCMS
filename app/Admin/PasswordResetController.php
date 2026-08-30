<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\PasswordResetService;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use InvalidArgumentException;

final readonly class PasswordResetController
{
    public function __construct(
        private CsrfTokenManager $csrf,
        private PasswordResetService $passwords,
        private AdminLayout $layout,
    ) {}

    public function requestForm(Request $request): Response
    {
        return $this->layout->render('Reset hasła', '<p class="lead">Podaj adres przypisany do konta administratora.</p>'
            . '<form method="post" action="/admin/password/forgot">' . $this->csrfField()
            . '<label>Email<input type="email" name="email" required autocomplete="email"></label>'
            . '<button type="submit">Wyślij link resetujący</button></form>'
            . '<p class="hint"><a href="/admin/login">Wróć do logowania</a></p>');
    }

    public function request(Request $request): Response
    {
        $this->validateCsrf($request);
        $email = $request->parsedBody()['email'] ?? null;
        if (!\is_string($email)) {
            throw new HttpException(422, 'EMAIL_REQUIRED', 'Email address is required.');
        }

        try {
            $this->passwords->request($email, $request->clientIp());
        } catch (AuthenticationException) {
            return $this->layout->render(
                'Reset hasła',
                '<p class="error">Przekroczono limit prób. Spróbuj ponownie później.</p>',
                429,
            );
        }

        return $this->layout->render(
            'Sprawdź skrzynkę',
            '<p class="success">Jeśli konto istnieje, wysłaliśmy wiadomość z dalszymi instrukcjami.</p>'
                . '<p><a class="button secondary" href="/admin/login">Wróć do logowania</a></p>',
            202,
        );
    }

    public function resetForm(Request $request): Response
    {
        $token = $this->token($request->query()['token'] ?? null);
        if (!$this->passwords->isValid($token)) {
            return $this->layout->render(
                'Link wygasł',
                '<p class="error">Link resetujący jest nieprawidłowy, wygasł albo został już użyty.</p>'
                    . '<p><a class="button secondary" href="/admin/password/forgot">Wygeneruj nowy link</a></p>',
                410,
            );
        }

        return $this->layout->render('Ustaw nowe hasło', $this->resetFormHtml($token));
    }

    public function reset(Request $request): Response
    {
        $this->validateCsrf($request);
        $token = $this->token($request->parsedBody()['token'] ?? null);
        $password = $request->parsedBody()['password'] ?? null;
        $confirmation = $request->parsedBody()['password_confirmation'] ?? null;
        if (!\is_string($password) || !\is_string($confirmation)) {
            throw new HttpException(422, 'PASSWORD_REQUIRED', 'Password and confirmation are required.');
        }

        try {
            $this->passwords->reset($token, $password, $confirmation, $request->clientIp());
        } catch (AuthenticationException | InvalidArgumentException $exception) {
            return $this->layout->render(
                'Ustaw nowe hasło',
                '<p class="error">' . self::escape($exception->getMessage()) . '</p>' . $this->resetFormHtml($token),
                422,
            );
        }

        return Response::redirect('/admin/login?password_reset=1', 303);
    }

    private function resetFormHtml(string $token): string
    {
        return '<form method="post" action="/admin/password/reset" class="stack">' . $this->csrfField()
            . '<input type="hidden" name="token" value="' . self::escape($token) . '">'
            . '<label>Nowe hasło<input type="password" name="password" required autocomplete="new-password" minlength="8"></label>'
            . '<label>Powtórz hasło<input type="password" name="password_confirmation" required autocomplete="new-password" minlength="8"></label>'
            . '<p class="hint">Minimum 8 znaków, mała i wielka litera, cyfra oraz znak specjalny.</p>'
            . '<button type="submit">Zapisz nowe hasło</button></form>';
    }

    private function token(mixed $token): string
    {
        return \is_string($token) ? $token : '';
    }

    private function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::escape($this->csrf->token()) . '">';
    }

    private function validateCsrf(Request $request): void
    {
        try {
            $this->csrf->validate($request->parsedBody()['_csrf'] ?? null);
        } catch (AuthenticationException $exception) {
            throw new HttpException(403, 'CSRF_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
