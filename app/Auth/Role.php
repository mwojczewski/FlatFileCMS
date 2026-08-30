<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

enum Role: string
{
    case Admin = 'ROLE_ADMIN';
    case Superadmin = 'ROLE_SUPERADMIN';
}
