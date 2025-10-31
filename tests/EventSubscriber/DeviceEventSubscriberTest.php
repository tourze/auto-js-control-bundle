<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\AutoJsControlBundle\Contract\DeviceMonitorDataRepositoryInterface;
use Tourze\AutoJsControlBundle\Event\DeviceRegisteredEvent;
use Tourze\AutoJsControlBundle\Event\DeviceStatusChangedEvent;
use Tourze\AutoJsControlBundle\EventSubscriber\DeviceEventSubscriber;
use Tourze\AutoJsControlBundle\Service\DeviceManagerInterface;
use Tourze\AutoJsControlBundle\Tests\Fixtures\FixtureFactory;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class DeviceEventSubscriberTest extends AbstractEventSubscriberTestCase
{
    private DeviceEventSubscriber $subscriber;

    private DeviceManagerInterface $deviceManager;

    private DeviceMonitorDataRepositoryInterface $monitorDataRepository;

    private LoggerInterface $logger;

    protected function onSetUp(): void
    {
        /*
         * 使用匿名类模拟 DeviceManager
         * 在集成测试中，需要先创建 Mock 服务并注入到容器，然后从容器获取测试目标服务
         */
        $this->deviceManager = new class implements DeviceManagerInterface {
            private bool $shouldThrow = false;

            private \Exception $exception;

            /** @var array<array{method: string, args: array<mixed>}> */
            private array $calls = [];

            /** @var array<array{device: object}> */
            private array $welcomeInstructionCalls = [];

            /** @var array<array{device: object}> */
            private array $pendingTaskCalls = [];

            /** @var array<array{device: object}> */
            private array $cancelTaskCalls = [];

            public function __construct(?\Exception $exception = null)
            {
                $this->exception = $exception ?? new \Exception('Network error');
            }

            /** @param array<string, mixed> $deviceInfo */
            public function registerOrUpdateDevice(string $deviceCode, string $deviceName, string $certificateRequest, array $deviceInfo, string $clientIp): object
            {
                $this->calls[] = ['method' => 'registerOrUpdateDevice', 'args' => func_get_args()];
                if ($this->shouldThrow) {
                    throw $this->exception;
                }

                return new \stdClass();
            }

            public function getDevice(string $deviceCode): object
            {
                $this->calls[] = ['method' => 'getDevice', 'args' => func_get_args()];

                return new \stdClass();
            }

            public function getDeviceById(int $deviceId): object
            {
                $this->calls[] = ['method' => 'getDeviceById', 'args' => func_get_args()];

                return new \stdClass();
            }

            /** @return array{devices: array<mixed>, pagination: array{total: int}} */
            public function getOnlineDevices(int $page = 1, int $limit = 20): array
            {
                $this->calls[] = ['method' => 'getOnlineDevices', 'args' => func_get_args()];

                return ['devices' => [], 'pagination' => ['total' => 0]];
            }

            public function updateDeviceStatus(string $deviceCode, string $status): void
            {
                $this->calls[] = ['method' => 'updateDeviceStatus', 'args' => func_get_args()];
            }

            public function deleteDevice(string $deviceCode): void
            {
                $this->calls[] = ['method' => 'deleteDevice', 'args' => func_get_args()];
            }

            /** @return array{total: int, online: int, offline: int} */
            public function getDeviceStatistics(): array
            {
                $this->calls[] = ['method' => 'getDeviceStatistics', 'args' => func_get_args()];

                return ['total' => 0, 'online' => 0, 'offline' => 0];
            }

            public function sendWelcomeInstruction(object $device): void
            {
                $this->welcomeInstructionCalls[] = ['device' => $device];
                if ($this->shouldThrow) {
                    throw $this->exception;
                }
            }

            public function checkPendingTasks(object $device): void
            {
                $this->pendingTaskCalls[] = ['device' => $device];
            }

            public function cancelRunningTasks(object $device): void
            {
                $this->cancelTaskCalls[] = ['device' => $device];
            }

            /** @param string[] $deviceCodes */
            /** @return array<string, mixed> */
            public function getDevicesStatus(array $deviceCodes): array
            {
                $this->calls[] = ['method' => 'getDevicesStatus', 'args' => func_get_args()];

                return [];
            }

            /** @param array<string, mixed> $criteria */
            /** @param array<string, string> $orderBy */
            /** @return array<object> */
            public function searchDevices(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
            {
                $this->calls[] = ['method' => 'searchDevices', 'args' => func_get_args()];

                return [];
            }

            // 辅助方法用于测试验证
            /** @return array<array{method: string, args: array<mixed>}> */
            public function getCalls(): array
            {
                return $this->calls;
            }

            /** @return array<array{device: object}> */
            public function getWelcomeInstructionCalls(): array
            {
                return $this->welcomeInstructionCalls;
            }

            /** @return array<array{device: object}> */
            public function getPendingTaskCalls(): array
            {
                return $this->pendingTaskCalls;
            }

            /** @return array<array{device: object}> */
            public function getCancelTaskCalls(): array
            {
                return $this->cancelTaskCalls;
            }

            public function setShouldThrow(bool $shouldThrow, ?\Exception $exception = null): void
            {
                $this->shouldThrow = $shouldThrow;
                if (null !== $exception) {
                    $this->exception = $exception;
                }
            }

            public function clear(): void
            {
                $this->calls = [];
                $this->welcomeInstructionCalls = [];
                $this->pendingTaskCalls = [];
                $this->cancelTaskCalls = [];
            }
        };

        /*
         * 使用匿名类模拟 DeviceMonitorDataRepository
         */
        $this->monitorDataRepository = new class implements DeviceMonitorDataRepositoryInterface {
            private bool $shouldThrow = false;

            private \Exception $exception;

            /** @var array<array{device: object}> */
            private array $createInitialDataCalls = [];

            /** @var array<array{device: object, time: ?\DateTime}> */
            private array $updateStatusChangedTimeCalls = [];

            public function __construct(?\Exception $exception = null)
            {
                $this->exception = $exception ?? new \Exception('Database error');
            }

            public function createInitialData(object $device): void
            {
                $this->createInitialDataCalls[] = ['device' => $device];
                if ($this->shouldThrow) {
                    throw $this->exception;
                }
            }

            public function updateStatusChangedTime(object $device, ?\DateTime $time = null): void
            {
                if ($this->shouldThrow) {
                    throw $this->exception;
                }
                $this->updateStatusChangedTimeCalls[] = ['device' => $device, 'time' => $time];
            }

            // 辅助方法用于测试验证
            /** @return array<array{device: object}> */
            public function getCreateInitialDataCalls(): array
            {
                return $this->createInitialDataCalls;
            }

            /** @return array<array{device: object, time: ?\DateTime}> */
            public function getUpdateStatusChangedTimeCalls(): array
            {
                return $this->updateStatusChangedTimeCalls;
            }

            public function setShouldThrow(bool $shouldThrow, ?\Exception $exception = null): void
            {
                $this->shouldThrow = $shouldThrow;
                if (null !== $exception) {
                    $this->exception = $exception;
                }
            }

            public function clear(): void
            {
                $this->createInitialDataCalls = [];
                $this->updateStatusChangedTimeCalls = [];
            }
        };

        /*
         * 使用匿名类模拟 LoggerInterface
         */
        $this->logger = new class implements LoggerInterface {
            /** @var array<array{level: string, message: \Stringable|string, context: array<mixed>}> */
            private array $logs = [];

            /** @var array<array{message: \Stringable|string, context: array<mixed>}> */
            private array $infoCalls = [];

            /** @var array<array{message: \Stringable|string, context: array<mixed>}> */
            private array $warningCalls = [];

            /** @var array<array{message: \Stringable|string, context: array<mixed>}> */
            private array $errorCalls = [];

            /** @param array<mixed> $context */
            public function emergency(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'emergency', 'message' => $message, 'context' => $context];
            }

            /** @param array<mixed> $context */
            public function alert(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'alert', 'message' => $message, 'context' => $context];
            }

            /** @param array<mixed> $context */
            public function critical(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
            }

            /** @param array<mixed> $context */
            public function error(\Stringable|string $message, array $context = []): void
            {
                $this->errorCalls[] = ['message' => $message, 'context' => $context];
                $this->logs[] = ['level' => 'error', 'message' => $message, 'context' => $context];
            }

            /** @param array<mixed> $context */
            public function warning(\Stringable|string $message, array $context = []): void
            {
                $this->warningCalls[] = ['message' => $message, 'context' => $context];
                $this->logs[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
            }

            /** @param array<mixed> $context */
            public function notice(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'notice', 'message' => $message, 'context' => $context];
            }

            /** @param array<mixed> $context */
            public function info(\Stringable|string $message, array $context = []): void
            {
                $this->infoCalls[] = ['message' => $message, 'context' => $context];
                $this->logs[] = ['level' => 'info', 'message' => $message, 'context' => $context];
            }

            /** @param array<mixed> $context */
            public function debug(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
            }

            /** @param array<mixed> $context */
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }

            // 辅助方法用于测试验证
            /** @return array<array{level: string, message: \Stringable|string, context: array<mixed>}> */
            public function getLogs(): array
            {
                return $this->logs;
            }

            /** @return array<array{message: \Stringable|string, context: array<mixed>}> */
            public function getInfoCalls(): array
            {
                return $this->infoCalls;
            }

            /** @return array<array{message: \Stringable|string, context: array<mixed>}> */
            public function getWarningCalls(): array
            {
                return $this->warningCalls;
            }

            /** @return array<array{message: \Stringable|string, context: array<mixed>}> */
            public function getErrorCalls(): array
            {
                return $this->errorCalls;
            }

            public function clear(): void
            {
                $this->logs = [];
                $this->infoCalls = [];
                $this->warningCalls = [];
                $this->errorCalls = [];
            }
        };

        // 直接实例化 subscriber 并注入测试用的依赖
        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass - 使用匿名类实现接口，无法通过容器注入
        $this->subscriber = new DeviceEventSubscriber(
            $this->deviceManager,
            $this->monitorDataRepository,
            $this->logger
        );
    }

    #[Test]
    public function testOnDeviceRegisteredLogsDeviceRegistration(): void
    {
        // 准备测试数据
        $device = FixtureFactory::createAutoJsDevice();
        $ipAddress = '192.168.1.100';
        $deviceInfo = FixtureFactory::createDeviceInfo();

        $event = new DeviceRegisteredEvent(
            $device,
            $ipAddress,
            $deviceInfo
        );

        // 清空之前的调用记录
        if (method_exists($this->logger, 'clear')) {
            $this->logger->clear();
        }
        if (method_exists($this->monitorDataRepository, 'clear')) {
            $this->monitorDataRepository->clear();
        }
        if (method_exists($this->deviceManager, 'clear')) {
            $this->deviceManager->clear();
        }

        // 执行事件处理
        $this->subscriber->onDeviceRegistered($event);

        // 验证日志记录
        $infoCalls = method_exists($this->logger, 'getInfoCalls') ? $this->logger->getInfoCalls() : [];
        $this->assertCount(1, $infoCalls, '应该记录一条info日志');
        $infoCall = $infoCalls[0];
        $this->assertEquals('New device registered', $infoCall['message']);
        $this->assertEquals($device->getDeviceId(), $infoCall['context']['device_id']);
        $this->assertEquals($device->getName(), $infoCall['context']['name']);
        $this->assertEquals($ipAddress, $infoCall['context']['ip_address']);
        $this->assertEquals($deviceInfo, $infoCall['context']['info']);

        // 验证监控数据创建
        $createCalls = method_exists($this->monitorDataRepository, 'getCreateInitialDataCalls') ? $this->monitorDataRepository->getCreateInitialDataCalls() : [];
        $this->assertCount(1, $createCalls, '应该创建监控数据');
        $this->assertSame($device, $createCalls[0]['device']);

        // 验证欢迎指令发送
        $welcomeCalls = method_exists($this->deviceManager, 'getWelcomeInstructionCalls') ? $this->deviceManager->getWelcomeInstructionCalls() : [];
        $this->assertCount(1, $welcomeCalls, '应该发送欢迎指令');
        $this->assertSame($device, $welcomeCalls[0]['device']);
    }

    #[Test]
    public function testOnDeviceRegisteredHandlesMonitorDataCreationError(): void
    {
        // 准备测试数据
        $device = FixtureFactory::createAutoJsDevice();
        $event = new DeviceRegisteredEvent($device, '192.168.1.100', []);

        // 设置监控数据创建失败
        if (method_exists($this->monitorDataRepository, 'setShouldThrow')) {
            $this->monitorDataRepository->setShouldThrow(true, new \Exception('Database error'));
        }

        // 清空之前的调用记录
        if (method_exists($this->logger, 'clear')) {
            $this->logger->clear();
        }
        if (method_exists($this->deviceManager, 'clear')) {
            $this->deviceManager->clear();
        }

        // 执行事件处理
        try {
            $this->subscriber->onDeviceRegistered($event);
        } catch (\Exception $e) {
            // 忽略异常，继续验证日志和调用
        }

        // 验证错误日志
        $errorCalls = method_exists($this->logger, 'getErrorCalls') ? $this->logger->getErrorCalls() : [];
        $this->assertCount(1, $errorCalls, '应该记录一条错误日志');
        $errorCall = $errorCalls[0];
        $this->assertEquals('Failed to create monitor data for device', $errorCall['message']);
        $this->assertEquals($device->getDeviceId(), $errorCall['context']['device_id']);
        $this->assertEquals('Database error', $errorCall['context']['error']);

        // 即使监控数据创建失败，仍应发送欢迎指令
        $welcomeCalls = method_exists($this->deviceManager, 'getWelcomeInstructionCalls') ? $this->deviceManager->getWelcomeInstructionCalls() : [];
        $this->assertCount(1, $welcomeCalls, '应该发送欢迎指令');
    }

    #[Test]
    public function testOnDeviceRegisteredHandlesWelcomeInstructionError(): void
    {
        // 准备测试数据
        $device = FixtureFactory::createAutoJsDevice();
        $event = new DeviceRegisteredEvent($device, '192.168.1.100', []);

        // 设置发送欢迎指令失败
        if (method_exists($this->deviceManager, 'setShouldThrow')) {
            $this->deviceManager->setShouldThrow(true, new \Exception('Network error'));
        }

        // 清空之前的调用记录
        if (method_exists($this->logger, 'clear')) {
            $this->logger->clear();
        }

        // 执行事件处理
        try {
            $this->subscriber->onDeviceRegistered($event);
        } catch (\Exception $e) {
            // 忽略异常，继续验证日志
        }

        // 验证错误日志
        $errorCalls = method_exists($this->logger, 'getErrorCalls') ? $this->logger->getErrorCalls() : [];
        $this->assertCount(1, $errorCalls, '应该记录一条错误日志');
        $errorCall = $errorCalls[0];
        $this->assertEquals('Failed to send welcome instruction', $errorCall['message']);
        $this->assertEquals($device->getDeviceId(), $errorCall['context']['device_id']);
        $this->assertEquals('Network error', $errorCall['context']['error']);
    }

    #[Test]
    public function testOnDeviceStatusChangedHandlesDeviceOnline(): void
    {
        // 准备测试数据
        $device = FixtureFactory::createAutoJsDevice();
        $event = DeviceStatusChangedEvent::online($device);

        // 清空之前的调用记录
        if (method_exists($this->logger, 'clear')) {
            $this->logger->clear();
        }
        if (method_exists($this->monitorDataRepository, 'clear')) {
            $this->monitorDataRepository->clear();
        }
        if (method_exists($this->deviceManager, 'clear')) {
            $this->deviceManager->clear();
        }

        // 执行事件处理
        $this->subscriber->onDeviceStatusChanged($event);

        // 验证日志记录
        $infoCalls = method_exists($this->logger, 'getInfoCalls') ? $this->logger->getInfoCalls() : [];
        $this->assertCount(1, $infoCalls, '应该记录一条info日志');
        $infoCall = $infoCalls[0];
        $this->assertEquals('Device came online', $infoCall['message']);
        $this->assertEquals($device->getDeviceId(), $infoCall['context']['device_id']);
        $this->assertEquals($device->getName(), $infoCall['context']['name']);

        // 验证检查待执行任务
        $pendingTaskCalls = method_exists($this->deviceManager, 'getPendingTaskCalls') ? $this->deviceManager->getPendingTaskCalls() : [];
        $this->assertCount(1, $pendingTaskCalls, '应该检查待执行任务');
        $this->assertSame($device, $pendingTaskCalls[0]['device']);

        // 验证更新监控数据
        $updateCalls = method_exists($this->monitorDataRepository, 'getUpdateStatusChangedTimeCalls') ? $this->monitorDataRepository->getUpdateStatusChangedTimeCalls() : [];
        $this->assertCount(1, $updateCalls, '应该更新监控数据');
        $this->assertSame($device, $updateCalls[0]['device']);
        $this->assertInstanceOf(\DateTime::class, $updateCalls[0]['time']);
    }

    #[Test]
    public function testOnDeviceStatusChangedHandlesDeviceOffline(): void
    {
        // 准备测试数据
        $device = FixtureFactory::createAutoJsDevice();
        $event = DeviceStatusChangedEvent::offline($device);

        // 清空之前的调用记录
        if (method_exists($this->logger, 'clear')) {
            $this->logger->clear();
        }
        if (method_exists($this->monitorDataRepository, 'clear')) {
            $this->monitorDataRepository->clear();
        }
        if (method_exists($this->deviceManager, 'clear')) {
            $this->deviceManager->clear();
        }

        // 执行事件处理
        $this->subscriber->onDeviceStatusChanged($event);

        // 验证日志记录
        $warningCalls = method_exists($this->logger, 'getWarningCalls') ? $this->logger->getWarningCalls() : [];
        $this->assertCount(1, $warningCalls, '应该记录一条warning日志');
        $warningCall = $warningCalls[0];
        $this->assertEquals('Device went offline', $warningCall['message']);
        $this->assertEquals($device->getDeviceId(), $warningCall['context']['device_id']);
        $this->assertEquals($device->getName(), $warningCall['context']['name']);
        $this->assertArrayHasKey('last_heartbeat', $warningCall['context']);

        // 验证取消运行中的任务
        $cancelTaskCalls = method_exists($this->deviceManager, 'getCancelTaskCalls') ? $this->deviceManager->getCancelTaskCalls() : [];
        $this->assertCount(1, $cancelTaskCalls, '应该取消运行中的任务');
        $this->assertSame($device, $cancelTaskCalls[0]['device']);

        // 验证更新监控数据
        $updateCalls = method_exists($this->monitorDataRepository, 'getUpdateStatusChangedTimeCalls') ? $this->monitorDataRepository->getUpdateStatusChangedTimeCalls() : [];
        $this->assertCount(1, $updateCalls, '应该更新监控数据');
        $this->assertSame($device, $updateCalls[0]['device']);
    }

    #[Test]
    public function testOnDeviceStatusChangedHandlesStatusChangeWithSpecificTime(): void
    {
        // 准备测试数据
        $device = FixtureFactory::createAutoJsDevice();
        $statusChangedTime = new \DateTime('2024-01-01 12:00:00');
        $event = new DeviceStatusChangedEvent($device, true, false, $statusChangedTime);

        // 清空之前的调用记录
        if (method_exists($this->monitorDataRepository, 'clear')) {
            $this->monitorDataRepository->clear();
        }

        // 执行事件处理
        $this->subscriber->onDeviceStatusChanged($event);

        // 验证使用事件中的时间更新监控数据
        $updateCalls = method_exists($this->monitorDataRepository, 'getUpdateStatusChangedTimeCalls') ? $this->monitorDataRepository->getUpdateStatusChangedTimeCalls() : [];
        $this->assertCount(1, $updateCalls, '应该更新监控数据');
        $this->assertSame($device, $updateCalls[0]['device']);
        $this->assertEquals($statusChangedTime, $updateCalls[0]['time']);
    }

    #[Test]
    public function testOnDeviceStatusChangedHandlesMonitorDataUpdateError(): void
    {
        // 准备测试数据
        $device = FixtureFactory::createAutoJsDevice();
        $event = DeviceStatusChangedEvent::online($device);

        // 设置监控数据更新失败
        if (method_exists($this->monitorDataRepository, 'setShouldThrow')) {
            $this->monitorDataRepository->setShouldThrow(true, new \Exception('Database error'));
        }

        // 清空之前的调用记录
        if (method_exists($this->logger, 'clear')) {
            $this->logger->clear();
        }
        if (method_exists($this->deviceManager, 'clear')) {
            $this->deviceManager->clear();
        }

        // 执行事件处理
        try {
            $this->subscriber->onDeviceStatusChanged($event);
        } catch (\Exception $e) {
            // 忽略异常，继续验证日志和调用
        }

        // 验证错误日志
        $errorCalls = method_exists($this->logger, 'getErrorCalls') ? $this->logger->getErrorCalls() : [];
        $this->assertCount(1, $errorCalls, '应该记录一条错误日志');
        $errorCall = $errorCalls[0];
        $this->assertEquals('Failed to update monitor data', $errorCall['message']);
        $this->assertEquals($device->getDeviceId(), $errorCall['context']['device_id']);
        $this->assertEquals('Database error', $errorCall['context']['error']);

        // 即使监控数据更新失败，仍应检查待执行任务
        $pendingTaskCalls = method_exists($this->deviceManager, 'getPendingTaskCalls') ? $this->deviceManager->getPendingTaskCalls() : [];
        $this->assertCount(1, $pendingTaskCalls, '应该检查待执行任务');
        $this->assertSame($device, $pendingTaskCalls[0]['device']);
    }

    #[Test]
    public function testOnDeviceStatusChangedHandlesUnknownStatus(): void
    {
        // 准备测试数据
        $device = FixtureFactory::createAutoJsDevice();
        // 创建既不是online也不是offline的事件
        $event = new DeviceStatusChangedEvent($device, false, false);

        // 清空之前的调用记录
        if (method_exists($this->deviceManager, 'clear')) {
            $this->deviceManager->clear();
        }
        if (method_exists($this->monitorDataRepository, 'clear')) {
            $this->monitorDataRepository->clear();
        }

        // 执行事件处理
        $this->subscriber->onDeviceStatusChanged($event);

        // 不应调用checkPendingTasks或cancelRunningTasks
        $pendingTaskCalls = method_exists($this->deviceManager, 'getPendingTaskCalls') ? $this->deviceManager->getPendingTaskCalls() : [];
        $cancelTaskCalls = method_exists($this->deviceManager, 'getCancelTaskCalls') ? $this->deviceManager->getCancelTaskCalls() : [];
        $this->assertCount(0, $pendingTaskCalls, '不应该调用checkPendingTasks');
        $this->assertCount(0, $cancelTaskCalls, '不应该调用cancelRunningTasks');

        // 仍应更新监控数据
        $updateCalls = method_exists($this->monitorDataRepository, 'getUpdateStatusChangedTimeCalls') ? $this->monitorDataRepository->getUpdateStatusChangedTimeCalls() : [];
        $this->assertCount(1, $updateCalls, '应该更新监控数据');
        $this->assertSame($device, $updateCalls[0]['device']);
    }

    #[Test]
    public function testEventListenerPriorityIsCorrect(): void
    {
        // 验证事件监听器优先级
        $reflection = new \ReflectionClass(DeviceEventSubscriber::class);

        // 检查 onDeviceRegistered 方法的属性
        $registerMethod = $reflection->getMethod('onDeviceRegistered');
        $registerAttributes = $registerMethod->getAttributes(AsEventListener::class);
        $this->assertCount(1, $registerAttributes);

        $registerAttribute = $registerAttributes[0]->newInstance();
        $this->assertSame(DeviceRegisteredEvent::class, $registerAttribute->event);
        $this->assertSame(10, $registerAttribute->priority);

        // 检查 onDeviceStatusChanged 方法的属性
        $statusMethod = $reflection->getMethod('onDeviceStatusChanged');
        $statusAttributes = $statusMethod->getAttributes(AsEventListener::class);
        $this->assertCount(1, $statusAttributes);

        $statusAttribute = $statusAttributes[0]->newInstance();
        $this->assertSame(DeviceStatusChangedEvent::class, $statusAttribute->event);
        $this->assertSame(5, $statusAttribute->priority);
    }
}
