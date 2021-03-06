<?php

declare(strict_types=1);

namespace Leevel\Validate\Helper;

use InvalidArgumentException;

/**
 * 是否不处于某个范围.
 * @throws \InvalidArgumentException
 */
function not_in(mixed $value, array $param): bool
{
    if (!array_key_exists(0, $param)) {
        $e = 'Missing the first element of param.';

        throw new InvalidArgumentException($e);
    }

    return !in_array($value, $param, true);
}

class not_in
{
}
