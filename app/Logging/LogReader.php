<?php

declare(strict_types=1);

namespace FlatFileCms\Logging;

use DateTimeImmutable;
use JsonException;

final readonly class LogReader
{
    private const int MAX_ENTRIES = 500;
    private string $directory;

    public function __construct(string $projectRoot)
    {
        $this->directory = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/storage/logs';
    }

    /** @return list<array{name: string, size: int, modified: int}> */
    public function files(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = [];
        foreach (scandir($this->directory) ?: [] as $name) {
            if (!$this->validName($name)) {
                continue;
            }
            $path = $this->directory . '/' . $name;
            if (!is_file($path) || is_link($path)) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'size' => filesize($path) ?: 0,
                'modified' => filemtime($path) ?: 0,
            ];
        }

        usort($files, static fn(array $left, array $right): int => $right['modified'] <=> $left['modified']);

        return $files;
    }

    /** @return array{entries: list<array{datetime: string, date: string, level: string, message: string, channel: string, context: array<mixed>, extra: array<mixed>}>, total: int, malformed: int, truncated: bool} */
    public function read(?string $filename = null, ?string $level = null, string $search = ''): array
    {
        $available = $this->files();
        if ($available === []) {
            return ['entries' => [], 'total' => 0, 'malformed' => 0, 'truncated' => false];
        }

        $selected = $filename ?? $available[0]['name'];
        if (!$this->validName($selected) || !\in_array($selected, array_column($available, 'name'), true)) {
            throw new \InvalidArgumentException('Wybrany plik logu nie istnieje.');
        }

        $path = $this->directory . '/' . $selected;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Nie udało się odczytać pliku logu.');
        }

        $entries = [];
        $total = 0;
        $malformed = 0;
        $needle = mb_strtolower(trim($search));
        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                try {
                    $entry = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    ++$malformed;
                    continue;
                }
                if (!\is_array($entry)) {
                    ++$malformed;
                    continue;
                }
                $normalized = $this->normalize($entry);
                if ($level !== null && $level !== '' && $normalized['level'] !== strtoupper($level)) {
                    continue;
                }
                if ($needle !== '' && !str_contains(mb_strtolower($line), $needle)) {
                    continue;
                }
                ++$total;
                $entries[] = $normalized;
                if (\count($entries) > self::MAX_ENTRIES) {
                    array_shift($entries);
                }
            }
        } finally {
            fclose($handle);
        }

        usort($entries, static fn(array $left, array $right): int => strcmp($right['datetime'], $left['datetime']));

        return ['entries' => $entries, 'total' => $total, 'malformed' => $malformed, 'truncated' => $total > self::MAX_ENTRIES];
    }

    /**
     * @param array<mixed> $entry
     * @return array{datetime: string, date: string, level: string, message: string, channel: string, context: array<mixed>, extra: array<mixed>}
     */
    private function normalize(array $entry): array
    {
        $datetime = \is_string($entry['datetime'] ?? null) ? $entry['datetime'] : '';
        $formatted = $datetime;
        try {
            $formatted = (new DateTimeImmutable($datetime))->format('d.m.Y, H:i:s');
        } catch (\Exception) {
        }

        return [
            'datetime' => $datetime,
            'date' => $formatted,
            'level' => strtoupper(\is_string($entry['level_name'] ?? null) ? $entry['level_name'] : 'UNKNOWN'),
            'message' => \is_string($entry['message'] ?? null) ? $entry['message'] : 'Brak opisu zdarzenia',
            'channel' => \is_string($entry['channel'] ?? null) ? $entry['channel'] : '',
            'context' => \is_array($entry['context'] ?? null) ? $entry['context'] : [],
            'extra' => \is_array($entry['extra'] ?? null) ? $entry['extra'] : [],
        ];
    }

    private function validName(string $name): bool
    {
        return preg_match('/^application(?:-[0-9]{4}-[0-9]{2}-[0-9]{2})?\.log$/D', $name) === 1;
    }
}
