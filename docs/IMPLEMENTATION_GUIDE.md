# Auto.js 控制系统实现指南

## 已完成的设计

### 1. 值对象 (ValueObject)

- **DeviceInstruction**: 设备指令的核心值对象，包含指令类型、数据、优先级等
- **RedisQueueKeys**: Redis 键命名规范和 TTL 定义
- **DeviceConnectionInfo**: 设备连接信息，用于跟踪设备状态
- **InstructionExecutionContext**: 指令执行上下文，包含重试、调度等信息

### 2. 请求 DTO (Dto/Request)

- **DeviceHeartbeatRequest**: 设备心跳请求，支持长轮询
- **ReportExecutionResultRequest**: 执行结果上报
- **DeviceLogRequest**: 批量日志上报
- **DeviceRegisterRequest**: 设备注册请求

### 3. 响应 DTO (Dto/Response)

- **DeviceHeartbeatResponse**: 心跳响应，包含待执行指令
- **ExecutionResultResponse**: 执行结果确认响应
- **DeviceLogResponse**: 日志上报确认响应
- **DeviceRegisterResponse**: 设备注册响应，返回证书
- **ScriptDownloadResponse**: 脚本下载响应

### 4. 路由配置

位置：`src/Resources/config/routes.yaml`

定义了以下端点：
- POST `/api/autojs/v1/device/register` - 设备注册
- POST `/api/autojs/v1/device/heartbeat` - 设备心跳（长轮询）
- POST `/api/autojs/v1/device/report-result` - 上报执行结果
- POST `/api/autojs/v1/device/logs` - 批量上报日志
- GET `/api/autojs/v1/script/{scriptId}` - 获取脚本内容
- POST `/api/autojs/v1/device/screenshot` - 上传截图

## 需要实现的组件

### 1. 控制器 (Controller)

创建 `DeviceApiController` 实现所有 API 端点：

```php
namespace Tourze\AutoJsControlBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/autojs/v1', name: 'auto_js_api_')]
class DeviceApiController extends AbstractController
{
    // 实现各个端点方法
}
```

### 2. 服务层 (Service)

需要创建以下服务：

- **DeviceAuthenticationService**: 处理设备认证和签名验证
- **InstructionQueueService**: 管理 Redis 指令队列
- **DeviceLongPollingService**: 实现长轮询机制
- **DeviceRegistrationService**: 处理设备注册逻辑
- **ExecutionResultService**: 处理执行结果上报

### 3. Redis 集成

使用 Symfony 的 Redis 组件实现队列操作：

```php
namespace Tourze\AutoJsControlBundle\Service;

use Symfony\Component\Cache\Adapter\RedisAdapter;

class InstructionQueueService
{
    public function __construct(
        private RedisAdapter $redis
    ) {}
    
    public function pushInstruction(string $deviceCode, DeviceInstruction $instruction): void
    {
        $key = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);
        $this->redis->lpush($key, json_encode($instruction->toArray()));
    }
    
    public function pullInstructions(string $deviceCode, int $timeout = 0): array
    {
        $key = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);
        // 实现 BRPOP 或立即返回
    }
}
```

### 4. 长轮询实现

```php
class DeviceLongPollingService
{
    public function waitForInstructions(string $deviceCode, int $timeout): array
    {
        // 1. 先检查队列中是否有指令
        // 2. 如果没有，订阅 Redis Pub/Sub
        // 3. 等待新指令或超时
        // 4. 返回指令列表
    }
}
```

### 5. 事件系统

创建以下事件：

- **DeviceRegisteredEvent**: 设备注册成功
- **InstructionReceivedEvent**: 收到新指令
- **ExecutionResultReportedEvent**: 执行结果上报
- **DeviceHeartbeatEvent**: 设备心跳

### 6. 命令行工具

创建管理命令：

- `autojs:device:list` - 列出所有设备
- `autojs:instruction:send` - 发送指令到设备
- `autojs:queue:status` - 查看队列状态
- `autojs:device:clean` - 清理离线设备

## 安全实现要点

1. **签名验证**：
   - 每个请求都必须验证 HMAC-SHA256 签名
   - 使用设备证书作为密钥
   - 验证时间戳防止重放攻击

2. **设备隔离**：
   - 设备只能访问自己的数据
   - 使用设备代码作为隔离键

3. **速率限制**：
   - 实现基于 Redis 的速率限制
   - 防止恶意请求

## 性能优化建议

1. **连接池**：
   - 使用 Redis 连接池
   - 复用长连接

2. **批量操作**：
   - 日志批量上报
   - 指令批量获取

3. **缓存策略**：
   - 缓存脚本内容
   - 缓存设备信息

## 监控和日志

1. **性能指标**：
   - 队列长度
   - 响应时间
   - 在线设备数

2. **错误监控**：
   - 认证失败
   - 执行失败
   - 超时统计

## 测试策略

1. **单元测试**：
   - DTO 验证测试
   - 签名算法测试
   - 队列操作测试

2. **集成测试**：
   - API 端点测试
   - 长轮询测试
   - Redis 操作测试

3. **性能测试**：
   - 并发设备测试
   - 队列压力测试
   - 长轮询性能测试