<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use Closure;
use Throwable;

final class OutputBuffer
{
    /** @param Closure(): void $render */
    public function capture(Closure $render): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            $render();
            $contents = ob_get_clean();
            if (!is_string($contents)) {
                throw new RenderingException('Unable to collect rendered output.');
            }

            return $contents;
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            if ($exception instanceof RenderingException) {
                throw $exception;
            }

            throw new RenderingException('Template rendering failed.', previous: $exception);
        }
    }
}
