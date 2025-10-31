# AutoJsControlBundle Cache 模式配置指南

## 概述

AutoJsControlBundle 现在支持使用 Symfony Cache 组件替代 Redis 作为存储后端。这对于不需要 Redis 高级功能（如发布/订阅）的简单场景特别有用。

## 已支持 Cache 模式的功能

✅ **设备心跳管理**
- 设备在线状态存储
- 心跳时间记录
- 设备性能指标缓存

✅ **缓存存储服务**
- 通用的 KV 存储
- Hash 数据结构支持
- TTL 过期时间支持

## 配置步骤

### 1. 确保 Cache 服务可用

确保你的 Symfony 应用配置了 `cache.app` 服务：

```yaml
# config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.filesystem
        # 或者使用其他适配器
        # app: cache.adapter.redis
        # app: cache.adapter.apcu
```

### 2. Bundle 配置

Bundle 已经默认配置为使用 CacheStorageAdapter：

```yaml
# packages/auto_js_control_bundle/src/Resources/config/services.yaml
Tourze\AutoJsControlBundle\Service\StorageAdapterInterface:
    alias: 'Tourze\AutoJsControlBundle\Service\CacheStorageAdapter'

Tourze\AutoJsControlBundle\Service\CacheStorageAdapter:
    arguments:
        - '@cache.app'
        - '@?redis.autojs_connection'  # 可选：保留 Redis 功能支持
```

### 3. 可选：配置 Redis 作为后备

如果你想要在需要时回退到 Redis 功能，可以保留 Redis 连接：

```yaml
# 保留 Redis 连接配置
Redis:
    alias: 'redis.autojs_connection'
    public: true
```

## 功能限制

⚠️ **以下功能仍需要 Redis**：
- 指令队列系统（发布/订阅）
- 实时通信功能
- 长轮询机制

## 使用示例

### 检查设备在线状态

```php
use Tourze\AutoJsControlBundle\Service\DeviceHeartbeatService;

class MyService
{
    public function __construct(
        private DeviceHeartbeatService $heartbeatService
    ) {
    }
    
    public function checkDevice(string $deviceCode): bool
    {
        return $this->heartbeatService->isDeviceOnline($deviceCode);
    }
}
```

### 获取设备性能指标

```php
use Tourze\AutoJsControlBundle\Service\DeviceHeartbeatService;

class MyService
{
    public function __construct(
        private DeviceHeartbeatService $heartbeatService
    ) {
    }
    
    public function getDeviceMetrics(string $deviceCode): array
    {
        return $this->heartbeatService->getDeviceMetrics($deviceCode);
    }
}
```

## 性能考虑

- **Cache 模式**：适合读多写少的场景，性能较好
- **Redis 模式**：适合高并发、实时性要求高的场景
- **混合模式**：Cache 处理简单存储，Redis 处理复杂功能

## 故障排除

### 1. Cache 服务未找到

确保 `cache.app` 服务已正确配置：

```bash
php bin/console debug:container cache.app
```

### 2. 权限问题

确保 Cache 目录有正确的写入权限：

```bash
sudo chown -R www-data:www-data var/cache/
```

### 3. TTL 不生效

检查 Cache 适配器是否支持 TTL：

```php
// 检查适配器类型
$adapter = $container->get('cache.app');
if ($adapter instanceof Symfony\Component\Cache\Adapter\FilesystemAdapter) {
    // FilesystemAdapter 支持 TTL
}
```

## 切换回 Redis

如果需要完整功能，可以切换回 RedisStorageAdapter：

```yaml
Tourze\AutoJsControlBundle\Service\StorageAdapterInterface:
    alias: 'Tourze\AutoJsControlBundle\Service\RedisStorageAdapter'
```

## 总结

Cache 模式为不需要 Redis 高级功能的场景提供了一个轻量级的替代方案。通过简单的配置，就可以将设备心跳和缓存存储功能迁移到 Symfony Cache 组件上。