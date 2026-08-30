<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

final readonly class Authenticator
{
    private const string AUTHENTICATED_USER = 'authenticated_user_id';
    private const string PENDING_USER = 'pending_2fa_user_id';
    private const string PENDING_SINCE = 'pending_2fa_since';

    public function __construct(
        private UserRepository $users,
        private WebAuthnCredentialRepository $credentials,
        private PasswordHasher $passwords,
        private SessionStore $session,
        private RateLimiter $limiter,
    ) {}

    public function passwordLogin(string $email, string $password): bool
    {
        $identifier = mb_strtolower(trim($email));
        $this->limiter->assertAllowed('login', $identifier);
        $user = $this->users->findByEmail($identifier);
        $verificationHash = $user?->passwordHash()
            ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $validPassword = $this->passwords->verify($password, $verificationHash);
        if ($user === null || !$user->enabled() || !$validPassword) {
            $this->limiter->hit('login', $identifier);
            throw new AuthenticationException('Invalid email or password.');
        }
        $this->limiter->clear('login', $identifier);

        if ($this->credentials->forUser($user->id()) !== []) {
            $this->session->set(self::PENDING_USER, $user->id());
            $this->session->set(self::PENDING_SINCE, time());
            $this->session->remove(self::AUTHENTICATED_USER);

            return true;
        }

        $this->complete($user);

        return false;
    }

    public function pendingUser(): User
    {
        $id = $this->session->get(self::PENDING_USER);
        $since = $this->session->get(self::PENDING_SINCE);
        if (!is_int($id) || !is_int($since) || $since + 300 < time()) {
            $this->session->remove(self::PENDING_USER);
            $this->session->remove(self::PENDING_SINCE);
            throw new AuthenticationException('Second-factor authentication is not pending.');
        }

        return $this->users->get($id);
    }

    public function complete(User $user): void
    {
        $this->session->regenerate();
        $this->session->set(self::AUTHENTICATED_USER, $user->id());
        $this->session->remove(self::PENDING_USER);
        $this->session->remove(self::PENDING_SINCE);
    }

    public function user(): ?User
    {
        $id = $this->session->get(self::AUTHENTICATED_USER);
        if (!is_int($id)) {
            return null;
        }
        $user = $this->users->get($id);

        return $user->enabled() ? $user : null;
    }

    public function requireUser(): User
    {
        return $this->user() ?? throw new AuthenticationException('Authentication required.');
    }

    public function logout(): void
    {
        $this->session->invalidate();
    }
}
