<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2024 重庆胖头网络技术有限公司，并保留所有权利。
 *  网站地址: https://www.pangtou.com
 *  ----------------------------------------------------------------------------
 *  尊敬的用户，
 *     感谢您对我们产品的关注与支持。我们希望提醒您，在商业用途中使用我们的产品时，请务必前往官方渠道购买正版授权。
 *  购买正版授权不仅有助于支持我们不断提供更好的产品和服务，更能够确保您在使用过程中不会引起不必要的法律纠纷。
 *  正版授权是保障您合法使用产品的最佳方式，也有助于维护您的权益和公司的声誉。我们一直致力于为客户提供高质量的解决方案，并通过正版授权机制确保产品的可靠性和安全性。
 *  如果您有任何疑问或需要帮助，我们的客户服务团队将随时为您提供支持。感谢您的理解与合作。
 *  诚挚问候，
 *  【重庆胖头网络技术有限公司】
 *  ============================================================================
 *  Author:    Zane
 *  Homepage:  https://www.pangtou.com
 *  Email:     vip@pangtou.com
 */

namespace PTAdmin\Addon\Service;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PTAdmin\Addon\Exception\AddonException;

/**
 * 插件指令.
 */
class AddonDirectives
{
    /** @var string 指令默认调用方法 */
    public const DEFAULT_METHOD = 'handle';

    /** @var string 插件名称 */
    private $addon_name;

    /** @var mixed 插件原始参数信息 */
    private $old_params;

    private $target;

    public function __construct($addon_name, $params)
    {
        $this->addon_name = $addon_name;
        $this->old_params = $params;
        $this->parserTargets();
    }

    /**
     * @param $method
     * @param DirectivesDTO $dto
     *
     * @return array|mixed
     */
    public function execute($method, DirectivesDTO $dto)
    {
        $target = $this->getMethodTarget($method);
        $instance = app($target['class']);
        if (!method_exists($instance, $target['method'])) {
            throw new AddonException("插件【{$this->addon_name}】未定义方法【{$target['method']}】");
        }
        $param = $this->getClassMethodParams($instance, $target['method']);

        $results = $instance->{$target['method']}(...($this->mergeParamToMethodParams($param, $dto)));
        if ($this->isLoop($method)) {
            if (\is_array($results)) {
                return $results;
            }
            if ($results instanceof Collection) {
                return $results->toArray();
            }

            return collect($results)->toArray();
        }

        return $results;
    }

    public function getCacheKey($method, $transfer): string
    {
        return "PTAdmin:{$this->addon_name}_{$method}_{$transfer}";
    }

    /**
     * 获取插件是否允许缓存结果.不显示定义为false则默认允许缓存.
     *
     * @param $method
     *
     * @return false
     */
    public function isAllowCaching($method): bool
    {
        $target = $this->getMethodTarget($method);
        if (!blank($target) && isset($target['allow_caching'])) {
            return false !== $target['allow_caching'];
        }

        return true;
    }

    /**
     * 验证指令是否为循环.
     *
     * @param $method
     *
     * @return bool
     */
    public function isLoop($method): bool
    {
        $method = $method ?? self::DEFAULT_METHOD;
        $target = $this->getMethodTarget($method);
        if (isset($target['return_type'])) {
            return true !== $target['return_type'];
        }

        return true;
    }

