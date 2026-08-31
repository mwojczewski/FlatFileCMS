<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Mail\MailException;
use FlatFileCms\Mail\Mailer;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class PasswordResetService
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetRepository $tokens,
        private PasswordHasher $hasher,
        private PasswordPolicy $policy,
        private RateLimiter $limiter,
        private Mailer $mailer,
        private ConfigurationRepository $configuration,
        private AuditLogger $audit,
        private int $ttl,
    ) {}

    public function request(string $email, string $ip): void
    {
        $identifier = \mb_strtolower(\trim($email));
        $this->limiter->assertAllowed('password_reset_ip', $ip);
        $this->limiter->assertAllowed('password_reset_email', $identifier);
        $this->limiter->hit('password_reset_ip', $ip);
        $this->limiter->hit('password_reset_email', $identifier);

        try {
            $user = $this->users->findByEmail($identifier);
        } catch (AuthenticationException) {
            $user = null;
        }
        if ($user === null || !$user->enabled()) {
            $this->audit->log('auth.password_reset_requested', null, 'auth/password-reset', $ip);

            return;
        }

        $token = $this->token();
        $this->tokens->issue($user, $this->hash($token), \time() + $this->ttl);
        $url = $this->resetUrl($token);
        $delivered = true;
        try {
            $this->mailer->send(
                $user->email(),
                'Reset hasła — FlatFile CMS',
                "Otwórz poniższy link, aby ustawić nowe hasło:\n\n{$url}\n\nLink jest jednorazowy i wygaśnie.",
                '<p>Otwórz poniższy link, aby ustawić nowe hasło:</p><p><a href="'
                    . self::escape($url) . '">Ustaw nowe hasło</a></p><p>Link jest jednorazowy i wygaśnie.</p>',
            );
        } catch (MailException) {
            $delivered = false;
            $this->tokens->revokeForUser($user->id());
        }

        $this->audit->log(
            'auth.password_reset_requested',
            $user->id(),
            "users/{$user->id()}",
            $ip,
            ['delivered' => $delivered],
        );
    }

    public function isValid(string $token): bool
    {
        return $this->validTokenFormat($token) && $this->tokens->valid($this->hash($token), \time());
    }

    public function reset(string $token, string $password, string $confirmation, string $ip): void
    {
        if (!$this->validTokenFormat($token)) {
            throw new AuthenticationException('Password reset link is invalid or expired.');
        }
        if (!\hash_equals($password, $confirmation)) {
            throw new InvalidArgumentException('New password confirmation does not match.');
        }
        $this->policy->validate($password);
        $user = $this->tokens->claim($this->hash($token), \time());
        $this->users->updatePassword($user, $this->hasher->hash($password));
        $this->tokens->revokeForUser($user->id());
        $this->audit->log('auth.password_reset', $user->id(), "users/{$user->id()}", $ip);
    }

    private function resetUrl(string $token): string
    {
        $configuration = $this->configuration->get()->data();
        $site = ContentData::map($configuration['site'] ?? null, 'site');
        $baseUrl = \rtrim(ContentData::string($site['url'] ?? null, 'site.url'), '/');

        return "{$baseUrl}/admin/password/reset?token=" . \rawurlencode($token);
    }

    private function token(): string
    {
        return \rtrim(\strtr(\base64_encode(\random_bytes(32)), '+/', '-_'), '=');
    }

    private function hash(string $token): string
    {
        return \hash('sha256', $token);
    }

    private function validTokenFormat(string $token): bool
    {
        return \preg_match('/^[A-Za-z0-9_-]{43}$/D', $token) === 1;
    }

    private static function escape(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
