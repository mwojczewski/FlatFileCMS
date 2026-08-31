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
        private AdminView $views,
        private AdminLayout $layout,
    ) {}

    public function requestForm(Request $request): Response
    {
        return $this->layout->render('Reset hasła', $this->views->render('password-reset/request', [
            'csrfToken' => $this->csrf->token(),
        ]));
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
                $this->views->render('password-reset/message', ['type' => 'error', 'message' => 'Przekroczono limit prób. Spróbuj ponownie później.', 'url' => '/admin/login', 'label' => 'Wróć do logowania']),
                429,
            );
        }

        return $this->layout->render(
            'Sprawdź skrzynkę',
            $this->views->render('password-reset/message', ['type' => 'success', 'message' => 'Jeśli konto istnieje, wysłaliśmy wiadomość z dalszymi instrukcjami.', 'url' => '/admin/login', 'label' => 'Wróć do logowania']),
            202,
        );
    }

    public function resetForm(Request $request): Response
    {
        $token = $this->token($request->query()['token'] ?? null);
        if (!$this->passwords->isValid($token)) {
            return $this->layout->render(
                'Link wygasł',
                $this->views->render('password-reset/message', ['type' => 'error', 'message' => 'Link resetujący jest nieprawidłowy, wygasł albo został już użyty.', 'url' => '/admin/password/forgot', 'label' => 'Wygeneruj nowy link']),
                410,
            );
        }

        return $this->layout->render('Ustaw nowe hasło', $this->resetFormContent($token));
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
                $this->resetFormContent($token, $exception->getMessage()),
                422,
            );
        }

        return Response::redirect('/admin/login?password_reset=1', 303);
    }

    private function resetFormContent(string $token, string $error = ''): string
    {
        return $this->views->render('password-reset/reset', [
            'token' => $token,
            'csrfToken' => $this->csrf->token(),
            'error' => $error,
        ]);
    }

    private function token(mixed $token): string
    {
        return \is_string($token) ? $token : '';
    }

    private function validateCsrf(Request $request): void
    {
        try {
            $this->csrf->validate($request->parsedBody()['_csrf'] ?? null);
        } catch (AuthenticationException $exception) {
            throw new HttpException(403, 'CSRF_INVALID', $exception->getMessage(), previous: $exception);
        }
    }

}
