# Auto.js Control Bundle 事件系统

## 概述

Auto.js Control Bundle 实现了完整的事件驱动架构，用于处理设备管理、任务调度、脚本执行等关键业务流程。本文档介绍事件系统的设计和使用方法。

## 事件类型

### 1. DeviceRegisteredEvent

**触发时机**：新设备首次注册到系统时

**事件数据**：
- `device`: AutoJsDevice - 注册的设备实体
- `ipAddress`: string - 设备IP地址
- `deviceInfo`: array - 设备额外信息

**使用场景**：
- 初始化设备监控数据
- 发送欢迎消息
- 记录审计日志

### 2. DeviceStatusChangedEvent

**触发时机**：设备在线状态发生变化时

**事件数据**：
- `device`: AutoJsDevice - 状态变更的设备
- `previousStatus`: bool - 之前的在线状态
- `currentStatus`: bool - 当前的在线状态
- `statusChangedAt`: ?DateTimeInterface - 状态变更时间

**辅助方法**：
- `isOnline()`: bool - 判断是否从离线变为在线
- `isOffline()`: bool - 判断是否从在线变为离线

**使用场景**：
- 设备上线时检查待执行任务
- 设备离线时取消运行中任务
- 更新监控数据

### 3. TaskCreatedEvent

**触发时机**：新任务被创建时

**事件数据**：
- `task`: Task - 创建的任务实体
- `createdBy`: ?string - 任务创建者
- `context`: array - 上下文信息

**辅助方法**：
- `isImmediate()`: bool - 判断是否为立即执行任务
- `isScheduled()`: bool - 判断是否为计划任务

**使用场景**：
- 立即执行任务的自动调度
- 记录任务创建日志
- 通知相关设备

### 4. TaskStatusChangedEvent

**触发时机**：任务执行状态发生变化时

**事件数据**：
- `task`: Task - 状态变更的任务
- `previousStatus`: TaskStatus - 之前的状态
- `currentStatus`: TaskStatus - 当前的状态
- `statusChangedAt`: ?DateTimeInterface - 状态变更时间
- `reason`: ?string - 状态变更原因

**辅助方法**：
- `hasStarted()`: bool - 判断任务是否开始执行
- `hasCompleted()`: bool - 判断任务是否执行完成
- `hasFailed()`: bool - 判断任务是否执行失败
- `wasCancelled()`: bool - 判断任务是否被取消

**使用场景**：
- 更新任务执行时间
- 触发任务重试机制
- 统计任务执行结果

### 5. ScriptExecutedEvent

**触发时机**：脚本开始执行或执行完成时

**事件数据**：
- `script`: Script - 执行的脚本
- `device`: AutoJsDevice - 执行脚本的设备
- `task`: ?Task - 关联的任务（如果有）
- `executionRecord`: ?ScriptExecutionRecord - 执行记录
- `isStarted`: bool - 是否为开始执行事件
- `executionResult`: array - 执行结果（仅在执行完成时有效）

**辅助方法**：
- `isStarted()`: bool - 判断是否为执行开始事件
- `isCompleted()`: bool - 判断是否为执行完成事件
- `isSuccess()`: bool - 判断执行是否成功
- `getErrorMessage()`: ?string - 获取错误信息

**使用场景**：
- 创建/更新执行记录
- 记录脚本执行日志
- 更新任务进度

### 6. InstructionSentEvent

**触发时机**：向设备发送指令时

**事件数据**：
- `instruction`: DeviceInstruction - 发送的指令
- `device`: AutoJsDevice - 目标设备
- `success`: bool - 是否发送成功
- `errorMessage`: ?string - 错误信息（如果发送失败）
- `metadata`: array - 元数据

**辅助方法**：
- `isSuccess()`: bool - 判断指令是否发送成功
- `getInstructionType()`: string - 获取指令类型
- `getInstructionContent()`: array - 获取指令内容
- `isHighPriority()`: bool - 判断是否为高优先级指令

**使用场景**：
- 记录重要指令日志
- 监控指令发送状态
- 统计指令执行情况

## 事件订阅者

### 1. DeviceEventSubscriber

**职责**：处理设备相关事件

**监听事件**：
- DeviceRegisteredEvent (优先级: 10)
- DeviceStatusChangedEvent (优先级: 5)

**主要功能**：
- 初始化新设备的监控数据
- 发送欢迎指令给新注册设备
- 设备上线时检查并分发待执行任务
- 设备离线时取消该设备的运行中任务
- 更新设备监控数据

### 2. TaskEventSubscriber

**职责**：处理任务和脚本执行相关事件

**监听事件**：
- TaskCreatedEvent (优先级: 10)
- TaskStatusChangedEvent (优先级: 5)
- ScriptExecutedEvent (优先级: 0)

**主要功能**：
- 立即执行任务的自动调度
- 记录任务开始/完成时间
- 计算任务执行统计
- 处理任务失败重试
- 创建和更新脚本执行记录
- 取消任务时清理相关执行记录

### 3. NotificationEventSubscriber

