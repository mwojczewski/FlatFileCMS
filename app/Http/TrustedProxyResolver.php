<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use InvalidArgumentException;

final readonly class TrustedProxyResolver
{
    /** @var list<array{string, int}> */
    private array $networks;

    /** @param list<string> $networks */
    public function __construct(array $networks)
    {
        $parsed = [];
        foreach ($networks as $network) {
            $parsed[] = $this->parseNetwork($network);
        }
        $this->networks = $parsed;
    }

    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            return new self([]);
        }
        $networks = [];
        foreach (explode(',', $value) as $network) {
            $network = trim($network);
            if ($network !== '') {
                $networks[] = $network;
            }
        }

        return new self($networks);
    }

    public function resolve(string $remoteAddress, ?string $forwardedFor): string
    {
        if ($forwardedFor === null || !$this->trusted($remoteAddress)) {
            return $remoteAddress;
        }
        $forwarded = array_map('trim', explode(',', $forwardedFor));
        foreach ($forwarded as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                return $remoteAddress;
            }
        }

        $current = $remoteAddress;
        for ($index = \count($forwarded) - 1; $index >= 0; --$index) {
            if (!$this->trusted($current)) {
                break;
            }
            $current = $forwarded[$index];
        }

        return $current;
    }

    private function trusted(string $address): bool
    {
        $packedAddress = inet_pton($address);
        if ($packedAddress === false) {
            return false;
        }
        foreach ($this->networks as [$packedNetwork, $prefix]) {
            if (\strlen($packedAddress) !== \strlen($packedNetwork)) {
                continue;
            }
            $bytes = intdiv($prefix, 8);
            $bits = $prefix % 8;
            if ($bytes > 0 && substr($packedAddress, 0, $bytes) !== substr($packedNetwork, 0, $bytes)) {
                continue;
            }
            if ($bits === 0) {
                return true;
            }
            $mask = (0xff << (8 - $bits)) & 0xff;
            if ((\ord($packedAddress[$bytes]) & $mask) === (\ord($packedNetwork[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{string, int} */
    private function parseNetwork(string $network): array
    {
        $address = $network;
        $prefixValue = null;
        if (str_contains($network, '/')) {
            [$address, $prefixValue] = explode('/', $network, 2);
        }

        $packed = inet_pton($address);
        if ($packed === false) {
            throw new InvalidArgumentException("Trusted proxy network \"{$network}\" is invalid.");
        }
        $maximum = \strlen($packed) * 8;
        $prefix = $prefixValue === null ? $maximum : filter_var($prefixValue, FILTER_VALIDATE_INT);
        if (!\is_int($prefix) || $prefix < 0 || $prefix > $maximum) {
            throw new InvalidArgumentException("Trusted proxy prefix \"{$network}\" is invalid.");
        }

        return [$packed, $prefix];
    }
}
