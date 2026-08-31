<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use InvalidArgumentException;

final readonly class MediaName
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (
            \strlen($value) > 180
            || \preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?\.[A-Za-z0-9]{2,5}$/D', $value) !== 1
            || \str_contains($value, '..')
            || \in_array(\strtolower($value), ['content.yml', 'pagination.yml'], true)
        ) {
            throw new InvalidArgumentException('Media filename is invalid.');
        }

        return new self($value);
    }

    public static function fromUpload(string $clientFilename, string $mimeType): self
    {
        $extension = MediaTypes::extension($mimeType);
        if ($extension === null) {
            throw new InvalidArgumentException('Uploaded media type has no safe extension mapping.');
        }
        $basename = \pathinfo(\basename(\str_replace('\\', '/', $clientFilename)), PATHINFO_FILENAME);
        $basename = \strtr(\mb_strtolower($basename, 'UTF-8'), [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ]);
        if (\function_exists('iconv')) {
            $transliterated = \iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $basename);
            if (\is_string($transliterated)) {
                $basename = $transliterated;
            }
        }
        $basename = \strtolower($basename);
        $basename = \preg_replace('/[^a-z0-9]+/', '-', $basename);
        $basename = \is_string($basename) ? \trim($basename, '-') : '';
        if ($basename === '') {
            $basename = 'media';
        }
        $basename = \substr($basename, 0, 160);

        return self::fromString("{$basename}.{$extension}");
    }

    public function value(): string
    {
        return $this->value;
    }
}
