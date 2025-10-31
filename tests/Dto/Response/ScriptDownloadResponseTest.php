<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Response\ScriptDownloadResponse;

/**
 * @internal
 */
#[CoversClass(ScriptDownloadResponse::class)]
final class ScriptDownloadResponseTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $serverTime = new \DateTimeImmutable('2024-01-01 12:00:00');
        $parameters = ['param1' => 'value1', 'param2' => 'value2'];

        // Act
        $response = new ScriptDownloadResponse(
            status: 'ok',
            scriptId: 123,
            scriptCode: 'SCRIPT_001',
            scriptName: 'test_script.js',
            scriptType: 'javascript',
            content: 'console.log("Hello World");',
            version: '1.0.0',
            parameters: $parameters,
            timeout: 300,
            checksum: 'sha256_hash_12345',
            message: 'Script ready for download',
            serverTime: $serverTime
        );

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertEquals('Script ready for download', $response->getMessage());
        $this->assertEquals(123, $response->getScriptId());
        $this->assertEquals('SCRIPT_001', $response->getScriptCode());
        $this->assertEquals('test_script.js', $response->getScriptName());
        $this->assertEquals('javascript', $response->getScriptType());
        $this->assertEquals('console.log("Hello World");', $response->getContent());
        $this->assertEquals('1.0.0', $response->getVersion());
        $this->assertEquals($parameters, $response->getParameters());
        $this->assertEquals(300, $response->getTimeout());
        $this->assertEquals('sha256_hash_12345', $response->getChecksum());
        $this->assertEquals($serverTime, $response->getServerTime());
    }

    #[Test]
    public function constructorWithMinimalParameters(): void
    {
        // Arrange & Act
        $response = new ScriptDownloadResponse(
            status: 'error',
            message: 'Script not found'
        );

        // Assert
        $this->assertEquals('error', $response->getStatus());
        $this->assertEquals('Script not found', $response->getMessage());
        $this->assertNull($response->getScriptId());
        $this->assertNull($response->getScriptCode());
        $this->assertNull($response->getScriptName());
        $this->assertNull($response->getScriptType());
        $this->assertNull($response->getContent());
        $this->assertNull($response->getVersion());
        $this->assertNull($response->getParameters());
        $this->assertNull($response->getTimeout());
        $this->assertNull($response->getChecksum());
        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getServerTime());
    }

    #[Test]
    public function createSuccessResponse(): void
    {
        // Arrange
        $scriptContent = 'function test() { return true; }';
        $parameters = ['debug' => true, 'retries' => 3];

        // Act
        $response = ScriptDownloadResponse::success(
            scriptId: 456,
            scriptCode: 'SCRIPT_456',
            scriptName: 'my_script.js',
            scriptType: 'javascript',
            content: $scriptContent,
            version: '2.0.0',
            parameters: $parameters,
            timeout: 600
        );

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertNull($response->getMessage());
        $this->assertEquals(456, $response->getScriptId());
        $this->assertEquals('SCRIPT_456', $response->getScriptCode());
        $this->assertEquals('my_script.js', $response->getScriptName());
        $this->assertEquals('javascript', $response->getScriptType());
        $this->assertEquals($scriptContent, $response->getContent());
        $this->assertEquals('2.0.0', $response->getVersion());
        $this->assertEquals($parameters, $response->getParameters());
        $this->assertEquals(600, $response->getTimeout());
        $this->assertEquals(hash('sha256', $scriptContent), $response->getChecksum());
    }

    #[Test]
    public function createNotFoundResponse(): void
    {
        // Act
        $response = ScriptDownloadResponse::notFound(789);

        // Assert
        $this->assertEquals('not_found', $response->getStatus());
        $this->assertEquals('脚本不存在', $response->getMessage());
        $this->assertEquals(789, $response->getScriptId());
        $this->assertNull($response->getContent());
    }

    #[Test]
    public function createForbiddenResponse(): void
    {
        // Act
        $response = ScriptDownloadResponse::forbidden(999);

        // Assert
        $this->assertEquals('forbidden', $response->getStatus());
        $this->assertEquals('无权访问该脚本', $response->getMessage());
        $this->assertEquals(999, $response->getScriptId());
        $this->assertNull($response->getContent());
    }

    #[Test]
    public function createErrorResponse(): void
    {
        // Act
        $response = ScriptDownloadResponse::error('数据库连接失败');

        // Assert
        $this->assertEquals('error', $response->getStatus());
        $this->assertEquals('数据库连接失败', $response->getMessage());
        $this->assertNull($response->getScriptId());
        $this->assertNull($response->getContent());
    }

    #[Test]
    public function jsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $serverTime = new \DateTimeImmutable('2024-01-01 14:00:00');
        $content = 'var x = 1;';
        $response = new ScriptDownloadResponse(
            status: 'ok',
            scriptId: 789,
            scriptCode: 'SCRIPT_789',
            scriptName: 'download.js',
            scriptType: 'javascript',
            content: $content,
            version: '3.0.0',
            parameters: ['async' => true],
            timeout: 1800,
            checksum: 'checksum_value',
            serverTime: $serverTime
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals(789, $decoded['scriptId']);
        $this->assertEquals('SCRIPT_789', $decoded['scriptCode']);
        $this->assertEquals('download.js', $decoded['scriptName']);
        $this->assertEquals('javascript', $decoded['scriptType']);
        $this->assertEquals($content, $decoded['content']);
        $this->assertEquals(strlen($content), $decoded['contentSize']);
        $this->assertEquals('3.0.0', $decoded['version']);
        $this->assertEquals(['async' => true], $decoded['parameters']);
        $this->assertEquals(1800, $decoded['timeout']);
        $this->assertEquals('checksum_value', $decoded['checksum']);
        $this->assertEquals($serverTime->format(\DateTimeInterface::RFC3339), $decoded['serverTime']);
    }

    #[Test]
    public function jsonSerializeWithMinimalData(): void
    {
        // Arrange
        $response = new ScriptDownloadResponse(
            status: 'error',
            message: 'Error occurred'
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('error', $decoded['status']);
        $this->assertEquals('Error occurred', $decoded['message']);
        $this->assertArrayNotHasKey('scriptId', $decoded);
        $this->assertArrayNotHasKey('scriptCode', $decoded);
        $this->assertArrayNotHasKey('scriptName', $decoded);
        $this->assertArrayNotHasKey('scriptType', $decoded);
        $this->assertArrayNotHasKey('content', $decoded);
        $this->assertArrayNotHasKey('contentSize', $decoded);
        $this->assertArrayNotHasKey('version', $decoded);
        $this->assertArrayNotHasKey('parameters', $decoded);
        $this->assertArrayNotHasKey('timeout', $decoded);
        $this->assertArrayNotHasKey('checksum', $decoded);
        $this->assertArrayHasKey('serverTime', $decoded);
    }

    #[Test]
    public function jsonSerializeWithContentIncludesContentSize(): void
    {
        // Arrange
        $content = 'console.log("Hello World! This is a longer script content.");';
        $response = new ScriptDownloadResponse(
            status: 'ok',
            scriptId: 999,
            content: $content
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals($content, $decoded['content']);
        $this->assertEquals(strlen($content), $decoded['contentSize']);
    }

    #[Test]
    public function successResponseWithDefaultTimeout(): void
    {
        // Act
        $response = ScriptDownloadResponse::success(
            scriptId: 100,
            scriptCode: 'SCRIPT_100',
            scriptName: 'default_timeout.js',
            scriptType: 'javascript',
            content: 'test content',
            version: '1.0.0'
        );

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertEquals(3600, $response->getTimeout()); // Default timeout
    }

    #[Test]
    public function jsonSerializeHandlesAllOptionalFields(): void
    {
        // Arrange
        $response = new ScriptDownloadResponse(
            status: 'ok',
            scriptId: 1234,
            scriptCode: 'SCRIPT_FULL',
            scriptName: 'full_test.js',
            scriptType: 'javascript',
            content: 'full test content',
            version: '5.0.0',
            parameters: [
                'timeout' => 30,
                'retries' => 3,
                'environment' => 'production',
                'options' => ['debug' => false, 'verbose' => true],
            ],
            timeout: 7200,
            checksum: 'sha256_full_checksum',
            message: 'Complete script with all fields'
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertArrayHasKey('status', $decoded);
        $this->assertArrayHasKey('scriptId', $decoded);
        $this->assertArrayHasKey('scriptCode', $decoded);
        $this->assertArrayHasKey('scriptName', $decoded);
        $this->assertArrayHasKey('scriptType', $decoded);
        $this->assertArrayHasKey('content', $decoded);
        $this->assertArrayHasKey('contentSize', $decoded);
        $this->assertArrayHasKey('version', $decoded);
        $this->assertArrayHasKey('parameters', $decoded);
        $this->assertArrayHasKey('timeout', $decoded);
        $this->assertArrayHasKey('checksum', $decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('serverTime', $decoded);
        $this->assertIsArray($decoded['parameters']);
        $this->assertArrayHasKey('options', $decoded['parameters']);
    }
}
