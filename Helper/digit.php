<?php

declare(strict_types=1);

namespace Leevel\Validate\Helper;

/**
 * 检测字符串中的字符是否都是数字，负数和小数会检测不通过.
 */
function digit(mixed $value): bool
{
    return ctype_digit($value);
}

class digit
{
}
