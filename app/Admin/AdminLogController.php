<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Logging\LogReader;

final readonly class AdminLogController
{
    private const array LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public function __construct(
        private Authenticator $authenticator,
        private LogReader $logs,
        private AdminView $views,
        private AdminLayout $layout,
    ) {}

    public function index(Request $request): Response
    {
        $this->requireUser();
        $file = $this->string($request->query()['file'] ?? null);
        $level = strtoupper($this->string($request->query()['level'] ?? null) ?? '');
        $search = mb_substr($this->string($request->query()['q'] ?? null) ?? '', 0, 200);
        if ($level !== '' && !\in_array($level, self::LEVELS, true)) {
            throw new HttpException(400, 'LOG_LEVEL_INVALID', 'Nieprawidłowy poziom logu.');
        }

        $files = $this->logs->files();
        $selected = $file ?? ($files[0]['name'] ?? null);
        try {
            $result = $this->logs->read($selected, $level, $search);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpException(404, 'LOG_FILE_NOT_FOUND', $exception->getMessage(), previous: $exception);
        }

        $counts = array_fill_keys(self::LEVELS, 0);
        foreach ($result['entries'] as $entry) {
            $entryLevel = $entry['level'];
            $counts[$entryLevel] = ($counts[$entryLevel] ?? 0) + 1;
        }

        $content = $this->views->render('logs/index', [
            'files' => $files,
            'selected' => $selected,
            'levels' => self::LEVELS,
            'selectedLevel' => $level,
            'search' => $search,
            'result' => $result,
            'counts' => $counts,
        ]);

        return $this->layout->render('Logi aplikacji', $content, active: 'logs');
    }

    private function requireUser(): void
    {
        try {
            $this->authenticator->requireUser();
        } catch (AuthenticationException $exception) {
            throw new HttpException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required.', previous: $exception);
        }
    }

    private function string(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
