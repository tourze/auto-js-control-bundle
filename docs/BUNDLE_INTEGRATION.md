# Auto.js Control Bundle 集成指南

## 安装

### 1. Composer 安装

```bash
composer require tourze/auto-js-control-bundle
```

### 2. 注册 Bundle

在你的项目的 `config/bundles.php` 中添加：

```php
<?php

return [
    // ... 其他 bundles
    Tourze\Component\AutoJsControl\AutoJsControlBundle::class => ['all' => true],
];
```

### 3. 配置

创建配置文件 `config/packages/auto_js_control.yaml`：

```yaml
auto_js_control:
    # Redis 连接配置
    redis_dsn: '%env(REDIS_DSN)%'
    
    # 设备心跳配置
    heartbeat_timeout: 120        # 心跳超时时间（秒）
    heartbeat_interval: 30        # 期望的心跳间隔（秒）
    
    # 队列配置
    queue_polling_interval: 5     # 队列轮询间隔（秒）
    instruction_timeout: 300      # 指令执行超时（秒）
    max_retry_attempts: 3         # 最大重试次数
    
    # 安全配置
    security:
        api_key_header: 'X-API-Key'       # API Key 请求头名称
        enable_ip_whitelist: false        # 是否启用 IP 白名单
        allowed_ips: []                   # 允许的 IP 地址列表
    
    # 日志配置
    logging:
        enable_device_logs: true          # 是否记录设备日志
        log_retention_days: 30            # 日志保留天数
```

### 4. 路由配置

在 `config/routes.yaml` 中导入 Bundle 路由：

```yaml
auto_js_control:
    resource: '@AutoJsControlBundle/Resources/config/routes.yaml'
```

### 5. 数据库迁移

运行数据库迁移以创建必要的表：

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## 服务使用

### 设备管理服务

```php
use Tourze\Component\AutoJsControl\Service\DeviceManager;

class MyController
{
    public function __construct(
        private DeviceManager $deviceManager
    ) {}
    
    public function myAction()
    {
        // 获取在线设备
        $onlineDevices = $this->deviceManager->getOnlineDevices();
        
        // 注册新设备
        $device = $this->deviceManager->registerDevice(
            $deviceCode,
            $deviceName,
            $connectionInfo
        );
    }
}
```

### 脚本管理服务

```php
use Tourze\Component\AutoJsControl\Service\ScriptManager;

class MyService
{
    public function __construct(
        private ScriptManager $scriptManager
    ) {}
    
    public function executeScript()
    {
        // 创建脚本
        $script = $this->scriptManager->createScript(
            'My Script',
            'console.log("Hello");',
            ScriptType::JAVASCRIPT
        );
        
        // 执行脚本
        $this->scriptManager->executeOnDevice($script, $device);
    }
}
```

### 任务调度服务

```php
use Tourze\Component\AutoJsControl\Service\TaskScheduler;

class TaskService
{
    public function __construct(
        private TaskScheduler $taskScheduler
    ) {}
    
    public function scheduleTask()
    {
        // 创建任务
        $task = $this->taskScheduler->createTask(
            'Batch Task',
            TaskType::BATCH_EXECUTE,
            ['script_id' => 123]
        );
        
        // 调度任务
        $this->taskScheduler->scheduleTask($task);
    }
}
```

## 命令行工具

Bundle 提供了以下命令行工具：

```bash
# 设备管理
php bin/console auto-js:device:list              # 列出所有设备
php bin/console auto-js:device:cleanup           # 清理离线设备
php bin/console auto-js:device:simulator         # 启动设备模拟器

# 队列监控
php bin/console auto-js:queue:monitor            # 监控指令队列

# 脚本管理
php bin/console auto-js:script:validate          # 验证脚本语法

# 任务执行
php bin/console auto-js:task:execute             # 执行任务
```

## 事件系统

Bundle 提供了以下事件：

- `DeviceRegisteredEvent` - 设备注册时触发
- `DeviceStatusChangedEvent` - 设备状态变更时触发
- `ScriptExecutedEvent` - 脚本执行完成时触发
- `TaskCreatedEvent` - 任务创建时触发
- `TaskStatusChangedEvent` - 任务状态变更时触发
- `InstructionSentEvent` - 指令发送时触发

### 监听事件示例

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\Component\AutoJsControl\Event\DeviceRegisteredEvent;

class MyEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DeviceRegisteredEvent::class => 'onDeviceRegistered',
        ];
    }
    
    public function onDeviceRegistered(DeviceRegisteredEvent $event): void
    {
        $device = $event->getDevice();
        // 处理设备注册逻辑
    }
}
```

## 扩展指令类型

要添加自定义指令类型，创建一个处理器并标记它：

```php
use Tourze\Component\AutoJsControl\Handler\InstructionHandlerInterface;

class MyCustomInstructionHandler implements InstructionHandlerInterface
{
    public function handle(array $instruction): void
    {
        // 处理指令
    }
}
```

在服务配置中：

```yaml
services:
    App\Handler\MyCustomInstructionHandler:
        tags:
            - { name: 'auto_js_control.instruction', type: 'my_custom_type' }
```

## 安全建议

1. **生产环境配置**：
   - 启用 IP 白名单
   - 使用强 API Key
   - 配置适当的心跳超时

2. **Redis 安全**：
   - 使用密码保护的 Redis
   - 限制 Redis 访问

3. **日志管理**：
   - 定期清理旧日志
   - 监控日志大小

## 故障排除

### 设备无法连接

1. 检查 Redis 连接
2. 验证 API Key 配置
3. 检查 IP 白名单设置

### 指令执行超时

1. 增加 `instruction_timeout` 配置
2. 检查设备网络连接
3. 查看设备日志

### 内存问题

1. 调整队列轮询间隔
2. 限制并发任务数
3. 定期清理执行记录