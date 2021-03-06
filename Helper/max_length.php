<?php

declare(strict_types=1);

namespace Leevel\Validate\Helper;

use InvalidArgumentException;

/**
 * 验证数据最大长度.
 * @throws \InvalidArgumentException
 */
function max_length(mixed $value, array $param): bool
{
    if (!is_scalar($value)) {
        return false;
    }

    $value = (string) $value;

    if (!array_key_exists(0, $param)) {
        $e = 'Missing the first element of param.';

        throw new InvalidArgumentException($e);
    }

    return mb_strlen($value, 'utf-8') <= (int) $param[0];
}

class max_length
{
}
