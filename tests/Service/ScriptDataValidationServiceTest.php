<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Service\ScriptDataValidationService;

/**
 * @internal
 */
#[CoversClass(ScriptDataValidationService::class)]
final class ScriptDataValidationServiceTest extends TestCase
{
    private ScriptDataValidationService $service;

    protected function setUp(): void
    {
        $this->service = new ScriptDataValidationService();
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(ScriptDataValidationService::class, $this->service);
    }

    public function testValidateCreateDataWithValidData(): void
    {
        // Arrange
        $data = [
            'code' => 'TEST_SCRIPT',
            'name' => 'Test Script',
            'scriptType' => 'javascript',
        ];

        // Act & Assert - should not throw exception
        $this->expectNotToPerformAssertions();
        $this->service->validateCreateData($data);
    }

    public function testValidateCreateDataWithMissingCode(): void
    {
        // Arrange
        $data = [
            'name' => 'Test Script',
            'scriptType' => 'javascript',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('字段 code 不能为空');

        $this->service->validateCreateData($data);
    }

    public function testValidateCreateDataWithEmptyCode(): void
    {
        // Arrange
        $data = [
            'code' => '',
            'name' => 'Test Script',
            'scriptType' => 'javascript',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('字段 code 不能为空');

        $this->service->validateCreateData($data);
    }

    public function testValidateCreateDataWithMissingName(): void
    {
        // Arrange
        $data = [
            'code' => 'TEST_SCRIPT',
            'scriptType' => 'javascript',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('字段 name 不能为空');

        $this->service->validateCreateData($data);
    }

    public function testValidateCreateDataWithMissingScriptType(): void
    {
        // Arrange
        $data = [
            'code' => 'TEST_SCRIPT',
            'name' => 'Test Script',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('字段 scriptType 不能为空');

        $this->service->validateCreateData($data);
    }

    public function testParseScriptTypeWithValidType(): void
    {
        // Arrange & Act
        $result = $this->service->parseScriptType('javascript');

        // Assert
        $this->assertEquals(ScriptType::JAVASCRIPT, $result);
    }

    public function testParseScriptTypeWithAutoJs(): void
    {
        // Arrange & Act
        $result = $this->service->parseScriptType('auto_js');

        // Assert
        $this->assertEquals(ScriptType::AUTO_JS, $result);
    }

    public function testParseScriptTypeWithInvalidType(): void
    {
        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('无效的脚本类型');

        $this->service->parseScriptType('invalid_type');
    }

    public function testValidateScriptContentForJavaScript(): void
    {
        // Arrange
        $script = new Script();
        $script->setScriptType(ScriptType::JAVASCRIPT);
        $data = ['content' => 'console.log("test");'];

        // Act & Assert - should not throw exception
        $this->expectNotToPerformAssertions();
        $this->service->validateScriptContent($script, $data);
    }

    public function testValidateScriptContentForJavaScriptWithMissingContent(): void
    {
        // Arrange
        $script = new Script();
        $script->setScriptType(ScriptType::JAVASCRIPT);
        $data = [];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('JavaScript脚本必须提供内容');

        $this->service->validateScriptContent($script, $data);
    }

    public function testValidateScriptContentForAutoJs(): void
    {
        // Arrange
        $script = new Script();
        $script->setScriptType(ScriptType::AUTO_JS);
        $data = ['projectPath' => '/path/to/project'];

        // Act & Assert - should not throw exception
        $this->expectNotToPerformAssertions();
        $this->service->validateScriptContent($script, $data);
    }

    public function testValidateScriptContentForAutoJsWithMissingProjectPath(): void
    {
        // Arrange
        $script = new Script();
        $script->setScriptType(ScriptType::AUTO_JS);
        $data = [];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Auto.js脚本必须提供项目路径');

        $this->service->validateScriptContent($script, $data);
    }

    public function testValidateParametersDefinitionWithValidParameters(): void
    {
        // Arrange
        $parameters = [
            'param1' => [
                'type' => 'string',
                'required' => true,
            ],
            'param2' => [
                'type' => 'number',
                'required' => false,
            ],
        ];

        // Act & Assert - should not throw exception
        $this->expectNotToPerformAssertions();
        $this->service->validateParametersDefinition($parameters);
    }

    public function testValidateParametersDefinitionWithInvalidParameterType(): void
    {
        // Arrange
        $parameters = [
            'param1' => 'invalid_definition',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('参数 param1 的定义必须是对象');

        $this->service->validateParametersDefinition($parameters);
    }

    public function testValidateParametersDefinitionWithMissingType(): void
    {
        // Arrange
        $parameters = [
            'param1' => [
                'required' => true,
            ],
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('参数 param1 必须指定类型');

        $this->service->validateParametersDefinition($parameters);
    }

    public function testValidateParametersDefinitionWithInvalidTypeValue(): void
    {
        // Arrange
        $parameters = [
            'param1' => [
                'type' => 'invalid_type',
            ],
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('参数 param1 的类型无效');

        $this->service->validateParametersDefinition($parameters);
    }

    public function testValidateParametersDefinitionWithInvalidRequiredValue(): void
    {
        // Arrange
        $parameters = [
            'param1' => [
                'type' => 'string',
                'required' => 'yes',
            ],
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('参数 param1 的 required 字段必须是布尔值');

        $this->service->validateParametersDefinition($parameters);
    }

    public function testValidateScriptSyntaxWithValidContent(): void
    {
        // Arrange
        $content = 'function test() { return "hello"; }';

        // Act
        $result = $this->service->validateScriptSyntax($content);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testValidateScriptSyntaxWithDangerousFunction(): void
    {
        // Arrange
        $content = 'eval("console.log(\"test\")");';

        // Act
        $result = $this->service->validateScriptSyntax($content);

        // Assert
        $this->assertIsArray($result);
        $this->assertContains('检测到潜在危险函数: eval', $result);
    }

    public function testValidateScriptSyntaxWithMultipleDangerousFunctions(): void
    {
        // Arrange
        $content = 'eval("test"); setTimeout(() => {}, 1000);';

        // Act
        $result = $this->service->validateScriptSyntax($content);

        // Assert
        $this->assertIsArray($result);
        $this->assertContains('检测到潜在危险函数: eval', $result);
        $this->assertContains('检测到潜在危险函数: setTimeout', $result);
    }

    public function testValidateScriptSyntaxWithUnmatchedBraces(): void
    {
        // Arrange
        $content = 'function test() { console.log("test");';

        // Act
        $result = $this->service->validateScriptSyntax($content);

        // Assert
        $this->assertIsArray($result);
        $this->assertContains('大括号不匹配', $result);
    }

    public function testValidateScriptSyntaxWithUnmatchedParentheses(): void
    {
        // Arrange
        $content = 'function test( { console.log("test"); }';

        // Act
        $result = $this->service->validateScriptSyntax($content);

        // Assert
        $this->assertIsArray($result);
        $this->assertContains('圆括号不匹配', $result);
    }

    public function testValidateScriptSyntaxWithMultipleErrors(): void
    {
        // Arrange
        $content = 'eval("test"); function test( { console.log("test");';

        // Act
        $result = $this->service->validateScriptSyntax($content);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContains('检测到潜在危险函数: eval', $result);
        $this->assertContains('大括号不匹配', $result);
        $this->assertContains('圆括号不匹配', $result);
    }
}