    /**
     * 获取实例的方法参数列表.
     *
     * @param $class
     * @param $method
     *
     * @return array
     */
    private function getClassMethodParams($class, $method): array
    {
        try {
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\ReflectionException $e) {
            throw new AddonException("插件【{$this->addon_name}】未定义方法【{$method}】");
        }
        $params = $reflection->getParameters();
        $result = [];
        foreach ($params as $param) {
            $result[$param->getPosition()] = [
                'name' => $param->getName(),
                'type' => $param->isArray() ? 'array' : null === $param->getType() ? 'mixed' : $param->getType()->getName(),
                'optional' => $param->isOptional(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'position' => $param->getPosition(),
            ];
        }

        return $result;
    }

    /**
     * 合并请求参数到方法中.
     *
     * @param $methodParams
     * @param $dto
     *
     * @return array
     */
    private function mergeParamToMethodParams($methodParams, $dto): array
    {
        $result = [];
        foreach ($methodParams as $param) {
            switch ($param['type']) {
                case 'int':
                    $result[$param['position']] = (int) $dto->getAttribute($param['name'], $param['default']);

                    break;

                case 'bool':
                    $result[$param['position']] = (bool) $dto->getAttribute($param['name'], $param['default']);

                    break;

                case 'float':
                    $result[$param['position']] = (float) $dto->getAttribute($param['name'], $param['default']);

                    break;

                case 'string':
                    $result[$param['position']] = (string) $dto->getAttribute($param['name'], $param['default']);

                    break;

                case 'array':
                    $result[$param['position']] = (array) $dto->getAttribute($param['name'], $param['default']);

                    break;

                case 'object':
                    $result[$param['position']] = (object) $dto->getAttribute($param['name'], $param['default']);

                    break;

                case 'callable':
                    $val = $dto->getAttribute($param['name'], $param['default']);
                    if (!\is_callable($val)) {
                        throw new AddonException("插件【{$this->addon_name}】参数【{$param['name']}】必须为函数");
                    }
                    $result[$param['position']] = $val;

                    break;

                default:
                    if (Request::class === $param['type']) {
                        $result[$param['position']] = $this->mergeRequest($dto);

                        break;
                    }
                    if (DirectivesDTO::class === $param['type']) {
                        $result[$param['position']] = $dto;

                        break;
                    }
                    $result[$param['position']] = 'mixed' === $param['type'] ? $dto->getAttribute($param['name'], $param['default']) : app($param['type']);

                    break;
            }
        }

        return $result;
    }

    private function mergeRequest($transfer)
    {
        $params = $transfer->all();
        $request = request();
        $request->offsetSet('is_directives', 1);
        $request->offsetSet('transfer', $params);

        return $request;
    }

    /**
     * 根据插件安装信息解析出暴露给前端模版调用的方法.
     */
    private function parserTargets(): void
    {
        if (blank($this->old_params)) {
            return;
        }
        // 解析参数为字符串的情况,默认情况的处理方式
        if (\is_string($this->old_params)) {
            $this->target[self::DEFAULT_METHOD] = $this->parserTarget($this->old_params);

            return;
        }
        // 当参数为对象的情况
        if ($this->old_params instanceof self) {
            $this->target = $this->old_params->target;

            return;
        }
        // 当参数为数组时
        foreach ($this->old_params as $name => $param) {
            if (is_numeric($name) && isset($param['method']) && !blank($param['method'])) {
                $this->target[$param['method']] = $this->parserTarget($param, $param['method']);

                continue;
            }
            $this->target[$name] = $this->parserTarget($param, $name);
        }
    }

    /**
     * 解析目标参数信息.
     *
     * @param $params
     * @param int|string $defMethod
     *
     * @return array|string[]
     */
    private function parserTarget($params, $defMethod = 0): array
    {
        if (\is_string($params)) {
            $method = is_numeric($defMethod) ? self::DEFAULT_METHOD : $defMethod;

            return ['class' => $params, 'method' => $method];
        }
        if (\is_array($params)) {
            $keys = ['class', 'method', 'return_type', 'allow_caching'];
            $result = [];
            foreach ($params as $key => $param) {
                if (\is_int($key)) {
                    if ($key >= \count($keys)) {
                        continue;
                    }
                    $result[$keys[$key]] = $param;

                    continue;
                }
                $result[$key] = $param;
            }

            return $result;
        }

        return [];
    }

    /**
     * 获取方法执行的目标.
     *
     * @param $method
     *
     * @return null|mixed
     */
    private function getMethodTarget($method)
    {
        $target = $this->target[$method] ?? null;
        if (!$target) {
            // 当插件只有一个导出方法时可以使用插件名称作为标志后在自定义导出方法
            if (self::DEFAULT_METHOD === $method) {
                $target = $this->target[$this->addon_name] ?? null;
                if ($target) {
                    return $target;
                }
            }

            throw new AddonException("插件【{$this->addon_name}】未定义方法【{$method}】");
        }

        return $target;
    }
}
