<?php

declare(strict_types=1);

/*
 * This file is part of the ************************ package.
 * _____________                           _______________
 *  ______/     \__  _____  ____  ______  / /_  _________
 *   ____/ __   / / / / _ \/ __`\/ / __ \/ __ \/ __ \___
 *    __/ / /  / /_/ /  __/ /  \  / /_/ / / / / /_/ /__
 *      \_\ \_/\____/\___/_/   / / .___/_/ /_/ .___/
 *         \_\                /_/_/         /_/
 *
 * The PHP Framework For Code Poem As Free As Wind. <Query Yet Simple>
 * (c) 2010-2020 http://queryphp.com All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Leevel\Validate;

use DateTime;
use Exception;
use InvalidArgumentException;

/**
 * 日期验证.
 *
 * @author Xiangmin Liu <635750556@qq.com>
 *
 * @since 2018.11.26
 *
 * @version 1.0
 */
trait Date
{
    /**
     * 数据验证器.
     *
     * @var \Leevel\Validate\IValidator
     */
    protected IValidator $validator;

    /**
     * 校验日期.
     *
     * @param mixed                       $value
     * @param array                       $param
     * @param \Leevel\Validate\IValidator $validator
     * @param string                      $field
     * @param bool                        $before
     *
     * @throws \InvalidArgumentException
     *
     * @return bool
     */
    public function validateDate($value, array $param, IValidator $validator, string $field, bool $before = false): bool
    {
        if (!is_string($value)) {
            return false;
        }

        if (!array_key_exists(0, $param)) {
            $e = 'Missing the first element of param.';

            throw new InvalidArgumentException($e);
        }

        $this->validator = $validator;

        if ($format = $this->getDateFormat($field)) {
            return $this->doWithFormat($format, $value, $param, $before);
        }

        if (!($time = strtotime($param[0]))) {
            if (null === ($_ = $validator->getFieldValue($param[0]))) {
                return false;
            }

            return $this->compareTime(strtotime($value), strtotime($_), $before);
        }

        return $this->compareTime(strtotime($value), $time, $before);
    }

    /**
     * 比较时间.
     *
     * @param int  $left
     * @param int  $right
     * @param bool $before
     *
     * @return bool
     */
    protected function compareTime(int $left, int $right, bool $before = false): bool
    {
        if (true === $before) {
            return $left < $right;
        }

        return $left > $right;
    }

    /**
     * 获取时间格式化.
     *
     * @param string $field
     *
     * @return string|void
     */
    protected function getDateFormat(string $field)
    {
        if ($result = $this->validator->getParseRule($field, 'date_format')) {
            return $result[1][0];
        }
    }

    /**
     * 验证在给定日期之后.
     *
     * @param string $format
     * @param mixed  $value
     * @param array  $param
     * @param bool   $before
     *
     * @return bool
     */
    protected function doWithFormat(string $format, $value, array $param, bool $before = false): bool
    {
        $param[0] = $this->validator->getFieldValue($param[0]) ?: $param[0];

        if (true === $before) {
            list($param[0], $value) = [$value, $param[0]];
        }

        return $this->doCheckDate($format, $param[0], $value);
    }

    /**
     * 验证日期顺序.
     *
     * @param string $format
     * @param string $first
     * @param string $second
     *
     * @return bool
     */
    protected function doCheckDate(string $format, string $first, string $second): bool
    {
        $before = $this->makeDateTimeFormat($format, $first);
        $after = $this->makeDateTimeFormat($format, $second);

        return $before && $after && $before < $after;
    }

    /**
     * 创建 DateTime 实例.
     *
     * @param string $format
     * @param string $value
     *
     * @return \DateTime|void
     */
    protected function makeDateTimeFormat(string $format, string $value)
    {
        $date = DateTime::createFromFormat($format, $value);
        if ($date) {
            return $date;
        }

        try {
            return new DateTime($value);
        } catch (Exception $e) {
        }
    }
}
