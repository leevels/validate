<?php

declare(strict_types=1);

namespace Leevel\Validate\Helper;

use function checkdnsrr as base_checkdnsrr;

/**
 * 验证是否为有效的域名.
 */
function checkdnsrr(mixed $value): bool
{
    if (!is_string($value)) {
        return false;
    }

    return base_checkdnsrr($value);
}

class checkdnsrr
{
}
