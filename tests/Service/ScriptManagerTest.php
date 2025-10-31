<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;
use Tourze\AutoJsControlBundle\Service\ScriptManager;

/**
 * @internal
 */
#[CoversClass(ScriptManager::class)]
final class ScriptManagerTest extends TestCase
{
    private ScriptManager $scriptManager;

    private EntityManagerInterface&MockObject $entityManager;

    private ScriptRepository&MockObject $scriptRepository;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->scriptRepository = $this->createMock(ScriptRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->scriptManager = new ScriptManager(
            $this->entityManager,
            $this->scriptRepository,
            $this->logger
        );
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(ScriptManager::class, $this->scriptManager);
    }

    public function testCreateScriptSuccess(): void
    {
        // Arrange
        $data = [
            'code' => 'TEST_SCRIPT',
            'name' => 'Test Script',
            'description' => 'Test Description',
            'scriptType' => ScriptType::JAVASCRIPT,
            'content' => 'console.log("test");',
            'parameters' => '{"param1": "value1"}',
            'timeout' => 3600,
            'maxRetries' => 3,
            'tags' => ['tag1', 'tag2'],
        ];

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(Script::class))
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('脚本创建成功')
        ;

        // Act
        $result = $this->scriptManager->createScript($data);

        // Assert
        $this->assertInstanceOf(Script::class, $result);
        $this->assertEquals('TEST_SCRIPT', $result->getCode());
        $this->assertEquals('Test Script', $result->getName());
        $this->assertEquals(1, $result->getVersion());
        $this->assertNotEmpty($result->getChecksum());
    }

    public function testCreateScriptWithException(): void
    {
        // Arrange
        $data = ['code' => 'TEST_SCRIPT'];

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willThrowException(new \Exception('Database error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('创建脚本失败')
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('创建脚本失败: Database error');

        $this->scriptManager->createScript($data);
    }

    public function testUpdateScriptSuccess(): void
    {
        // Arrange
        $scriptId = 1;
        $data = [
            'name' => 'Updated Script',
            'content' => 'console.log("updated");',
        ];

        $script = new Script();
        $script->setCode('TEST_SCRIPT');
        $script->setName('Original Script');
        $script->setContent('console.log("original");');
        $script->setVersion(1);

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn($script)
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('脚本更新成功')
        ;

        // Act
        $result = $this->scriptManager->updateScript($scriptId, $data);

        // Assert
        $this->assertInstanceOf(Script::class, $result);
        $this->assertEquals('Updated Script', $result->getName());
        $this->assertEquals(2, $result->getVersion()); // Version incremented due to content change
    }

    public function testUpdateScriptWithException(): void
    {
        // Arrange
        $scriptId = 1;
        $data = ['name' => 'Updated Script'];

        $script = new Script();
        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn($script)
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception('Database error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('更新脚本失败')
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('更新脚本失败: Database error');

        $this->scriptManager->updateScript($scriptId, $data);
    }

    public function testGetScriptSuccess(): void
    {
        // Arrange
        $scriptId = 1;
        $script = new Script();
        $script->setCode('TEST_SCRIPT');

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn($script)
        ;

        // Act
        $result = $this->scriptManager->getScript($scriptId);

        // Assert
        $this->assertInstanceOf(Script::class, $result);
        $this->assertEquals('TEST_SCRIPT', $result->getCode());
    }

    public function testGetScriptNotFound(): void
    {
        // Arrange
        $scriptId = 999;

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn(null)
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('脚本不存在: 999');

        $this->scriptManager->getScript($scriptId);
    }

    public function testGetScriptByCodeSuccess(): void
    {
        // Arrange
        $scriptCode = 'TEST_SCRIPT';
        $script = new Script();
        $script->setCode($scriptCode);

        $this->scriptRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => $scriptCode])
            ->willReturn($script)
        ;

        // Act
        $result = $this->scriptManager->getScriptByCode($scriptCode);

        // Assert
        $this->assertInstanceOf(Script::class, $result);
        $this->assertEquals($scriptCode, $result->getCode());
    }

    public function testGetScriptByCodeNotFound(): void
    {
        // Arrange
        $scriptCode = 'NONEXISTENT_SCRIPT';

        $this->scriptRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => $scriptCode])
            ->willReturn(null)
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('脚本不存在: 0');

        $this->scriptManager->getScriptByCode($scriptCode);
    }

    public function testDeleteScriptSuccess(): void
    {
        // Arrange
        $scriptId = 1;
        $script = new Script();
        $script->setCode('TEST_SCRIPT');
        $script->setValid(true);

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn($script)
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('脚本已删除')
        ;

        // Act
        $this->scriptManager->deleteScript($scriptId);

        // Assert
        $this->assertFalse($script->isValid());
    }

    public function testDeleteScriptNotFound(): void
    {
        // Arrange
        $scriptId = 999;

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn(null)
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('尝试删除不存在的脚本')
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);

        $this->scriptManager->deleteScript($scriptId);
    }

    public function testUpdateScriptStatusSuccess(): void
    {
        // Arrange
        $scriptId = 1;
        $newStatus = 'active';
        $script = new Script();
        $script->setStatus(ScriptStatus::DRAFT);

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn($script)
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('脚本状态已更新')
        ;

        // Act
        $this->scriptManager->updateScriptStatus($scriptId, $newStatus);

        // Assert
        $this->assertEquals(ScriptStatus::ACTIVE, $script->getStatus());
    }

    public function testValidateScriptContentSuccess(): void
    {
        // Arrange
        $content = 'var test = "hello"; console.log(test);';
        $scriptType = 'javascript';

        // Act
        $result = $this->scriptManager->validateScriptContent($content, $scriptType);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateScriptContentWithEmptyContent(): void
    {
        // Arrange
        $content = '';
        $scriptType = 'javascript';

        // Act
        $result = $this->scriptManager->validateScriptContent($content, $scriptType);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertContains('脚本内容不能为空', (array) $result['errors']);
    }

    public function testValidateScriptContentJavaScriptWithDangerousFunction(): void
    {
        // Arrange
        $content = 'eval("console.log(\'test\')");';
        $scriptType = 'javascript';

        // Act
        $result = $this->scriptManager->validateScriptContent($content, $scriptType);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertContains('脚本包含潜在危险函数: eval', (array) $result['errors']);
    }

    public function testValidateAutoJsContent(): void
    {
        // Arrange
        $content = 'auto.setMode("fast"); toast("hello");';
        $scriptType = 'auto_js';

        // Act
        $result = $this->scriptManager->validateScriptContent($content, $scriptType);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateAutoJsContentWithoutApi(): void
    {
        // Arrange
        $content = 'console.log("test");';
        $scriptType = 'auto_js';

        // Act
        $result = $this->scriptManager->validateScriptContent($content, $scriptType);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertContains('未检测到Auto.js API调用', (array) $result['errors']);
    }

    public function testValidateShellScriptWithDangerousCommand(): void
    {
        // Arrange
        $content = 'rm -rf /';
        $scriptType = 'shell';

        // Act
        $result = $this->scriptManager->validateScriptContent($content, $scriptType);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertContains('脚本包含危险命令: rm -rf', (array) $result['errors']);
    }

    public function testSearchScriptsWithDefaults(): void
    {
        // Arrange
        $expectedCriteria = ['valid' => true];
        $expectedOrderBy = ['id' => 'DESC'];
        $expectedLimit = 20;
        $expectedOffset = 0;

        $this->scriptRepository->expects($this->once())
            ->method('findBy')
            ->with($expectedCriteria, $expectedOrderBy, $expectedLimit, $expectedOffset)
            ->willReturn([])
        ;

        // Act
        $result = $this->scriptManager->searchScripts();

        // Assert
        $this->assertIsArray($result);
    }

    public function testSearchScriptsWithCustomParameters(): void
    {
        // Arrange
        $criteria = ['status' => 'active', 'valid' => true];
        $orderBy = ['name' => 'ASC'];
        $limit = 10;
        $offset = 5;

        $this->scriptRepository->expects($this->once())
            ->method('findBy')
            ->with($criteria, $orderBy, $limit, $offset)
            ->willReturn([])
        ;

        // Act
        $result = $this->scriptManager->searchScripts($criteria, $orderBy, $limit, $offset);

        // Assert
        $this->assertIsArray($result);
    }

    public function testGetScriptStatistics(): void
    {
        // Arrange
        $this->scriptRepository->expects($this->atLeastOnce())
            ->method('count')
            ->willReturn(10)
        ;

        $this->scriptRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([])
        ;

        // Act
        $result = $this->scriptManager->getScriptStatistics();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('byStatus', $result);
        $this->assertArrayHasKey('byType', $result);
        $this->assertArrayHasKey('recentlyUpdated', $result);
    }

    public function testDuplicateScriptSuccess(): void
    {
        // Arrange
        $sourceScriptId = 1;
        $newCode = 'DUPLICATED_SCRIPT';
        $newName = 'Duplicated Script';

        $sourceScript = new Script();
        $sourceScript->setCode('ORIGINAL_SCRIPT');
        $sourceScript->setName('Original Script');
        $sourceScript->setDescription('Original Description');
        $sourceScript->setScriptType(ScriptType::JAVASCRIPT);
        $sourceScript->setContent('console.log("original");');
        $sourceScript->setParameters('{"param": "value"}');
        $sourceScript->setTimeout(3600);
        $sourceScript->setMaxRetries(3);
        $sourceScript->setTags(['tag1']);
        $sourceScript->setChecksum('abc123');

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($sourceScriptId)
            ->willReturn($sourceScript)
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(Script::class))
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('脚本复制成功')
        ;

        // Act
        $result = $this->scriptManager->duplicateScript($sourceScriptId, $newCode, $newName);

        // Assert
        $this->assertInstanceOf(Script::class, $result);
        $this->assertEquals($newCode, $result->getCode());
        $this->assertEquals($newName, $result->getName());
        $this->assertEquals('Original Description (复制)', $result->getDescription());
        $this->assertEquals(1, $result->getVersion());
        $this->assertEquals(ScriptStatus::DRAFT, $result->getStatus());
    }
}
