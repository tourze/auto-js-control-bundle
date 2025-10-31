<?php

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\AutoJsControlBundle\Service\MonitoringCoordinator;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MonitoringCoordinator::class)]
#[RunTestsInSeparateProcesses]
final class MonitoringCoordinatorTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 初始化集成测试环境
    }

    public function testServiceExists(): void
    {
        $monitoringCoordinator = self::getService(MonitoringCoordinator::class);
        $this->assertInstanceOf(MonitoringCoordinator::class, $monitoringCoordinator);
    }

    #[Test]
    public function testDisplayQueueStatusWithNonExistentDevice(): void
    {
        $deviceCode = 'NON_EXISTENT_DEVICE';
        $limit = 10;
        $io = $this->createMock(SymfonyStyle::class);

        // 期望会调用error方法
        $io->expects($this->once())
            ->method('error')
            ->with(self::stringContains('不存在'))
        ;

        $monitoringCoordinator = self::getService(MonitoringCoordinator::class);

        // Act - 调用方法但不期望异常
        $monitoringCoordinator->displayQueueStatus($deviceCode, $limit, $io);
    }

    #[Test]
    public function testDisplayQueueStatusWithAllDevices(): void
    {
        $limit = 10;
        $io = $this->createMock(SymfonyStyle::class);

        // 期望调用 section 方法（用于显示设备列表或警告信息）
        $io->expects($this->atLeastOnce())
            ->method('section')
        ;

        $monitoringCoordinator = self::getService(MonitoringCoordinator::class);

        // Act - 调用方法但不期望异常
        $monitoringCoordinator->displayQueueStatus(null, $limit, $io);
    }

    #[Test]
    public function testMonitoringCoordinatorHasRequiredDependencies(): void
    {
        $monitoringCoordinator = self::getService(MonitoringCoordinator::class);

        // 通过反射检查服务是否正确注入了依赖
        $reflection = new \ReflectionClass($monitoringCoordinator);

        // MonitoringCoordinator应该有QueueMonitorService和ConsoleDisplayFormatter作为依赖
        $this->assertTrue($reflection->hasProperty('queueService') || $reflection->hasProperty('monitorService'));
        $this->assertTrue($reflection->hasProperty('displayFormatter') || $reflection->hasProperty('formatter'));
    }

    #[Test]
    public function testStartMonitoringInitializesCorrectly(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $output = $this->createMock(OutputInterface::class);

        // 期望调用info方法显示监控开始信息
        $io->expects($this->once())
            ->method('info')
            ->with(self::stringContains('开始实时监控'))
        ;

        // 期望调用comment方法显示退出提示
        $io->expects($this->once())
            ->method('comment')
            ->with('按 Ctrl+C 退出监控')
        ;

        // 期望调用newLine方法
        $io->expects($this->once())
            ->method('newLine')
        ;

        $monitoringCoordinator = self::getService(MonitoringCoordinator::class);

        // 由于startMonitoring是无限循环，我们使用反射来测试初始化部分
        $reflection = new \ReflectionClass($monitoringCoordinator);
        $displayHeaderMethod = $reflection->getMethod('displayMonitoringHeader');
        $displayHeaderMethod->setAccessible(true);

        // 测试显示头部信息
        $displayHeaderMethod->invokeArgs($monitoringCoordinator, [5, $io]);
    }
}
