<?php

namespace App\Support;

use InvalidArgumentException;

class SqlIdentifier
{
    public static function quote(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("Unsafe SQL identifier: {$identifier}");
        }

        return '[' . $identifier . ']';
    }
}
