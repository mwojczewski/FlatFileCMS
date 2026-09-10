<?php

declare(strict_types=1);

namespace FlatFileCms\Support;

final class UuidV7
{
    public static function isValid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) === 1;
    }

    public static function generate(): string
    {
        $milliseconds = (int) floor(microtime(true) * 1000);
        $timestamp = str_pad(dechex($milliseconds), 12, '0', STR_PAD_LEFT);
        $random = bin2hex(random_bytes(10));
        $variant = dechex((hexdec($random[3]) & 0x3) | 0x8);

        return substr($timestamp, 0, 8) . '-'
            . substr($timestamp, 8, 4) . '-7'
            . substr($random, 0, 3) . '-'
            . $variant . substr($random, 4, 3) . '-'
            . substr($random, 7, 12);
    }
}
