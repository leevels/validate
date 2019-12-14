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

use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use Leevel\Di\IContainer;
use Leevel\Flow\FlowControl;
use function Leevel\Support\Arr\normalize;
use Leevel\Support\Arr\normalize;
use function Leevel\Support\Str\camelize;
use Leevel\Support\Str\camelize;
use function Leevel\Support\Str\un_camelize;
use Leevel\Support\Str\un_camelize;

/**
 * Validator 数据验证器.
 */
class Validator implements IValidator
{
    use FlowControl;

    /**
     * IOC 容器.
     *
     * @var null|\Leevel\Di\IContainer
     */
    protected ?IContainer $container = null;

    /**
     * 待验证数据.
     *
     * @var array
     */
    protected array $data = [];

    /**
     * 验证规则.
     *
     * @var array
     */
    protected array $rules = [];

    /**
     * 默认验证提示信息.
     *
     * @var array
     */
    protected static array $defaultMessages = [];

    /**
     * 验证提示信息.
     *
     * @var array
     */
    protected array $messages = [];

    /**
     * 字段名字.
     *
     * @var array
     */
    protected array $names = [];

    /**
     * 错误规则.
     *
     * @var array
     */
    protected array $failedRules = [];

    /**
     * 错误消息.
     *
     * @var array
     */
    protected array $errorMessages = [];

    /**
     * 需要跳过的验证规则.
     *
     * - 用于扩展属性支持
     *
     * @var array
     */
    protected array $skipRule = [];

    /**
     * 扩展验证器.
     *
     * @var array
     */
    protected array $extends = [];

    /**
     * 验证后续事件.
     *
     * @var array
     */
    protected array $afters = [];

    /**
     * 验证别名.
     *
     * @var array
     */
    protected array $alias = [
        'confirm' => 'equal_to',
        'gt'      => 'greater_than',
        '>'       => 'greater_than',
        'egt'     => 'equal_greater_than',
        '>='      => 'equal_greater_than',
        'lt'      => 'less_than',
        '<'       => 'less_than',
        'elt'     => 'equal_less_than',
        '<='      => 'equal_less_than',
        'eq'      => 'equal',
        '='       => 'equal',
        'neq'     => 'not_equal',
        '!='      => 'not_equal',
    ];

    /**
     * 构造函数.
     */
    public function __construct(array $data = [], array $rules = [], array $names = [], array $messages = [])
    {
        $this->data($data);
        $this->rule($rules);
        $this->name($names);
        $this->message($messages);
    }

    /**
     * call.
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        if (0 === strpos($method, 'validate')) {
            $extend = un_camelize(substr($method, 8));
            if (isset($this->extends[$extend])) {
                return $this->callExtend($extend, $args);
            }
        }

        if (count($args) > 0) {
            $extend = 'validate'.ucfirst($method);
            $param = [''];
            $param[] = array_shift($args);
            $param[] = $args;
            unset($args);

            if (class_exists($fn = __NAMESPACE__.'\\Helper\\'.un_camelize($method))) {
                array_shift($param);

                return $fn(...$param);
            }

            $extend = un_camelize($method);
            if (isset($this->extends[$extend])) {
                return $this->callExtend($extend, $param);
            }
        }

        $e = sprintf('Method %s is not exits.', $method);

        throw new BadMethodCallException($e);
    }

    /**
     * 初始化验证器.
     *
     * @return \Leevel\Validate\IValidator
     */
    public static function make(array $data = [], array $rules = [], array $names = [], array $messages = []): IValidator
    {
        return new static($data, $rules, $names, $messages);
    }

