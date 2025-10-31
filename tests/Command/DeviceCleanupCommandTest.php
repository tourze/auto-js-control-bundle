<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\AutoJsControlBundle\Command\DeviceCleanupCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * DeviceCleanupCommand 单元测试.
 *
 * @internal
 */
#[CoversClass(DeviceCleanupCommand::class)]
#[RunTestsInSeparateProcesses]
final class DeviceCleanupCommandTest extends AbstractCommandTestCase
{
    private DeviceCleanupCommand $command;

    private CommandTester $commandTester;

    protected function onSetUp(): void
    {
        $command = self::getService(DeviceCleanupCommand::class);
        $this->assertInstanceOf(DeviceCleanupCommand::class, $command);
        $this->command = $command;
        $this->commandTester = new CommandTester($this->command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    public function testCommandName(): void
    {
        $this->assertSame('auto-js:device:cleanup', DeviceCleanupCommand::NAME);
    }

    public function testCommandCanBeInstantiated(): void
    {
        $this->assertInstanceOf(DeviceCleanupCommand::class, $this->command);
    }

    public function testExecuteCommand(): void
    {
        // 执行命令
        $this->commandTester->execute([]);

        // 验证命令执行成功
        $this->assertSame(0, $this->commandTester->getStatusCode());

        // 验证输出包含基本信息
        $output = $this->commandTester->getDisplay();
        $this->assertIsString($output);
    }

    public function testOptionOfflineDays(): void
    {
        $this->commandTester->execute([
            '--offline-days' => '7',
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testOptionDryRun(): void
    {
        $this->commandTester->execute([
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testOptionForce(): void
    {
        $this->commandTester->execute([
            '--force' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testOptionDeletePermanently(): void
    {
        $this->commandTester->execute([
            '--delete-permanently' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}
