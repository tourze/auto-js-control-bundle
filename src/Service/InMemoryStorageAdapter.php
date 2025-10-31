<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * 内存存储适配器.
 *
 * 用于测试环境，无需依赖外部 Redis 服务
 */
#[Exclude]
final class InMemoryStorageAdapter implements StorageAdapterInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $storage = [];

    /**
     * @var array<string, int>
     */
    private array $expireTime = [];

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->storage[$key] = $value;
        if (null !== $ttl) {
            $this->expireTime[$key] = time() + $ttl;
        } else {
            unset($this->expireTime[$key]);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->checkExpired($key);

        return $this->storage[$key] ?? $default;
    }

    public function delete(string $key): bool
    {
        $exists = isset($this->storage[$key]);
        unset($this->storage[$key], $this->expireTime[$key]);

        return $exists;
    }

    public function hSet(string $key, string $field, mixed $value): void
    {
        $this->checkExpired($key);

        if (!isset($this->storage[$key]) || !is_array($this->storage[$key])) {
            $this->storage[$key] = [];
        }

        $this->storage[$key][$field] = $value;
    }

    public function hGet(string $key, string $field): mixed
    {
        $this->checkExpired($key);

        return $this->storage[$key][$field] ?? false;
    }

    public function hMSet(string $key, array $data): void
    {
        $this->checkExpired($key);

        if (!isset($this->storage[$key]) || !is_array($this->storage[$key])) {
            $this->storage[$key] = [];
        }

        $this->storage[$key] = array_merge($this->storage[$key], $data);
    }

    public function hGetAll(string $key): array
    {
        $this->checkExpired($key);

        $value = $this->storage[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    public function lPush(string $key, mixed $value): int
    {
        $this->checkExpired($key);

        if (!isset($this->storage[$key]) || !is_array($this->storage[$key])) {
            $this->storage[$key] = [];
        }

        array_unshift($this->storage[$key], $value);

        return count($this->storage[$key]);
    }

    public function rPush(string $key, mixed $value): int
    {
        $this->checkExpired($key);

        if (!isset($this->storage[$key]) || !is_array($this->storage[$key])) {
            $this->storage[$key] = [];
        }

        $this->storage[$key][] = $value;

        return count($this->storage[$key]);
    }

    public function rPop(string $key): mixed
    {
        $this->checkExpired($key);

        if (!isset($this->storage[$key]) || !is_array($this->storage[$key]) || [] === $this->storage[$key]) {
            return false;
        }

        return array_pop($this->storage[$key]);
    }

    public function lLen(string $key): int
    {
        $this->checkExpired($key);

        if (!isset($this->storage[$key]) || !is_array($this->storage[$key])) {
            return 0;
        }

        return count($this->storage[$key]);
    }

    public function lRange(string $key, int $start, int $end): array
    {
        $this->checkExpired($key);

        if (!isset($this->storage[$key]) || !is_array($this->storage[$key])) {
            return [];
        }

        $list = $this->storage[$key];
        $length = count($list);

        // 处理负数索引（Redis 风格）
        if ($start < 0) {
            $start = max(0, $length + $start);
        }
        if ($end < 0) {
            $end = $length + $end;
        }

        // 确保索引在有效范围内
        $start = max(0, min($start, $length - 1));
        $end = max(0, min($end, $length - 1));

        if ($start > $end) {
            return [];
        }

        return array_slice($list, $start, $end - $start + 1);
    }

    public function publish(string $channel, string $message): int
    {
        // 内存实现不支持发布订阅，返回 0 表示没有订阅者
        return 0;
    }

    public function subscribe(array $channels, callable $callback): void
    {
        // 内存实现不支持发布订阅，直接返回
    }

    public function expire(string $key, int $seconds): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        $this->expireTime[$key] = time() + $seconds;

        return true;
    }

    public function exists(string $key): bool
    {
        $this->checkExpired($key);

        return isset($this->storage[$key]);
    }

    /**
     * 获取键的剩余过期时间（秒）.
     */
    public function ttl(string $key): int
    {
        $this->checkExpired($key);

        if (!isset($this->storage[$key])) {
            return -2; // 键不存在
        }

        if (!isset($this->expireTime[$key])) {
            return -1; // 键存在但没有设置过期时间
        }

        return max(0, $this->expireTime[$key] - time());
    }

    /**
     * 清空所有数据（用于测试环境重置）.
     */
    public function flushAll(): void
    {
        $this->storage = [];
        $this->expireTime = [];
    }

    /**
     * 检查键是否已过期并自动删除.
     */
    private function checkExpired(string $key): void
    {
        if (isset($this->expireTime[$key]) && $this->expireTime[$key] <= time()) {
            unset($this->storage[$key], $this->expireTime[$key]);
        }
    }
}