**职责**：记录重要事件到日志系统

**监听事件**：
- 所有事件 (优先级: -10，最后执行)

**主要功能**：
- 将重要事件记录到设备日志表
- 区分不同类型的日志（系统、任务、脚本）
- 设置适当的日志级别（INFO、WARNING、ERROR）
- 为审计和问题追踪提供完整的事件历史

## 使用示例

### 1. 手动触发事件

```php
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\Component\AutoJsControl\Event\DeviceRegisteredEvent;

class SomeService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    ) {}
    
    public function registerDevice(AutoJsDevice $device, string $ipAddress): void
    {
        // ... 设备注册逻辑 ...
        
        // 触发设备注册事件
        $event = new DeviceRegisteredEvent($device, $ipAddress, [
            'userAgent' => $request->headers->get('User-Agent'),
            'version' => $request->get('version'),
        ]);
        
        $this->eventDispatcher->dispatch($event);
    }
}
```

### 2. 创建自定义事件订阅者

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\Component\AutoJsControl\Event\TaskStatusChangedEvent;

class CustomTaskSubscriber
{
    #[AsEventListener(event: TaskStatusChangedEvent::class, priority: 15)]
    public function onTaskCompleted(TaskStatusChangedEvent $event): void
    {
        if (!$event->hasCompleted()) {
            return;
        }
        
        $task = $event->getTask();
        // 发送任务完成通知
        // 生成任务报告
        // 触发后续流程
    }
}
```

### 3. 在控制器中使用事件

```php
use Tourze\Component\AutoJsControl\Event\TaskCreatedEvent;

class TaskController extends AbstractApiController
{
    public function create(Request $request): JsonResponse
    {
        $task = $this->taskScheduler->createTask($request->request->all());
        
        // 触发任务创建事件
        $event = new TaskCreatedEvent(
            $task,
            $this->getUser()?->getUsername(),
            ['source' => 'api', 'ip' => $request->getClientIp()]
        );
        
        $this->eventDispatcher->dispatch($event);
        
        return $this->json(['task' => $task]);
    }
}
```

## 事件流程图

```
设备注册流程：
Device Register API → DeviceManager::registerOrUpdateDevice()
    ↓
DeviceRegisteredEvent dispatched
    ↓
DeviceEventSubscriber::onDeviceRegistered()
    ├─→ Create monitor data
    ├─→ Send welcome instruction
    └─→ Log event
    ↓
NotificationEventSubscriber::onDeviceRegistered()
    └─→ Create device log

任务执行流程：
Task Create API → TaskScheduler::createAndScheduleTask()
    ↓
TaskCreatedEvent dispatched
    ↓
TaskEventSubscriber::onTaskCreated()
    └─→ Schedule for immediate execution (if applicable)
    ↓
Task dispatched to devices
    ↓
TaskStatusChangedEvent dispatched (status: RUNNING)
    ↓
ScriptExecutedEvent dispatched (isStarted: true)
    ↓
... script execution ...
    ↓
ScriptExecutedEvent dispatched (isStarted: false)
    ↓
TaskStatusChangedEvent dispatched (status: COMPLETED/FAILED)
```

## 最佳实践

1. **事件优先级**：
   - 高优先级 (>0)：核心业务逻辑
   - 默认优先级 (0)：一般处理
   - 低优先级 (<0)：日志记录、通知等

2. **错误处理**：
   - 事件处理器中的异常不应影响主流程
   - 使用 try-catch 包裹非关键逻辑
   - 记录错误但继续执行

3. **性能考虑**：
   - 避免在事件处理器中执行长时间操作
   - 使用异步消息队列处理耗时任务
   - 合理使用事件订阅者的优先级

4. **测试建议**：
   - 为每个事件编写单元测试
   - 测试事件的触发条件和数据完整性
   - 模拟事件订阅者的行为

## 扩展事件系统

要添加新的事件：

1. 在 `src/Event/` 目录创建新的事件类
2. 继承 `Symfony\Contracts\EventDispatcher\Event`
3. 添加必要的属性和 getter 方法
4. 在相应的服务中触发事件
5. 创建或更新事件订阅者来处理新事件

```php
namespace Tourze\Component\AutoJsControl\Event;

use Symfony\Contracts\EventDispatcher\Event;

class CustomEvent extends Event
{
    public function __construct(
        private readonly mixed $data,
        private readonly array $context = []
    ) {}
    
    public function getData(): mixed
    {
        return $this->data;
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
}
```

## 相关配置

确保在 `services.yaml` 中正确配置事件订阅者：

```yaml
services:
    # 自动注册事件订阅者
    Tourze\Component\AutoJsControl\EventSubscriber\:
        resource: '../src/EventSubscriber/'
        tags: ['kernel.event_subscriber']
```

## 总结

事件系统为 Auto.js Control Bundle 提供了灵活的扩展机制，使得各个组件之间保持松耦合的同时，能够有效地协同工作。通过合理使用事件系统，可以轻松添加新功能、集成第三方服务、实现审计日志等需求。