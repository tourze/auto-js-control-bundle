<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\AutoJsControlBundle\Command\DeviceListCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceListCommand::class)]
#[RunTestsInSeparateProcesses]
final class DeviceListCommandTest extends AbstractCommandTestCase
{
    private CommandTester $commandTester;

    protected function onSetUp(): void
    {
        $command = self::getService(DeviceListCommand::class);
        $this->assertInstanceOf(DeviceListCommand::class, $command);
        $this->commandTester = new CommandTester($command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    #[Test]
    public function executeWithNoDevices(): void
    {
        // 执行命令
        $exitCode = $this->commandTester->execute([]);

        // 验证命令执行成功
        $this->assertSame(Command::SUCCESS, $exitCode);

        // 验证输出包含基本信息
        $output = $this->commandTester->getDisplay();
        $this->assertIsString($output);
    }

    #[Test]
    public function executeWithJsonFormat(): void
    {
        // 执行命令
        $exitCode = $this->commandTester->execute(['--format' => 'json']);

        // 验证命令执行成功
        $this->assertSame(Command::SUCCESS, $exitCode);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertIsString($output);
    }

    #[Test]
    public function executeWithCsvFormat(): void
    {
        // 执行命令
        $exitCode = $this->commandTester->execute(['--format' => 'csv']);

        // 验证命令执行成功
        $this->assertSame(Command::SUCCESS, $exitCode);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertIsString($output);
    }

    #[Test]
    public function executeWithConflictingOptions(): void
    {
        // 执行命令
        $exitCode = $this->commandTester->execute([
            '--online-only' => true,
            '--offline-only' => true,
        ]);

        // 验证输出
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('不能同时使用', $output);
    }

    #[Test]
    public function executeWithLimit(): void
    {
        // 执行命令
        $exitCode = $this->commandTester->execute(['--limit' => '10']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOptionOnlineOnly(): void
    {
        $exitCode = $this->commandTester->execute([
            '--online-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOptionOfflineOnly(): void
    {
        $exitCode = $this->commandTester->execute([
            '--offline-only' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOptionFormat(): void
    {
        $exitCode = $this->commandTester->execute([
            '--format' => 'table',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOptionLimit(): void
    {
        $exitCode = $this->commandTester->execute([
            '--limit' => '5',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOptionSortBy(): void
    {
        $exitCode = $this->commandTester->execute([
            '--sort-by' => 'id',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOptionOrder(): void
    {
        $exitCode = $this->commandTester->execute([
            '--order' => 'asc',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function commandHasCorrectConfiguration(): void
    {
        $command = self::getService(DeviceListCommand::class);
        $this->assertInstanceOf(DeviceListCommand::class, $command);

        $this->assertSame(DeviceListCommand::NAME, $command->getName());
        $this->assertSame('auto-js:device:list', $command->getName());
        $this->assertContains('autojs:device:list', $command->getAliases());
        $this->assertSame('列出所有Auto.js设备及其状态信息', $command->getDescription());

        // 验证选项
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('online-only'));
        $this->assertTrue($definition->hasOption('offline-only'));
        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('limit'));
        $this->assertTrue($definition->hasOption('sort-by'));
        $this->assertTrue($definition->hasOption('order'));
    }
}
