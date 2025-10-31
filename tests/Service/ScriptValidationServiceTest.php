<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Exception\ScriptValidationException;
use Tourze\AutoJsControlBundle\Service\ScriptValidationService;

/**
 * @internal
 */
#[CoversClass(ScriptValidationService::class)]
final class ScriptValidationServiceTest extends TestCase
{
    private ScriptValidationService $scriptValidationService;

    protected function setUp(): void
    {
        $this->scriptValidationService = new ScriptValidationService();
    }

    #[Test]
    public function testValidateWithValidScriptReturnsTrue(): void
    {
        // Arrange
        $validScript = 'log("Hello World");';

        // Act
        $result = $this->scriptValidationService->validate($validScript);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function testValidateWithEmptyScriptThrowsException(): void
    {
        // Arrange
        $emptyScript = '';

        // Act & Assert
        $this->expectException(ScriptValidationException::class);
        $this->expectExceptionMessage('脚本内容不能为空');

        $this->scriptValidationService->validate($emptyScript);
    }

    #[Test]
    public function testValidateWithWhitespaceOnlyScriptThrowsException(): void
    {
        // Arrange
        $whitespaceScript = '   
        
        ';

        // Act & Assert
        $this->expectException(ScriptValidationException::class);
        $this->expectExceptionMessage('脚本内容不能为空');

        $this->scriptValidationService->validate($whitespaceScript);
    }

    #[Test]
    public function testValidateWithDangerousFunctionThrowsException(): void
    {
        // Arrange
        $dangerousScript = 'shell("rm -rf /");';

        // Act & Assert
        $this->expectException(ScriptValidationException::class);
        $this->expectExceptionMessage('脚本包含危险函数');

        $this->scriptValidationService->validate($dangerousScript);
    }

    #[Test]
    public function testValidateWithInvalidSyntaxThrowsException(): void
    {
        // Arrange
        $invalidScript = 'log("Hello World"';

        // Act & Assert
        $this->expectException(ScriptValidationException::class);
        $this->expectExceptionMessage('脚本语法错误');

        $this->scriptValidationService->validate($invalidScript);
    }

    #[Test]
    public function testValidateWithExcessiveLoopsThrowsException(): void
    {
        // Arrange
        $loopyScript = str_repeat('for(let i=0; i<1000000; i++){', 100) . 'log(i);' . str_repeat('}', 100);

        // Act & Assert
        $this->expectException(ScriptValidationException::class);
        $this->expectExceptionMessage('脚本复杂度过高');

        $this->scriptValidationService->validate($loopyScript);
    }

    #[Test]
    public function testValidateWithAutoJsFunctionsReturnsTrue(): void
    {
        // Arrange
        $autoJsScript = '
            auto.waitFor();
            click(100, 200);
            sleep(1000);
            log("Script executed");
        ';

        // Act
        $result = $this->scriptValidationService->validate($autoJsScript);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function testValidateWithComplexValidScriptReturnsTrue(): void
    {
        // Arrange
        $complexScript = '
            function findElement(selector) {
                return selector("button").findOne();
            }
            
            let element = findElement(text("确定"));
            if (element) {
                element.click();
                sleep(1000);
                log("Element clicked successfully");
            } else {
                log("Element not found");
            }
        ';

        // Act
        $result = $this->scriptValidationService->validate($complexScript);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function testValidateScriptSizeWithOversizedScriptThrowsException(): void
    {
        // Arrange
        $oversizedScript = str_repeat('log("This is a very long script"); ', 10000);

        // Act & Assert
        $this->expectException(ScriptValidationException::class);
        $this->expectExceptionMessage('脚本文件过大');

        $this->scriptValidationService->validate($oversizedScript);
    }

    #[Test]
    public function testValidateWithProhibitedKeywordsThrowsException(): void
    {
        // Arrange
        $maliciousScript = 'eval("malicious code");';

        // Act & Assert
        $this->expectException(ScriptValidationException::class);
        $this->expectExceptionMessage('脚本包含禁用关键字');

        $this->scriptValidationService->validate($maliciousScript);
    }

    #[Test]
    public function testCheckCodeStyleWithJavaScriptReturnsStyleIssues(): void
    {
        // Arrange
        $scriptContent = "function	TestFunction() {\n\tvar test = 'hello';\n}";
        $type = 'javascript';

        // Act
        $issues = $this->scriptValidationService->checkCodeStyle($scriptContent, $type);

        // Assert
        $this->assertIsArray($issues);
        $this->assertContains('建议使用空格而不是制表符进行缩进', $issues);
        $this->assertContains('普通函数名应该使用小驼峰命名法', $issues);
    }

    #[Test]
    public function testCheckCodeStyleWithLongLinesReturnsLineLengthIssues(): void
    {
        // Arrange
        $longLine = str_repeat('a', 130);
        $scriptContent = "log('{$longLine}');";
        $type = 'javascript';

        // Act
        $issues = $this->scriptValidationService->checkCodeStyle($scriptContent, $type);

        // Assert
        $this->assertIsArray($issues);
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('第 1 行超过120个字符，建议分行', $issues[0]);
    }

    #[Test]
    public function testCheckCodeStyleWithExcessiveEmptyLinesReturnsIssue(): void
    {
        // Arrange
        $scriptContent = "log('test');\n\n\n\nlog('another');";
        $type = 'javascript';

        // Act
        $issues = $this->scriptValidationService->checkCodeStyle($scriptContent, $type);

        // Assert
        $this->assertIsArray($issues);
        $this->assertContains('检测到多余的空行（超过2个连续空行）', $issues);
    }

    #[Test]
    public function testCheckCodeStyleWithUnknownTypeReturnsEmptyArray(): void
    {
        // Arrange
        $scriptContent = 'some code';
        $type = 'unknown';

        // Act
        $issues = $this->scriptValidationService->checkCodeStyle($scriptContent, $type);

        // Assert
        $this->assertIsArray($issues);
        $this->assertEmpty($issues);
    }

    #[Test]
    public function testCheckPerformanceWithJavaScriptReturnsSuggestions(): void
    {
        // Arrange
        $scriptContent = "
            for(let i = 0; i < 100; i++) {
                document.getElementById('test');
            }
            let str = String('');
            str += 'a'; str += 'b'; str += 'c'; str += 'd'; str += 'e'; str += 'f';
        ";
        $type = 'javascript';

        // Act
        $suggestions = $this->scriptValidationService->checkPerformance($scriptContent, $type);

        // Assert
        $this->assertIsArray($suggestions);
        $this->assertContains('在循环中进行 DOM 查询可能影响性能，建议在循环外缓存元素', $suggestions);
        $this->assertContains('大量字符串拼接建议使用数组 join 方法', $suggestions);
    }

    #[Test]
    public function testCheckPerformanceWithAutoJsAndLongSleepReturnsSuggestions(): void
    {
        // Arrange
        $scriptContent = "sleep(10000); log('test');";
        $type = 'auto_js';

        // Act
        $suggestions = $this->scriptValidationService->checkPerformance($scriptContent, $type);

        // Assert
        $this->assertIsArray($suggestions);
        $this->assertContains('检测到长时间 sleep，可能影响脚本响应性', $suggestions);
    }

    #[Test]
    public function testCheckPerformanceWithUnknownTypeReturnsEmptyArray(): void
    {
        // Arrange
        $scriptContent = 'some code';
        $type = 'unknown';

        // Act
        $suggestions = $this->scriptValidationService->checkPerformance($scriptContent, $type);

        // Assert
        $this->assertIsArray($suggestions);
        $this->assertEmpty($suggestions);
    }

    #[Test]
    public function testPerformStrictValidationWithJavaScriptReturnsWarnings(): void
    {
        // Arrange
        $scriptContent = "
            myVar = 'test';
            if (myVar == 'test') {
                log('match')
            }
        ";
        $type = 'javascript';

        // Act
        $warnings = $this->scriptValidationService->performStrictValidation($scriptContent, $type);

        // Assert
        $this->assertIsArray($warnings);
        $this->assertContains('检测到可能未声明的变量', $warnings);
        $this->assertContains('建议使用 === 代替 == 进行严格比较', $warnings);
        $this->assertContains('语句末尾缺少分号', $warnings);
    }

    #[Test]
    public function testPerformStrictValidationWithShellReturnsWarnings(): void
    {
        // Arrange
        $scriptContent = 'echo $variable without quotes';
        $type = 'shell';

        // Act
        $warnings = $this->scriptValidationService->performStrictValidation($scriptContent, $type);

        // Assert
        $this->assertIsArray($warnings);
        $this->assertContains('检测到未引用的变量，建议使用 "${var}" 格式', $warnings);
    }

    #[Test]
    public function testPerformStrictValidationWithUnknownTypeReturnsEmptyArray(): void
    {
        // Arrange
        $scriptContent = 'some code';
        $type = 'unknown';

        // Act
        $warnings = $this->scriptValidationService->performStrictValidation($scriptContent, $type);

        // Assert
        $this->assertIsArray($warnings);
        $this->assertEmpty($warnings);
    }
}
