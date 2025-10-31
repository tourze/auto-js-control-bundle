# Auto.js 控制 Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://packagist.org/packages/tourze/auto-js-control-bundle)
[![Symfony Version](https://img.shields.io/badge/symfony-%5E7.3-green.svg)](https://packagist.org/packages/tourze/auto-js-control-bundle)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

一个综合性的 Symfony Bundle，通过 HTTP 长轮询管理和控制 Auto.js 设备，提供企业级远程自动化能力，包括任务调度、脚本分发和实时监控。

## 目录

- [功能特性](#功能特性)
- [系统要求](#系统要求)
- [安装](#安装)
- [配置](#配置)
- [快速开始](#快速开始)
- [使用方法](#使用方法)
- [API 参考](#api-参考)
- [高级用法](#高级用法)
- [安全性](#安全性)
- [测试](#测试)
- [贡献](#贡献)
- [许可证](#许可证)

## 功能特性

- **设备管理**：注册、认证和管理数千个 Auto.js 设备
- **脚本分发**：在设备集群中部署和执行 JavaScript 脚本
- **HTTP 长轮询**：针对移动网络优化的可靠通信
- **任务调度**：立即执行、定时执行或周期性执行任务
- **实时监控**：跟踪设备状态、收集日志和监控性能
- **安全优先**：基于 PKI 的认证、加密通信、沙箱执行
- **可扩展架构**：Redis 支持的队列，分布式部署就绪
- **RESTful API**：完善的设备通信和管理 API 文档

## 系统要求

- PHP 8.1 或更高版本
- Symfony 7.3 或更高版本
- MySQL/MariaDB 或 PostgreSQL
- Redis 6.0 或更高版本
- Composer
- PHP 扩展: ext-redis, ext-json, ext-hash, ext-pcntl

## 安装

使用 Composer 安装此 bundle：

```bash
composer require tourze/auto-js-control-bundle
```

在 `config/bundles.php` 中注册 bundle：

```php
return [
    // ...
    Tourze\AutoJsControlBundle\AutoJsControlBundle::class => ['all' => true],
];
```

## 配置

在 `config/packages/auto_js_control.yaml` 中创建配置文件：

```yaml
auto_js_control:
    redis:
        dsn: '%env(REDIS_DSN)%'
    security:
        api_key: '%env(AUTOJS_API_KEY)%'
        signature_algorithm: 'sha256'
        timestamp_tolerance: 300
    device:
        offline_threshold: 300
        heartbeat_interval: 30
        cleanup_interval: 3600
    task:
        default_timeout: 300
        retry_attempts: 3
        retry_delay: 60
    polling:
        timeout: 30
        max_timeout: 60
```

必需的环境变量：

```bash
# .env
REDIS_DSN=redis://localhost:6379
AUTOJS_API_KEY=your-secret-api-key
DATABASE_URL=mysql://user:pass@localhost:3306/dbname
```

## 快速开始

### 1. 配置数据库

运行迁移以创建必要的表：

```bash
php bin/console doctrine:migrations:migrate
```

### 2. 配置 Redis

在 `.env` 文件中配置 Redis 连接：

```env
REDIS_DSN=redis://localhost:6379
```

### 3. 注册设备

```bash
php bin/console auto-js:device:register device001 --name="测试设备"
```

### 4. 创建并执行脚本

```php
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Service\TaskScheduler;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Doctrine\ORM\EntityManagerInterface;

$entityManager = $this->container->get(EntityManagerInterface::class);
$taskScheduler = $this->container->get(TaskScheduler::class);

// 创建脚本
$script = new Script();
$script->setName('我的脚本')
       ->setContent('console.log("Hello World");')
       ->setScriptType(ScriptType::JAVASCRIPT);

$entityManager->persist($script);
$entityManager->flush();

// 在指定设备上执行
$task = $taskScheduler->createAndScheduleTask([
    'name' => '我的任务',
    'taskType' => TaskType::IMMEDIATE->value,
    'targetType' => TaskTargetType::SPECIFIC->value,
    'script' => $script,
    'targetDevices' => ['device001']
]);
```

## 使用方法

### 设备管理

```php
use Tourze\AutoJsControlBundle\Service\DeviceManager;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;

$deviceManager = $this->container->get(DeviceManager::class);
$deviceRepo = $this->container->get(AutoJsDeviceRepository::class);

// 获取在线设备
$onlineDevices = $deviceRepo->findAllOnlineDevices();

// 根据设备代码获取设备
$device = $deviceRepo->findByDeviceCode('device001');

// 检查设备状态
if ($device && $device->isOnline()) {
    echo "设备在线";
}
```

### 脚本管理

```php
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Service\ScriptValidationService;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Doctrine\ORM\EntityManagerInterface;

$entityManager = $this->container->get(EntityManagerInterface::class);
$validationService = $this->container->get(ScriptValidationService::class);

// 创建新脚本
$script = new Script();
$script->setName('我的脚本')
       ->setContent('console.log("Hello World");')
       ->setScriptType(ScriptType::JAVASCRIPT)
       ->setTimeout(60);

// 验证脚本语法
if ($validationService->validateScript($script)) {
    $entityManager->persist($script);
    $entityManager->flush();
}
```

### 任务调度

```php
use Tourze\AutoJsControlBundle\Service\TaskScheduler;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;

$scheduler = $this->container->get(TaskScheduler::class);

// 立即执行
$task = $scheduler->createAndScheduleTask([
    'name' => '立即任务',
    'script' => $script,
    'taskType' => TaskType::IMMEDIATE->value,
    'targetType' => TaskTargetType::SPECIFIC->value,
    'targetDevices' => ['device001', 'device002']
]);

// 定时执行
$scheduledTask = $scheduler->createAndScheduleTask([
    'name' => '定时任务',
    'script' => $script,
    'taskType' => TaskType::SCHEDULED->value,
    'targetType' => TaskTargetType::SPECIFIC->value,
    'scheduledTime' => (new \DateTime('+1 hour'))->format('c'),
    'targetDevices' => ['device001']
]);

// 周期性任务
$recurringTask = $scheduler->createAndScheduleTask([
    'name' => '每日任务',
    'script' => $script,
    'taskType' => TaskType::RECURRING->value,
    'targetType' => TaskTargetType::SPECIFIC->value,
    'cronExpression' => '0 9 * * *', // 每天上午9点
    'targetDevices' => ['device001']
]);
```

### 控制台命令

Bundle 提供了多个用于管理和调试的控制台命令：

```bash
# 设备管理
php bin/console auto-js:device:list              # 列出所有注册的设备
php bin/console auto-js:device:cleanup           # 清理离线设备
php bin/console auto-js:device:simulator         # 模拟设备进行测试

# 队列监控
php bin/console auto-js:queue:monitor            # 实时监控指令队列

# 脚本管理
php bin/console auto-js:script:validate          # 验证脚本语法

# 任务执行
php bin/console auto-js:task:execute             # 手动执行任务
```

## API 参考

### 设备 API 端点

- `POST /api/autojs/v1/device/register` - 注册新设备
- `POST /api/autojs/v1/device/heartbeat` - 设备心跳与长轮询
- `POST /api/autojs/v1/device/report-result` - 报告脚本执行结果
- `POST /api/autojs/v1/device/logs` - 批量上传设备日志
- `GET /api/autojs/v1/device/script/{scriptId}` - 下载脚本内容
- `POST /api/autojs/v1/device/screenshot` - 上传执行截图

### 管理端点

- `GET /api/autojs/v1/devices` - 列出所有设备
- `GET /api/autojs/v1/scripts` - 列出所有脚本
- `POST /api/autojs/v1/scripts` - 创建新脚本
- `POST /api/autojs/v1/tasks` - 创建并调度任务
- `GET /api/autojs/v1/tasks/{id}/status` - 获取任务执行状态

## 系统架构

### 架构概述

Auto.js 控制 Bundle 实现了一个综合的设备管理系统，架构如下：

```text
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Auto.js       │    │   Symfony        │    │   Redis         │
│   设备群         │◄──►│   应用程序        │◄──►│   消息队列       │
│                 │    │                  │    │                 │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                        │                        │
         │                        │                        │
         ▼                        ▼                        ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   HTTP 长       │    │   任务           │    │   设备          │
│   轮询          │    │   调度器         │    │   监控          │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### 核心组件

#### 1. HTTP 长轮询 API
- **设备注册**：安全的设备注册和证书生成
- **心跳机制**：长轮询实现实时指令下发
- **结果上报**：异步执行结果收集
- **日志聚合**：集中式设备日志收集

#### 2. 任务管理系统
- **脚本管理**：JavaScript 代码分发和版本控制
- **任务调度**：立即、定时和周期性任务执行
- **优先队列**：多级任务优先级管理
- **执行跟踪**：全面的任务状态监控

#### 3. 设备监控
- **实时指标**：CPU、内存、电池和网络监控
- **健康检查**：自动化设备可用性检测
- **日志分析**：集中式日志记录与过滤搜索
- **告警系统**：可配置的设备问题通知

#### 4. 安全框架
- **基于证书的认证**：HMAC-SHA256 签名请求
- **时间戳保护**：防重放攻击机制
- **访问控制**：设备特定的资源隔离
- **加密通信**：仅 TLS 数据传输

### 数据流程

1. **设备注册流程**：
    - 设备发送包含硬件信息的注册请求
    - 服务器验证请求并生成唯一证书
    - 设备接收证书用于后续认证

2. **任务执行流程**：
    - 管理员创建任务并指定目标设备/组
    - TaskScheduler 将指令分发到设备队列
    - 设备通过长轮询获取指令
    - 在设备上执行指令并上报结果

3. **长轮询机制**：
    - 设备发起心跳请求，配置超时时间
    - 服务器立即检查指令队列
    - 如无指令，服务器等待 Redis pub/sub 通知
    - 新指令触发立即响应或超时返回

### Redis 队列设计

#### 队列结构
```text
device_instruction_queue:{deviceCode}  # 设备特定指令队列
device_poll_notify:{deviceCode}        # Pub/Sub 通知通道
device_online:{deviceCode}             # 在线状态跟踪
instruction_status:{instructionId}     # 执行状态跟踪
```

#### 队列操作
- **LPUSH**：将高优先级指令添加到队列前端
- **RPUSH**：将普通指令添加到队列后端
- **BRPOP**：阻塞式指令检索
- **PUBLISH**：通知长轮询客户端有新指令

## 高级用法

### 自定义指令类型

通过实现指令接口创建自定义指令类型：

```php
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

// 使用标准 DeviceInstruction 类创建自定义指令
$customInstruction = new DeviceInstruction(
    instructionId: 'CUSTOM-001',
    type: 'custom_action',
    data: [
        'action' => 'custom',
        'parameters' => ['key' => 'value']
    ],
    timeout: 300,
    priority: 5
);
```

### 事件监听器

监听设备和任务事件：

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\AutoJsControlBundle\Event\DeviceRegisteredEvent;
use Tourze\AutoJsControlBundle\Event\TaskCreatedEvent;
use Tourze\AutoJsControlBundle\Event\ScriptExecutedEvent;

class AutoJsEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DeviceRegisteredEvent::class => 'onDeviceRegistered',
            TaskCreatedEvent::class => 'onTaskCreated',
            ScriptExecutedEvent::class => 'onScriptExecuted',
        ];
    }
    
    public function onDeviceRegistered(DeviceRegisteredEvent $event): void
    {
        $device = $event->getDevice();
        // 处理设备注册逻辑
    }
    
    public function onTaskCreated(TaskCreatedEvent $event): void
    {
        $task = $event->getTask();
        // 处理任务创建逻辑
    }
    
    public function onScriptExecuted(ScriptExecutedEvent $event): void
    {
        $executionRecord = $event->getExecutionRecord();
        // 处理脚本执行完成逻辑
    }
}
```

### 监控和日志

```php
use Tourze\AutoJsControlBundle\Repository\DeviceMonitorDataRepository;
use Tourze\AutoJsControlBundle\Repository\DeviceLogRepository;

$monitorRepo = $this->container->get(DeviceMonitorDataRepository::class);
$logRepo = $this->container->get(DeviceLogRepository::class);

// 获取设备最新监控数据
$device = $deviceRepo->findByDeviceCode('device001');
if ($device) {
    $monitorData = $monitorRepo->findLatestByDevice($device);
    if ($monitorData) {
        $cpuUsage = $monitorData->getCpuUsage();
        $memoryUsage = $monitorData->getMemoryUsage();
        $batteryLevel = $monitorData->getBatteryLevel();
    }
    
    // 查询设备日志
    $logs = $logRepo->findRecentByDevice($device, [
        'level' => 'ERROR',
        'since' => new \DateTime('-1 hour'),
        'limit' => 100
    ]);
}
```

## 安全性

### 认证

Bundle 使用基于 PKI 的认证系统：

1. 设备在注册时生成证书
2. 所有请求都使用 HMAC-SHA256 签名
3. 支持证书轮换和撤销

### 加密

- 使用 TLS 1.2+ 进行传输加密
- 敏感数据在存储前加密
- 支持端到端加密选项

### 最佳实践

1. 定期轮换设备证书
2. 使用强密码和访问控制
3. 监控异常设备行为
4. 实施速率限制
5. 保持 Bundle 更新到最新版本

## 开发指南

### 开发环境搭建

1. **克隆仓库并安装依赖**：
```bash
git clone <repository-url>
cd php-monorepo
composer install
```

2. **配置数据库**：
```bash
# 创建数据库
bin/console doctrine:database:create

# 运行迁移
bin/console doctrine:migrations:migrate
```

3. **启动 Redis**：
```bash
# 使用 Docker
docker run -d -p 6379:6379 redis:6.0-alpine

# 或本地安装
sudo systemctl start redis
```

### 开发工作流

1. **创建新功能**：
    - 遵循 PSR-12 编码标准
    - 在 `src/Entity/` 创建实体
    - 在 `src/Repository/` 添加仓储
    - 在 `src/Service/` 实现服务
    - 在 `src/Controller/` 添加控制器

2. **数据库变更**：
```bash
# 生成迁移
bin/console doctrine:migrations:diff

# 检查并执行
bin/console doctrine:migrations:migrate
```

3. **添加命令**：
```bash
# 生成命令
bin/console make:command

# 如需要，在 services.yaml 中注册
```

### 代码质量工具

```bash
# PHPStan 静态分析
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/auto-js-control-bundle

# 代码风格修复
./vendor/bin/php-cs-fixer fix packages/auto-js-control-bundle/src

# 包验证
bin/console app:check-packages auto-js-control-bundle -o -f --skip=github-actions-status
```

### 测试

```bash
# 从项目根目录执行

# 运行所有测试
./vendor/bin/phpunit packages/auto-js-control-bundle/tests

# 运行特定测试类
./vendor/bin/phpunit packages/auto-js-control-bundle/tests/Service/TaskSchedulerTest.php

# 生成覆盖率报告
./vendor/bin/phpunit packages/auto-js-control-bundle/tests --coverage-html coverage

# 使用真实 Redis 的集成测试
REDIS_DSN=redis://localhost:6379/15 ./vendor/bin/phpunit packages/auto-js-control-bundle/tests
```

### 调试

1. **启用调试模式**：
```yaml
# config/packages/dev/auto_js_control.yaml
auto_js_control:
    logging:
        log_levels: [DEBUG, INFO, WARNING, ERROR, CRITICAL]
    performance:
        enable_caching: false
```

2. **监控 Redis 队列**：
```bash
# 队列监控控制台命令
bin/console auto-js:queue:monitor

# 或使用 Redis CLI
redis-cli MONITOR
```

3. **设备模拟**：
```bash
# 模拟设备用于测试
bin/console auto-js:device:simulator --count=5 --duration=3600
```

### 性能优化

1. **数据库优化**：
    - 在频繁查询的列上使用适当的索引
    - 在合适的地方实现查询结果缓存
    - 对大型集合使用 EXTRA_LAZY 加载

2. **Redis 优化**：
    - 为键设置适当的 TTL 值
    - 对批量操作使用 Redis 管道
    - 监控 Redis 内存使用情况

3. **应用程序优化**：
    - 在生产环境启用 OPCache
    - 使用 Symfony 的 HTTP 缓存
    - 实施适当的日志级别

### 添加新的设备指令

1. **定义指令类型**：
```php
// 在 DeviceInstruction 类中
public const TYPE_CUSTOM_ACTION = 'custom_action';
```

2. **创建处理器**（可选）：
```php
namespace Tourze\AutoJsControlBundle\Handler;

class CustomActionInstructionHandler implements InstructionHandlerInterface
{
    public function handle(DeviceInstruction $instruction): void
    {
        // 自定义处理逻辑
    }
}
```

3. **注册处理器**：
```yaml
# config/services.yaml
services:
    Tourze\AutoJsControlBundle\Handler\CustomActionInstructionHandler:
        tags:
            - { name: 'auto_js_control.instruction_handler', type: 'custom_action' }
```

## 贡献

欢迎贡献！请查看贡献指南。

## 许可证

本项目基于 MIT 许可证。详见 [LICENSE](LICENSE) 文件。