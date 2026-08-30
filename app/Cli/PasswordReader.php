<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use RuntimeException;

final readonly class PasswordReader
{
    public function read(): string
    {
        $environmentPassword = getenv('CMS_PASSWORD');
        if (is_string($environmentPassword) && $environmentPassword !== '') {
            return $environmentPassword;
        }

        fwrite(STDOUT, 'Password: ');
        $definedFunctions = get_defined_functions();
        $hidden = DIRECTORY_SEPARATOR === '/'
            && in_array('shell_exec', $definedFunctions['internal'], true);
        if ($hidden) {
            shell_exec('stty -echo');
        }
        try {
            $password = fgets(STDIN);
        } finally {
            if ($hidden) {
                shell_exec('stty echo');
                fwrite(STDOUT, "\n");
            }
        }
        if (!is_string($password)) {
            throw new RuntimeException('Unable to read password.');
        }

        return rtrim($password, "\r\n");
    }
}