    /**
     * 验证是否成功.
     */
    public function success(): bool
    {
        $skipRule = $this->getSkipRule();
        $this->failedRules = $this->errorMessages = [];

        foreach ($this->rules as $field => $rules) {
            foreach ($rules as $rule) {
                if (in_array($rule, $skipRule, true)) {
                    continue;
                }

                if (false === $this->doValidateItem($field, $rule)) {
                    // 验证失败跳过剩余验证规则
                    if ($this->shouldSkipOther($field)) {
                        break 2;
                    }

                    // 验证失败跳过自身剩余验证规则
                    if ($this->shouldSkipSelf($field)) {
                        break;
                    }
                }
            }
        }

        foreach ($this->afters as $after) {
            $after();
        }

        return 0 === count($this->errorMessages);
    }

    /**
     * 验证是否失败.
     */
    public function fail(): bool
    {
        return !$this->success();
    }

    /**
     * 返回所有错误消息.
     */
    public function error(): array
    {
        return $this->errorMessages;
    }

    /**
     * 返回验证数据.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 设置验证数据.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function data(array $data): IValidator
    {
        if ($this->checkFlowControl()) {
            return $this;
        }

        $this->data = $data;

        return $this;
    }

    /**
     * 添加验证数据.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function addData(array $data): IValidator
    {
        if ($this->checkFlowControl()) {
            return $this;
        }

        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * 返回验证规则.
     */
    public function getRule(): array
    {
        return $this->rules;
    }

    /**
     * 设置验证规则.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function rule(array $rules, ?Closure $callbacks = null): IValidator
    {
        if ($this->checkFlowControl()) {
            return $this;
        }

        if (null === $callbacks || $this->isCallbackValid($callbacks)) {
            $this->rules = $this->arrayRule($rules);
        }

        return $this;
    }

    /**
     * 添加验证规则.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function addRule(array $rules, ?Closure $callbacks = null): IValidator
    {
        if ($this->checkFlowControl()) {
            return $this;
        }

        if (null === $callbacks || $this->isCallbackValid($callbacks)) {
            $this->rules = array_merge($this->rules, $this->arrayRule($rules));
        }

        return $this;
    }

    /**
     * 返回验证消息.
     */
    public function getMessage(): array
    {
        return $this->messages;
    }

    /**
     * 设置验证消息.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function message(array $messages): IValidator
    {
        if ($this->checkFlowControl()) {
            return $this;
        }

        $this->messages = $this->arrayMessage($messages);

        return $this;
    }

    /**
     * 添加验证消息.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function addMessage(array $messages): IValidator
    {
        if ($this->checkFlowControl()) {
            return $this;
        }

        $this->messages = array_merge(
            $this->messages,
            $this->arrayMessage($messages)
        );

        return $this;
    }

    /**
     * 返回名字.
     */
    public function getName(): array
    {
        return $this->names;
    }

    /**
     * 设置名字.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function name(array $names): IValidator
    {
        if ($this->checkFlowControl()) {
            return $this;
        }

        $this->names = $names;

        return $this;
    }

    /**
     * 添加名字.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function addName(array $names): IValidator
    {
        if ($this->checkFlowControl()) {
            return $this;
        }

        $this->names = array_merge($this->names, $names);

        return $this;
    }

    /**
     * 设置别名.
     *
     * @throws \InvalidArgumentException
     *
     * @return \Leevel\Validate\IValidator
     */
    public function alias(string $name, string $alias): IValidator
    {
        if (in_array($name, $this->getSkipRule(), true)) {
            $e = sprintf('You cannot set alias for skip rule %s.', $name);

            throw new InvalidArgumentException($e);
        }

        $this->alias[$alias] = $name;

        return $this;
    }

    /**
     * 批量设置别名.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function aliasMany(array $alias): IValidator
    {
        foreach ($alias as $alias => $value) {
            $this->alias($alias, $value);
        }

        return $this;
    }

    /**
     * 设置验证后事件.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function after(Closure $callbacks): IValidator
    {
        $this->afters[] = function () use ($callbacks) {
            return $callbacks($this);
        };

        return $this;
    }

    /**
     * 注册自定义扩展.
     *
     * @param \Closure|string $extends
     *
     * @return \Leevel\Validate\IValidator
     */
    public function extend(string $rule, $extends): IValidator
    {
        $this->extends[strtolower($rule)] = $extends;

        return $this;
    }

