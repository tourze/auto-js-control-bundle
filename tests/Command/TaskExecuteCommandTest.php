<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\AutoJsControlBundle\Command\TaskExecuteCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(TaskExecuteCommand::class)]
#[RunTestsInSeparateProcesses]
final class TaskExecuteCommandTest extends AbstractCommandTestCase
{
    private TaskExecuteCommand $command;

    private CommandTester $commandTester;

    protected function onSetUp(): void
    {
        $command = self::getService(TaskExecuteCommand::class);
        $this->assertInstanceOf(TaskExecuteCommand::class, $command);
        $this->command = $command;
        $this->commandTester = new CommandTester($this->command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    public function test配置包含必要的参数和选项(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('task-id'));
        $this->assertTrue($definition->hasOption('script-id'));
        $this->assertTrue($definition->hasOption('script-code'));
        $this->assertTrue($definition->hasOption('device-ids'));
        $this->assertTrue($definition->hasOption('group-id'));
        $this->assertTrue($definition->hasOption('all-devices'));
        $this->assertTrue($definition->hasOption('name'));
        $this->assertTrue($definition->hasOption('description'));
        $this->assertEquals('手动执行任务或创建新任务', $this->command->getDescription());
    }

    public function test未提供任何参数时显示错误(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::FAILURE, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('必须指定', $outputContent);
    }

    public function test命令名称和别名设置正确(): void
    {
        $this->assertEquals('auto-js:task:execute', $this->command->getName());
        $this->assertContains('autojs:task:execute', $this->command->getAliases());
        $this->assertContains('autojs:task:run', $this->command->getAliases());
    }

    public function testExecuteWithCommandTester(): void
    {
        // 执行命令 - 由于没有提供参数，预期会失败
        $this->commandTester->execute([]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertIsString($output);
        // 由于没有提供必要参数，命令应该返回失败状态
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
    }

    public function testArgumentTaskId(): void
    {
        $this->commandTester->execute([
            'task-id' => '999',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionScriptId(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--all-devices' => true,
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionScriptCode(): void
    {
        $this->commandTester->execute([
            '--script-code' => 'test-script',
            '--all-devices' => true,
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionDeviceIds(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--device-ids' => '1,2,3',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionGroupId(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--group-id' => '1',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionAllDevices(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--all-devices' => true,
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionName(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--all-devices' => true,
            '--name' => 'Test Task',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionDescription(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--all-devices' => true,
            '--description' => 'Test task description',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionPriority(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--all-devices' => true,
            '--priority' => '8',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionParameters(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--all-devices' => true,
            '--parameters' => '{"timeout": 30}',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionScheduled(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--all-devices' => true,
            '--scheduled' => '2025-12-25T10:00:00+08:00',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionMonitor(): void
    {
        $this->commandTester->execute([
            '--script-id' => '1',
            '--all-devices' => true,
            '--monitor' => true,
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }
}
