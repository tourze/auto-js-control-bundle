<?php

namespace Tourze\AutoJsControlBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Redis 存储适配器.
 *
 * 保持现有的 Redis 功能，实现 StorageAdapterInterface
 */
#[Exclude]
readonly class RedisStorageAdapter implements StorageAdapterInterface
{
    public function __construct(
        private \Redis $redisConnection,
    ) {
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if (null !== $ttl) {
            $this->redisConnection->setex($key, $ttl, $value);
        } else {
            $this->redisConnection->set($key, $value);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redisConnection->get($key);

        return false !== $value ? $value : $default;
    }

    public function delete(string $key): bool
    {
        return (bool) $this->redisConnection->del($key);
    }

    public function hSet(string $key, string $field, mixed $value): void
    {
        $this->redisConnection->hSet($key, $field, $value);
    }

    public function hGet(string $key, string $field): mixed
    {
        return $this->redisConnection->hGet($key, $field);
    }

    public function hMSet(string $key, array $data): void
    {
        $this->redisConnection->hMset($key, $data);
    }

    public function hGetAll(string $key): array
    {
        return $this->redisConnection->hGetAll($key);
    }

    public function lPush(string $key, mixed $value): int
    {
        return $this->redisConnection->lPush($key, $value);
    }

    public function rPush(string $key, mixed $value): int
    {
        return $this->redisConnection->rPush($key, $value);
    }

    public function rPop(string $key): mixed
    {
        return $this->redisConnection->rPop($key);
    }

    public function lLen(string $key): int
    {
        $result = $this->redisConnection->lLen($key);

        return false !== $result && is_int($result) ? $result : 0;
    }

    public function lRange(string $key, int $start, int $end): array
    {
        return $this->redisConnection->lrange($key, $start, $end);
    }

    public function publish(string $channel, string $message): int
    {
        return $this->redisConnection->publish($channel, $message);
    }

    public function subscribe(array $channels, callable $callback): void
    {
        $this->redisConnection->subscribe($channels, $callback);
    }

    public function expire(string $key, int $seconds): bool
    {
        return (bool) $this->redisConnection->expire($key, $seconds);
    }

    public function exists(string $key): bool
    {
        return (bool) $this->redisConnection->exists($key);
    }
}
