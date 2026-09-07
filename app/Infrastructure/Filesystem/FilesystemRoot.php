<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Filesystem;

enum FilesystemRoot: string
{
    case Pages = 'pages';
    case Errors = 'errors';
    case Config = 'config';
    case Storage = 'storage';
}
