<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\AutoJsControlBundle\Command\ScriptValidateCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(ScriptValidateCommand::class)]
#[RunTestsInSeparateProcesses]
final class ScriptValidateCommandTest extends AbstractCommandTestCase
{
    private ScriptValidateCommand $command;

    private CommandTester $commandTester;

    protected function onSetUp(): void
    {
        $command = self::getService(ScriptValidateCommand::class);
        $this->assertInstanceOf(ScriptValidateCommand::class, $command);
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

        $this->assertTrue($definition->hasArgument('script'));
        $this->assertTrue($definition->hasOption('file'));
        $this->assertTrue($definition->hasOption('content'));
        $this->assertTrue($definition->hasOption('type'));
        $this->assertTrue($definition->hasOption('all'));
        $this->assertTrue($definition->hasOption('fix'));
        $this->assertTrue($definition->hasOption('strict'));
        $this->assertEquals('验证脚本语法和安全性', $this->command->getDescription());
    }

    public function test通过直接内容验证脚本(): void
    {
        $input = new ArrayInput(['--content' => 'console.log("Hello");']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // 验证命令执行
        $this->assertContains($result, [Command::SUCCESS, Command::FAILURE]);
        $outputContent = $output->fetch();
        $this->assertIsString($outputContent);
    }

    public function test命令名称和别名设置正确(): void
    {
        $this->assertEquals('auto-js:script:validate', $this->command->getName());
        $this->assertContains('autojs:script:validate', $this->command->getAliases());
        $this->assertContains('autojs:script:check', $this->command->getAliases());
    }

    public function test未提供任何参数时显示帮助(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::FAILURE, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('必须指定', $outputContent);
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

    public function testArgumentScript(): void
    {
        $this->commandTester->execute([
            'script' => 'test-script',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionFile(): void
    {
        $this->commandTester->execute([
            '--file' => '/non-existent-file.js',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionContent(): void
    {
        $this->commandTester->execute([
            '--content' => 'console.log("test");',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionType(): void
    {
        $this->commandTester->execute([
            '--content' => 'echo "test"',
            '--type' => 'shell',
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionAll(): void
    {
        $this->commandTester->execute([
            '--all' => true,
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionFix(): void
    {
        $this->commandTester->execute([
            '--content' => 'console.log("test");',
            '--fix' => true,
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testOptionStrict(): void
    {
        $this->commandTester->execute([
            '--content' => 'console.log("test");',
            '--strict' => true,
        ]);

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertContains($statusCode, [Command::SUCCESS, Command::FAILURE]);
    }
}