    /**
     * 设置 IOC 容器.
     *
     * @return \Leevel\Validate\IValidator
     */
    public function setContainer(IContainer $container): IValidator
    {
        $this->container = $container;

        return $this;
    }

    /**
     * 初始化默认的消息.
     */
    public static function initMessages(array $messages): void
    {
        static::$defaultMessages = $messages;
    }

    /**
     * 尝试读取格式化条件.
     *
     * @param array|string $rules
     */
    public function getParseRule(string $field, $rules): array
    {
        $rules = (array) $rules;
        foreach ($this->rules[$field] as $rule) {
            list($rule, $param) = $this->parseRule($rule);
            if (in_array($rule, $rules, true)) {
                return [$rule, $param];
            }
        }

        return [];
    }

    /**
     * 获取字段的值.
     *
     * @return mixed
     */
    public function getFieldValue(string $rule)
    {
        if (false === strpos($rule, '.')) {
            if (isset($this->data[$rule])) {
                return $this->data[$rule];
            }
        } else {
            $parts = explode('.', $rule);
            $data = $this->data;
            foreach ($parts as $part) {
                if (!isset($data[$part])) {
                    return;
                }
                $data = $data[$part];
            }

            return $data;
        }
    }

    /**
     * 转换消息为数组.
     */
    protected function arrayMessage(array $messages): array
    {
        $result = [];
        foreach ($messages as $field => $message) {
            // 字段消息或者通配符
            // ['name' => ['required' => '{field} required']]
            // ['na*' => 'foo bar']
            if (is_array($message) || false !== strpos($field, '*')) {
                if (false === strpos($field, '*')) {
                    $result = array_merge($result,
                        $this->arrayMessageItem($field, $message)
                    );
                } else {
                    $result = array_merge($result,
                        $this->wildcardMessageItem($field, $message)
                    );
                }
            }

            // 直接消息
            // ['required' => '{field} required']
            // ['name.required' => '{field} required']
            else {
                $result[$field] = $message;
            }
        }

        return $result;
    }

    /**
     * 分析通配符消息.
     *
     * @param mixed $message
     */
    protected function wildcardMessageItem(string $field, $message): array
    {
        $field = $this->prepareRegexForWildcard($field);
        $messages = [];
        foreach ($this->parseDataKey() as $key) {
            if (preg_match($field, $key, $matche)) {
                $messages = array_merge($messages,
                    $this->arrayMessageItem($key, $message)
                );
            }
        }

        return $messages;
    }

    /**
     * 通配符正则.
     */
    protected function prepareRegexForWildcard(string $regex, bool $strict = true): string
    {
        $regex = preg_quote($regex, '/');
        $regex = '/^'.str_replace('\*', '(\S+)', $regex).($strict ? '$' : '').'/';

        return $regex;
    }

    /**
     * 转换单条消息为数组.
     *
     * @param array|string $message
     */
    protected function arrayMessageItem(string $field, $message): array
    {
        $result = [];
        if (is_array($message)) {
            foreach ($message as $key => $message) {
                $result[$field.'.'.$key] = $message;
            }
        } else {
            foreach ($this->getFieldRuleWithoutSkip($field) as $rule) {
                $result[$field.'.'.$rule] = $message;
            }
        }

        return $result;
    }

    /**
     * 获取单个字段验证规则，排除掉绕过的规则.
     */
    protected function getFieldRuleWithoutSkip(string $field): array
    {
        return array_diff($this->getFieldRule($field), $this->getSkipRule());
    }

    /**
     * 获取需要跳过的验证规则.
     */
    protected function getSkipRule(): array
    {
        return array_merge([
            static::OPTIONAL,
            static::MUST,
            static::SKIP_SELF,
            static::SKIP_OTHER,
        ], $this->skipRule);
    }

