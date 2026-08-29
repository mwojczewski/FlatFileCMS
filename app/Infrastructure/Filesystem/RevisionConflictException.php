<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Filesystem;

final class RevisionConflictException extends FilesystemException
{
    public function __construct(
        private readonly FileRevision $expected,
        private readonly FileRevision $actual,
    ) {
        parent::__construct('The file changed after it was read. Reload it before saving again.');
    }

    public function expected(): FileRevision
    {
        return $this->expected;
    }

    public function actual(): FileRevision
    {
        return $this->actual;
    }
}
