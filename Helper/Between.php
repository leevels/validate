<?php

declare(strict_types=1);

namespace Leevel\Validate\Helper;

class Between
{
    /**
     * 处于 between 范围，不包含等于.
     *
     * @throws \InvalidArgumentException
     */
    public static function handle(mixed $value, array $param): bool
    {
        if (
            !\array_key_exists(0, $param)
            || !\array_key_exists(1, $param)
        ) {
            throw new \InvalidArgumentException('Missing the first or second element of param.');
        }

        return $value > $param[0] && $value < $param[1];
    }
}