    /**
     * 获取单个字段验证规则.
     */
    protected function getFieldRule(string $field): array
    {
        if (isset($this->rules[$field])) {
            return $this->rules[$field];
        }

        return [];
    }

    /**
     * 分析验证规则和参数.
     */
    protected function parseRule(string $rule): array
    {
        $rule = trim($rule);
        list($rule, $params) = array_pad(explode(':', $rule, 2), 2, []);
        if (is_string($params)) {
            $params = $this->parseParams($rule, $params);
        }

        $params = array_map(function (string $item) {
            return ctype_digit($item) ? (int) $item :
                (is_numeric($item) ? (float) $item : $item);
        }, $params);

        if (isset($this->alias[$rule])) {
            $rule = $this->alias[$rule];
        }

        return [$rule, $params];
    }

    /**
     * 转换规则为数组.
     */
    protected function arrayRule(array $rules): array
    {
        $result = [];
        foreach ($rules as $field => $rules) {
            if (false === strpos($field, '*')) {
                $result[$field] = $this->arrayRuleItem($rules);
            } else {
                $result = array_merge($result, $this->wildcardRuleItem($field, $rules));
            }
        }

        return $result;
    }

    /**
     * 转换单条规则为数组.
     *
     * @param mixed $rules
     */
    protected function arrayRuleItem($rules): array
    {
        return normalize($rules, '|');
    }

    /**
     * 分析通配符规则.
     *
     * @param mixed $rules
     */
    protected function wildcardRuleItem(string $field, $rules): array
    {
        $field = $this->prepareRegexForWildcard($field);
        $result = [];
        foreach ($this->parseDataKey() as $key) {
            if (preg_match($field, $key, $matche)) {
                $result[$key] = $this->arrayRuleItem($rules);
            }
        }

        return $result;
    }

    /**
     * 返回分析后的数据键.
     */
    protected function parseDataKey(): array
    {
        return $this->parseDataKeyRecursion($this->getData());
    }

    /**
     * 递归分析.
     */
    protected function parseDataKeyRecursion(array $data, string $parentKey = ''): array
    {
        $dataKeys = [];
        foreach ($data as $key => $d) {
            $first = ($parentKey ? $parentKey.'.' : '').$key;
            if (is_array($d)) {
                $dataKeys = array_merge($dataKeys, $this->parseDataKeyRecursion($d, $first));
            } else {
                $dataKeys[] = $first;
            }
        }

        return $dataKeys;
    }

    /**
     * 是否存在单个字段验证规则.
     */
    protected function hasFieldRuleWithParam(string $field, string $rule): bool
    {
        if (!isset($this->rules[$field])) {
            return false;
        }

        return in_array($rule, $this->rules[$field], true);
    }

    /**
     * 解析变量.
     */
    protected function parseParams(string $rule, string $param): array
    {
        if ('regex' === strtolower($rule)) {
            return [$param];
        }

        return explode(',', $param);
    }

    /**
     * 验证字段规则.
     *
     * @return bool|void
     */
    protected function doValidateItem(string $field, string $rule)
    {
        list($rule, $param) = $this->parseRule($rule);
        if ('' === $rule) {
            return;
        }

        $fieldValue = $this->getFieldValue($field);

        // 可选字段无需验证
        if (null === $fieldValue &&
            $this->hasFieldRuleWithParam($field, static::OPTIONAL)) {
            return;
        }

        if (class_exists($fn = __NAMESPACE__.'\\Helper\\'.$rule)) {
            if (!$fn($fieldValue, $param, $this, $field)) {
                $this->addFailure($field, $rule, $param);

                return false;
            }
        } elseif (class_exists($className = __NAMESPACE__.'\\'.($camelizeRule = ucwords(camelize($rule))).'Rule')) {
            if ($this->container) {
                $validateRule = $this->container->make($className);
            } else {
                $validateRule = new $className();
            }

            if (false === $validateRule->validate($fieldValue, $param, $this, $field)) {
                $this->addFailure($field, $rule, $param);

                return false;
            }
        } elseif (!$this->{'validate'.$camelizeRule}($fieldValue, $param, $this, $field)) {
            $this->addFailure($field, $rule, $param);

            return false;
        }

        return true;
    }

