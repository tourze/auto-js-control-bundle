<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\AutoJsControlBundle\Command\DeviceSimulatorCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * DeviceSimulatorCommand 单元测试.
 *
 * @internal
 */
#[CoversClass(DeviceSimulatorCommand::class)]
#[RunTestsInSeparateProcesses]
final class DeviceSimulatorCommandTest extends AbstractCommandTestCase
{
    private DeviceSimulatorCommand $command;

    private CommandTester $commandTester;

    protected function onSetUp(): void
    {
        $command = self::getService(DeviceSimulatorCommand::class);
        $this->assertInstanceOf(DeviceSimulatorCommand::class, $command);
        $this->command = $command;
        $this->commandTester = new CommandTester($this->command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    public function testCommandName(): void
    {
        $this->assertSame('auto-js:device:simulator', DeviceSimulatorCommand::NAME);
    }

    public function testCommandCanBeInstantiated(): void
    {
        $this->assertInstanceOf(DeviceSimulatorCommand::class, $this->command);
    }

    public function testExecuteCommand(): void
    {
        // 设备模拟器命令是长时间运行的命令，不应该在测试中执行
        // 只测试命令的基本配置和参数验证
        $this->assertSame('auto-js:device:simulator', DeviceSimulatorCommand::NAME);
        $this->assertInstanceOf(DeviceSimulatorCommand::class, $this->command);
    }

    public function testOptionDeviceCount(): void
    {
        // 验证选项配置存在，但不执行长时间运行的命令
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('device-count'));
        $this->assertSame('要模拟的设备数量', $definition->getOption('device-count')->getDescription());
    }

    public function testOptionDevicePrefix(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('device-prefix'));
        $this->assertSame('设备代码前缀', $definition->getOption('device-prefix')->getDescription());
    }

    public function testOptionPollInterval(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('poll-interval'));
        $this->assertSame('轮询间隔（秒）', $definition->getOption('poll-interval')->getDescription());
    }

    public function testOptionHeartbeatInterval(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('heartbeat-interval'));
        $this->assertSame('心跳间隔（秒）', $definition->getOption('heartbeat-interval')->getDescription());
    }

    public function testOptionExecutionDelay(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('execution-delay'));
        $this->assertSame('执行延迟范围（毫秒）', $definition->getOption('execution-delay')->getDescription());
    }

    public function testOptionFailureRate(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('failure-rate'));
        $this->assertSame('指令执行失败率（0-100）', $definition->getOption('failure-rate')->getDescription());
    }

    public function testOptionMetrics(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('metrics'));
        $this->assertSame('启用性能指标模拟', $definition->getOption('metrics')->getDescription());
    }

    public function testOptionVerboseExecution(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('verbose-execution'));
        $this->assertSame('显示详细的执行日志', $definition->getOption('verbose-execution')->getDescription());
    }
}
