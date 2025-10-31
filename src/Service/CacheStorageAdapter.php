<?php

namespace Tourze\AutoJsControlBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;

/**
 * Cache 存储适配器.
 *
 * 使用 Symfony Cache 组件实现存储功能
 */
readonly class CacheStorageAdapter implements StorageAdapterInterface
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private ?\Redis $redisConnection = null, // 用于需要 Redis 特定功能的情况
    ) {
    }

    /**
     * 转换键名以兼容 Symfony Cache.
     */
    private function convertKey(string $key): string
    {
        // Symfony Cache 不允许这些字符: {}()/\@:
        return str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], '_', $key);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $item = $this->cache->getItem($this->convertKey($key));
        $item->set($value);
        if (null !== $ttl) {
            $item->expiresAfter($ttl);
        }
        $this->cache->save($item);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->cache->getItem($this->convertKey($key));

        return $item->isHit() ? $item->get() : $default;
    }

    public function delete(string $key): bool
    {
        return $this->cache->deleteItem($this->convertKey($key));
    }

    public function hSet(string $key, string $field, mixed $value): void
    {
        $item = $this->cache->getItem($this->convertKey($key));
        $data = $this->safelyParseArray($item->isHit() ? $item->get() : []);
        $data[$field] = $value;
        $item->set($data);
        $this->cache->save($item);
    }

    public function hGet(string $key, string $field): mixed
    {
        $item = $this->cache->getItem($this->convertKey($key));
        if (!$item->isHit()) {
            return null;
        }
        $data = $this->safelyParseArray($item->get());

        return $data[$field] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function hMSet(string $key, array $data): void
    {
        $item = $this->cache->getItem($this->convertKey($key));
        $existingData = $this->safelyParseArray($item->isHit() ? $item->get() : []);
        $mergedData = array_merge($existingData, $data);
        $item->set($mergedData);
        $this->cache->save($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function hGetAll(string $key): array
    {
        $item = $this->cache->getItem($this->convertKey($key));

        return $this->safelyParseArrayWithStringKeys($item->isHit() ? $item->get() : []);
    }

    public function lPush(string $key, mixed $value): int
    {
        // 如果有 Redis 适配器，使用原生的 Redis 功能
        if (null !== $this->redisConnection) {
            return $this->redisConnection->lPush($key, $value);
        }

        // 否则使用 Cache 实现简单的队列
        $item = $this->cache->getItem($this->convertKey($key));
        $list = $this->safelyParseArray($item->isHit() ? $item->get() : []);
        array_unshift($list, $value);
        $item->set($list);
        $this->cache->save($item);

        return count($list);
    }

    public function rPush(string $key, mixed $value): int
    {
        // 如果有 Redis 适配器，使用原生的 Redis 功能
        if (null !== $this->redisConnection) {
            return $this->redisConnection->rPush($key, $value);
        }

        // 否则使用 Cache 实现简单的队列
        $item = $this->cache->getItem($this->convertKey($key));
        $list = $this->safelyParseArray($item->isHit() ? $item->get() : []);
        $list[] = $value;
        $item->set($list);
        $this->cache->save($item);

        return count($list);
    }

    public function rPop(string $key): mixed
    {
        // 如果有 Redis 适配器，使用原生的 Redis 功能
        if (null !== $this->redisConnection) {
            return $this->redisConnection->rPop($key);
        }

        // 否则使用 Cache 实现简单的队列
        $item = $this->cache->getItem($this->convertKey($key));
        if (!$item->isHit()) {
            return null;
        }
        $list = $this->safelyParseArray($item->get());
        if ([] === $list) {
            return null;
        }
        $value = array_pop($list);
        $item->set($list);
        $this->cache->save($item);

        return $value;
    }

    public function lLen(string $key): int
    {
        // 如果有 Redis 适配器，使用原生的 Redis 功能
        if (null !== $this->redisConnection) {
            $result = $this->redisConnection->lLen($key);

            return false !== $result && is_int($result) ? $result : 0;
        }

        $item = $this->cache->getItem($this->convertKey($key));

        return $item->isHit() ? count($this->safelyParseCountable($item->get())) : 0;
    }

    /**
     * @return array<int, mixed>
     */
    public function lRange(string $key, int $start, int $end): array
    {
        // 如果有 Redis 适配器，使用原生的 Redis 功能
        if (null !== $this->redisConnection) {
            $result = $this->redisConnection->lrange($key, $start, $end);

            return $this->safelyParseArrayWithIntKeys($result);
        }

        $item = $this->cache->getItem($this->convertKey($key));
        if (!$item->isHit()) {
            return [];
        }
        $list = $this->safelyParseArray($item->get());
        if ($end < 0) {
            $end = count($list) + $end;
        }

        $sliced = array_slice($list, $start, $end - $start + 1);

        return $this->safelyParseArrayWithIntKeys($sliced);
    }

    public function publish(string $channel, string $message): int
    {
        // 发布/订阅功能需要 Redis 支持
        if (null !== $this->redisConnection) {
            return $this->redisConnection->publish($channel, $message);
        }

        // 对于纯 Cache 实现，可以使用标记版本号的方式
        $versionKey = "{$channel}:version";
        $versionItem = $this->cache->getItem($this->convertKey($versionKey));
        $version = $versionItem->isHit() ? $this->safelyParseInt($versionItem->get()) : 0;
        $versionItem->set($version + 1);
        $this->cache->save($versionItem);

        // 存储消息
        $messageKey = "{$channel}:message:" . ($version + 1);
        $messageItem = $this->cache->getItem($this->convertKey($messageKey));
        $messageItem->set($message);
        $messageItem->expiresAfter(3600); // 消息保留1小时
        $this->cache->save($messageItem);

        return 1;
    }

    /**
     * @param array<int, string> $channels
     */
    public function subscribe(array $channels, callable $callback): void
    {
        if ($this->hasRedisConnection()) {
            $this->subscribeWithRedis($channels, $callback);

            return;
        }

        $this->subscribeWithCache($channels, $callback);
    }

    private function hasRedisConnection(): bool
    {
        return null !== $this->redisConnection;
    }

    /**
     * @param array<int, string> $channels
     */
    private function subscribeWithRedis(array $channels, callable $callback): void
    {
        if (null === $this->redisConnection) {
            throw BusinessLogicException::resourceStateError('Redis连接不可用，无法订阅频道');
        }

        $this->redisConnection->subscribe($channels, $callback);
    }

    /**
     * @param array<int, string> $channels
     */
    private function subscribeWithCache(array $channels, callable $callback): void
    {
        foreach ($channels as $channel) {
            $this->pollChannelForMessages($channel, $callback);
        }
    }

    private function pollChannelForMessages(string $channel, callable $callback): void
    {
        $versionKey = "{$channel}:version";
        $lastVersion = $this->getInitialVersion($versionKey);
        $this->pollForUpdates($channel, $versionKey, $lastVersion, $callback);
    }

    private function getInitialVersion(string $versionKey): int
    {
        $versionItem = $this->cache->getItem($this->convertKey($versionKey));

        return $versionItem->isHit() ? $this->safelyParseInt($versionItem->get()) : 0;
    }

    private function pollForUpdates(string $channel, string $versionKey, int $lastVersion, callable $callback): void
    {
        $maxIterations = 300;
        for ($i = 0; $i < $maxIterations; ++$i) {
            $currentVersion = $this->getCurrentVersion($versionKey);

            if ($currentVersion > $lastVersion && $this->handleNewMessage($channel, $currentVersion, $callback)) {
                break;
            }

            sleep(1);
        }
    }

    private function getCurrentVersion(string $versionKey): int
    {
        $currentVersionItem = $this->cache->getItem($this->convertKey($versionKey));

        return $currentVersionItem->isHit() ? $this->safelyParseInt($currentVersionItem->get()) : 0;
    }

    private function handleNewMessage(string $channel, int $version, callable $callback): bool
    {
        $messageKey = "{$channel}:message:{$version}";
        $messageItem = $this->cache->getItem($this->convertKey($messageKey));

        if ($messageItem->isHit()) {
            $callback(null, $channel, $messageItem->get());

            return true;
        }

        return false;
    }

    public function expire(string $key, int $seconds): bool
    {
        $item = $this->cache->getItem($this->convertKey($key));
        if (!$item->isHit()) {
            return false;
        }
        $item->expiresAfter($seconds);

        return $this->cache->save($item);
    }

    public function exists(string $key): bool
    {
        return $this->cache->hasItem($this->convertKey($key));
    }

    /**
     * 安全地将混合类型转换为数组
     *
     * @return array<mixed>
     */
    private function safelyParseArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    /**
     * 安全地将混合类型转换为字符串键的数组
     *
     * @return array<string, mixed>
     */
    private function safelyParseArrayWithStringKeys(mixed $value): array
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $val) {
                $stringKey = is_string($key) ? $key : (string) $key;
                $result[$stringKey] = $val;
            }

            return $result;
        }

        return [];
    }

    /**
     * 安全地将混合类型转换为整数键的数组
     *
     * @return array<int, mixed>
     */
    private function safelyParseArrayWithIntKeys(mixed $value): array
    {
        if (is_array($value)) {
            // 重新索引数组以确保整数键
            return array_values($value);
        }

        return [];
    }

    /**
     * 安全地将混合类型转换为可计数类型
     *
     * @return array<mixed>|\Countable
     */
    private function safelyParseCountable(mixed $value): array|\Countable
    {
        if (is_array($value) || $value instanceof \Countable) {
            return $value;
        }

        return [];
    }

    /**
     * 安全地将混合类型转换为整数
     */
    private function safelyParseInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return 0;
    }
}
