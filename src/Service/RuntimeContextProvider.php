<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use Illuminate\Http\Request;
use PTAdmin\Addon\Contracts\RuntimeContextNormalizerInterface;
use PTAdmin\Addon\Contracts\RuntimeContextProviderInterface;
use PTAdmin\Addon\Support\RuntimeContextKeys;

/**
 * 运行时上下文提供器。
 *
 * 该类自身不保存请求态数据，只负责把标准上下文写入当前请求，并提供统一读取入口。
 */
class RuntimeContextProvider implements RuntimeContextProviderInterface
{
    private RuntimeContextNormalizerInterface $normalizer;

    public function __construct(RuntimeContextNormalizerInterface $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function put(array $context): void
    {
        $this->request()->attributes->set(
            RuntimeContextKeys::REQUEST_ATTRIBUTE,
            $this->normalizer->normalize($context)
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function merge(array $context): void
    {
        $this->put(array_replace_recursive($this->current(), $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function replace(array $context): void
    {
        $this->put($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $context = $this->request()->attributes->get(RuntimeContextKeys::REQUEST_ATTRIBUTE);

        return \is_array($context) ? $this->normalizer->normalize($context) : $this->normalizer->empty();
    }

    /**
     * @return array<string, mixed>
     */
    public function fromDto(?DirectivesDTO $dto = null): array
    {
        if ($dto instanceof DirectivesDTO) {
            $context = $dto->getAttribute(RuntimeContextKeys::DTO_ATTRIBUTE);
            if (\is_array($context)) {
                return $this->normalizer->normalize($context);
            }
        }

        return $this->current();
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->current(), $key, $default);
    }

    public function has(string $key): bool
    {
        return null !== data_get($this->current(), $key);
    }

    public function clear(): void
    {
        $this->request()->attributes->remove(RuntimeContextKeys::REQUEST_ATTRIBUTE);
    }

    private function request(): Request
    {
        return request();
    }
}
