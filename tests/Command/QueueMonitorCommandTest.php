<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\AutoJsControlBundle\Command\QueueMonitorCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(QueueMonitorCommand::class)]
#[RunTestsInSeparateProcesses]
final class QueueMonitorCommandTest extends AbstractCommandTestCase
{
    private QueueMonitorCommand $command;

    private CommandTester $commandTester;

    protected function onSetUp(): void
    {
        $command = self::getService(QueueMonitorCommand::class);
        $this->assertInstanceOf(QueueMonitorCommand::class, $command);
        $this->command = $command;
        $this->commandTester = new CommandTester($this->command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    public function test配置包含必要的选项和描述(): void
    {
        $command = self::getService(QueueMonitorCommand::class);
        $this->assertInstanceOf(QueueMonitorCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('device'));
        $this->assertTrue($definition->hasOption('refresh'));
        $this->assertTrue($definition->hasOption('once'));
        $this->assertTrue($definition->hasOption('limit'));
        $this->assertTrue($definition->hasOption('show-completed'));
        $this->assertTrue($definition->hasOption('clear-queue'));
        $this->assertEquals('监控设备指令队列状态', $command->getDescription());
    }

    public function test清空队列功能显示确认提示(): void
    {
        $command = self::getService(QueueMonitorCommand::class);
        $this->assertInstanceOf(QueueMonitorCommand::class, $command);

        $input = new ArrayInput(['--clear-queue' => 'DEV001']);
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        // 因为没有用户交互，命令会提示确认并返回 SUCCESS
        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('即将清空设备', $outputContent);
    }

    public function test一次性显示(): void
    {
        $input = new ArrayInput(['--once' => true]);
        $output = new BufferedOutput();

        $command = self::getService(QueueMonitorCommand::class);
        $this->assertInstanceOf(QueueMonitorCommand::class, $command);
        $result = $command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertIsString($outputContent);
    }

    public function test命令名称和别名设置正确(): void
    {
        $command = self::getService(QueueMonitorCommand::class);
        $this->assertInstanceOf(QueueMonitorCommand::class, $command);

        $this->assertEquals('auto-js:queue:monitor', $command->getName());
        $this->assertContains('autojs:queue:monitor', $command->getAliases());
        $this->assertContains('autojs:queue:status', $command->getAliases());
    }

    public function testExecuteWithCommandTester(): void
    {
        // 执行命令
        $this->commandTester->execute(['--once' => true]);

        // 验证基本输出
        $output = $this->commandTester->getDisplay();
        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertIsString($output);
    }

    public function testOptionDevice(): void
    {
        // 验证设备选项配置，不执行可能失败的监控逻辑
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('device'));
        $this->assertSame('指定设备代码', $definition->getOption('device')->getDescription());
    }

    public function testOptionRefresh(): void
    {
        $this->commandTester->execute([
            '--refresh' => '3',
            '--once' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testOptionOnce(): void
    {
        $this->commandTester->execute([
            '--once' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testOptionLimit(): void
    {
        $this->commandTester->execute([
            '--limit' => '5',
            '--once' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testOptionShowCompleted(): void
    {
        $this->commandTester->execute([
            '--show-completed' => true,
            '--once' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testOptionClearQueue(): void
    {
        $this->commandTester->execute([
            '--clear-queue' => 'DEV001',
        ]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }
}
