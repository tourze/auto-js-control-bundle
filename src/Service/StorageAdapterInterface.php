<?php

namespace Tourze\AutoJsControlBundle\Service;

/**
 * 存储适配器接口.
 *
 * 为不同的存储后端（Redis、Cache等）提供统一的接口
 */
interface StorageAdapterInterface
{
    /**
     * 设置值
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * 获取值
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 删除值
     */
    public function delete(string $key): bool;

    /**
     * 设置哈希字段.
     */
    public function hSet(string $key, string $field, mixed $value): void;

    /**
     * 获取哈希字段.
     */
    public function hGet(string $key, string $field): mixed;

    /**
     * 设置多个哈希字段.
     *
     * @param array<string, mixed> $data
     */
    public function hMSet(string $key, array $data): void;

    /**
     * 获取所有哈希字段.
     *
     * @return array<string, mixed>
     */
    public function hGetAll(string $key): array;

    /**
     * 左推入列表.
     */
    public function lPush(string $key, mixed $value): int;

    /**
     * 右推入列表.
     */
    public function rPush(string $key, mixed $value): int;

    /**
     * 右弹出列表.
     */
    public function rPop(string $key): mixed;

    /**
     * 获取列表长度.
     */
    public function lLen(string $key): int;

    /**
     * 获取列表范围.
     *
     * @return array<int, mixed>
     */
    public function lRange(string $key, int $start, int $end): array;

    /**
     * 发布消息.
     */
    public function publish(string $channel, string $message): int;

    /**
     * 订阅频道.
     *
     * @param array<int, string> $channels
     */
    public function subscribe(array $channels, callable $callback): void;

    /**
     * 设置过期时间.
     */
    public function expire(string $key, int $seconds): bool;

    /**
     * 检查键是否存在.
     */
    public function exists(string $key): bool;
}
