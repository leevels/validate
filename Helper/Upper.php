<?php

declare(strict_types=1);

namespace Leevel\Validate\Helper;

class Upper
{
    /**
     * 验证是否都是大写.
     */
    public static function handle(mixed $value): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        return ctype_upper($value);
    }
}