    /**
     * 是否需要终止其他验证.
     */
    protected function shouldSkipOther(string $field): bool
    {
        return $this->hasFieldRuleWithParam($field, static::SKIP_OTHER);
    }

    /**
     * 是否需要终止自己其他验证.
     */
    protected function shouldSkipSelf(string $field): bool
    {
        return $this->hasFieldRuleWithParam($field, static::SKIP_SELF);
    }

    /**
     * 添加错误规则和验证错误消息.
     */
    protected function addFailure(string $field, string $rule, array $param): void
    {
        $this->addError($field, $rule, $param);
        $this->failedRules[$field][$rule] = $param;
    }

    /**
     * 添加验证错误消息.
     */
    protected function addError(string $field, string $rule, array $param): void
    {
        $message = $this->getFieldRuleMessage($field, $rule);
        $replace = ['field' => $this->parseFieldName($field)];

        if (!$this->isImplodeRuleParam($rule)) {
            foreach ($param as $key => $param) {
                $replace['rule'.($key ?: '')] = $param;
            }
        } else {
            $replace['rule'] = implode(',', $param);
        }

        $message = preg_replace_callback('/{(.+?)}/', function ($matche) use ($replace) {
            return $replace[$matche[1]] ?? $matche[0];
        }, $message);

        $this->errorMessages[$field][] = $message;
    }

    /**
     * 获取验证消息.
     */
    protected function getFieldRuleMessage(string $field, string $rule): string
    {
        return $this->messages[$field.'.'.$rule] ??
            ($this->messages[$rule] ?? (static::$defaultMessages[$rule] ?? ''));
    }

    /**
     * 获取字段名字.
     */
    protected function parseFieldName(string $field): string
    {
        return $this->names[$field] ?? $field;
    }

    /**
     * 返回需要合并的规则参数.
     */
    protected function isImplodeRuleParam(string $rule): bool
    {
        return in_array($rule, ['in', 'not_in', 'allow_ip', 'deny_ip'], true);
    }

    /**
     * 调用自定义验证器类.
     *
     * @throws \InvalidArgumentException
     */
    protected function callClassExtend(string $extend, array $param): bool
    {
        if (!$this->container) {
            $e = 'Container was not set.';

            throw new InvalidArgumentException($e);
        }

        if (false === strpos($extend, '@')) {
            $className = $extend;
            $method = 'handle';
        } else {
            list($className, $method) = explode('@', $extend);
        }

        if (!is_object($extend = $this->container->make($className))) {
            $e = sprintf('Extend class %s is not valid.', $className);

            throw new InvalidArgumentException($e);
        }

        $param[] = $this;

        return $extend->{$method}(...$param);
    }

    /**
     * 调用自定义验证器.
     *
     * @throws \InvalidArgumentException
     */
    protected function callExtend(string $rule, array $param): bool
    {
        $extends = $this->extends[$rule];
        if (is_callable($extends)) {
            $param[] = $this;

            return $extends(...$param);
        }

        if (is_string($extends)) {
            return $this->callClassExtend($extends, $param);
        }

        $e = sprintf('Extend in rule %s is not valid.', $rule);

        throw new InvalidArgumentException($e);
    }

    /**
     * 验证条件是否通过.
     */
    protected function isCallbackValid(Closure $callbacks): bool
    {
        return $callbacks($this->getData());
    }
}

// import fn.
class_exists(normalize::class);
class_exists(un_camelize::class);
class_exists(camelize::class);
