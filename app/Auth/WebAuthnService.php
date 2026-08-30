<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use JsonException;
use ReportUri\Passkeys\WebAuthn;
use Throwable;

final readonly class WebAuthnService
{
    private const string CHALLENGE_KEY = 'webauthn_challenge';
    private const string PURPOSE_KEY = 'webauthn_purpose';
    private const string ISSUED_AT_KEY = 'webauthn_issued_at';

    public function __construct(
        private WebAuthnCredentialRepository $credentials,
        private SessionStore $session,
        private string $rpName,
        private string $rpId,
    ) {}

    /** @return array<string, mixed> */
    public function registrationOptions(User $user): array
    {
        $server = $this->server();
        $existing = array_map(
            static fn(WebAuthnCredential $credential): string => $credential->credentialId(),
            $this->credentials->forUser($user->id()),
        );
        $options = $server->getCreateArgs(
            $user->webAuthnUserHandle(),
            $user->email(),
            $user->email(),
            120,
            'discouraged',
            'preferred',
            true,
            $existing,
        );

        return $this->rememberOptions($options, 'register');
    }

    /** @param array<string, mixed> $response */
    public function register(User $user, string $name, array $response): void
    {
        $challenge = $this->consumeChallenge('register');
        try {
            $data = $this->server()->processCreate(
                $this->binary($response, 'clientDataJSON'),
                $this->binary($response, 'attestationObject'),
                $challenge,
                false,
                true,
            );
        } catch (Throwable $exception) {
            throw new AuthenticationException('Security key registration failed.', previous: $exception);
        }
        $values = get_object_vars($data);
        $credentialId = $values['credentialId'] ?? null;
        $publicKey = $values['credentialPublicKey'] ?? null;
        $counter = $values['signatureCounter'] ?? 0;
        if (!is_string($credentialId) || !is_string($publicKey) || !is_int($counter)) {
            throw new AuthenticationException('Security key returned invalid registration data.');
        }
        $transports = $this->transports($response['transports'] ?? []);
        $this->credentials->add($user, $name, $credentialId, $publicKey, $counter, $transports);
    }

    /** @return array<string, mixed> */
    public function authenticationOptions(User $user): array
    {
        $credentials = $this->credentials->forUser($user->id());
        if ($credentials === []) {
            throw new AuthenticationException('No security key is registered for this account.');
        }
        $ids = array_map(
            static fn(WebAuthnCredential $credential): string => $credential->credentialId(),
            $credentials,
        );
        $options = $this->server()->getGetArgs($ids, 120, true, true, true, false, false, 'preferred');

        return $this->rememberOptions($options, 'authenticate');
    }

    /** @param array<string, mixed> $response */
    public function authenticate(User $user, array $response): void
    {
        $challenge = $this->consumeChallenge('authenticate');
        $credentialId = $this->binary($response, 'id');
        $credential = $this->credentials->findByCredentialId($credentialId);
        if ($credential === null || $credential->userId() !== $user->id()) {
            throw new AuthenticationException('Security key is not registered for this account.');
        }
        $server = $this->server();
        try {
            $server->processGet(
                $this->binary($response, 'clientDataJSON'),
                $this->binary($response, 'authenticatorData'),
                $this->binary($response, 'signature'),
                $credential->publicKey(),
                $challenge,
                $credential->signatureCounter(),
                false,
                true,
            );
        } catch (Throwable $exception) {
            throw new AuthenticationException('Security key verification failed.', previous: $exception);
        }
        $counter = $server->getSignatureCounter();
        $this->credentials->markUsed($credential, is_int($counter) ? $counter : $credential->signatureCounter());
    }

    private function server(): WebAuthn
    {
        return new WebAuthn($this->rpName, $this->rpId, true);
    }

    /** @return array<string, mixed> */
    private function rememberOptions(object $options, string $purpose): array
    {
        try {
            $json = json_encode($options, JSON_THROW_ON_ERROR);
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AuthenticationException('Unable to encode WebAuthn options.', previous: $exception);
        }
        $publicKey = is_array($data) ? ($data['publicKey'] ?? null) : null;
        if (!is_array($data) || !is_array($publicKey) || array_is_list($data)) {
            throw new AuthenticationException('WebAuthn options are invalid.');
        }
        $challenge = $publicKey['challenge'] ?? null;
        if (!is_string($challenge)) {
            throw new AuthenticationException('WebAuthn challenge is missing.');
        }
        $this->session->set(self::CHALLENGE_KEY, $challenge);
        $this->session->set(self::PURPOSE_KEY, $purpose);
        $this->session->set(self::ISSUED_AT_KEY, time());

        $normalized = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new AuthenticationException('WebAuthn option keys are invalid.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function consumeChallenge(string $purpose): string
    {
        $challenge = $this->session->get(self::CHALLENGE_KEY);
        $storedPurpose = $this->session->get(self::PURPOSE_KEY);
        $issuedAt = $this->session->get(self::ISSUED_AT_KEY);
        $this->session->remove(self::CHALLENGE_KEY);
        $this->session->remove(self::PURPOSE_KEY);
        $this->session->remove(self::ISSUED_AT_KEY);
        if (!is_string($challenge) || $storedPurpose !== $purpose || !is_int($issuedAt) || $issuedAt + 180 < time()) {
            throw new AuthenticationException('WebAuthn challenge is missing or expired.');
        }

        return $this->base64UrlDecode($challenge);
    }

    /** @param array<string, mixed> $response */
    private function binary(array $response, string $field): string
    {
        $value = $response[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new AuthenticationException(sprintf('WebAuthn field "%s" is missing.', $field));
        }

        return $this->base64UrlDecode($value);
    }

    private function base64UrlDecode(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            throw new AuthenticationException('WebAuthn data is not valid base64url.');
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if (!is_string($decoded)) {
            throw new AuthenticationException('Unable to decode WebAuthn data.');
        }

        return $decoded;
    }

    /** @return list<string> */
    private function transports(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }
        $allowed = ['usb', 'nfc', 'ble'];
        $result = [];
        foreach ($value as $transport) {
            if (is_string($transport) && in_array($transport, $allowed, true)) {
                $result[] = $transport;
            }
        }

        return array_values(array_unique($result));
    }
}
