<?php

namespace Tourze\AutoJsControlBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;
use Tourze\AutoJsControlBundle\Service\ScriptCrudService;
use Tourze\AutoJsControlBundle\Service\ScriptDataValidationService;

/**
 * @internal
 */
#[CoversClass(ScriptCrudService::class)]
final class ScriptCrudServiceTest extends TestCase
{
    private ScriptCrudService $scriptCrudService;

    private EntityManagerInterface&MockObject $entityManager;

    private ScriptRepository&MockObject $scriptRepository;

    private ScriptDataValidationService&MockObject $validationService;

    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->scriptRepository = $this->createMock(ScriptRepository::class);
        $this->validationService = $this->createMock(ScriptDataValidationService::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->scriptCrudService = new ScriptCrudService(
            $this->entityManager,
            $this->scriptRepository,
            $this->validationService,
            $this->validator
        );
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(ScriptCrudService::class, $this->scriptCrudService);
    }

    public function testCreateScript(): void
    {
        // Arrange
        $data = [
            'code' => 'TEST_SCRIPT',
            'name' => 'Test Script',
            'description' => 'Test Description',
            'scriptType' => 'javascript',
            'content' => 'console.log("test");',
            'version' => 1,
            'priority' => 5,
            'timeout' => 3600,
            'maxRetries' => 3,
        ];

        $this->validationService->expects($this->once())
            ->method('validateCreateData')
            ->with($data)
        ;

        $this->scriptRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'TEST_SCRIPT'])
            ->willReturn(null)
        ;

        $this->validationService->expects($this->once())
            ->method('parseScriptType')
            ->with('javascript')
            ->willReturn(ScriptType::JAVASCRIPT)
        ;

        $this->validationService->expects($this->once())
            ->method('validateScriptContent')
            ->with(self::isInstanceOf(Script::class), $data)
        ;

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList())
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(Script::class))
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->scriptCrudService->createScript($data);

        // Assert
        $this->assertInstanceOf(Script::class, $result);
        $this->assertEquals('TEST_SCRIPT', $result->getCode());
        $this->assertEquals('Test Script', $result->getName());
    }

    public function testCreateScriptThrowsExceptionWhenCodeExists(): void
    {
        // Arrange
        $data = ['code' => 'EXISTING_SCRIPT'];
        $existingScript = new Script();

        $this->validationService->expects($this->once())
            ->method('validateCreateData')
            ->with($data)
        ;

        $this->scriptRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'EXISTING_SCRIPT'])
            ->willReturn($existingScript)
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('请求参数错误: 脚本编码已存在');

        $this->scriptCrudService->createScript($data);
    }

    public function testUpdateScript(): void
    {
        // Arrange
        $scriptId = 1;
        $data = [
            'name' => 'Updated Script',
            'description' => 'Updated Description',
            'content' => 'console.log("updated");',
        ];

        $script = new Script();
        $script->setCode('TEST_SCRIPT');
        $script->setName('Original Script');
        $script->setScriptType(ScriptType::JAVASCRIPT);

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn($script)
        ;

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList())
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->scriptCrudService->updateScript($scriptId, $data);

        // Assert
        $this->assertInstanceOf(Script::class, $result);
        $this->assertEquals('Updated Script', $result->getName());
        $this->assertEquals('Updated Description', $result->getDescription());
    }

    public function testUpdateScriptThrowsExceptionWhenNotFound(): void
    {
        // Arrange
        $scriptId = 999;
        $data = ['name' => 'Updated Script'];

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn(null)
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('资源状态错误: 脚本不存在');

        $this->scriptCrudService->updateScript($scriptId, $data);
    }

    public function testDeleteScript(): void
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

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Act
        $this->scriptCrudService->deleteScript($scriptId);

        // Assert
        $this->assertFalse($script->isValid());
    }

    public function testDeleteScriptThrowsExceptionWhenHasTasks(): void
    {
        // Arrange
        $scriptId = 1;
        $script = $this->createMock(Script::class);
        $script->method('getTasks')
            ->willReturn(new ArrayCollection(['task1'])) // Non-empty collection
        ;

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn($script)
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('请求参数错误: 脚本正在被任务使用，无法删除');

        $this->scriptCrudService->deleteScript($scriptId);
    }

    public function testToggleScriptStatus(): void
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

        // Act
        $result = $this->scriptCrudService->toggleScriptStatus($scriptId);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertEquals('脚本已禁用', $result['message']);
        $this->assertFalse($script->isValid());
    }

    public function testToggleScriptStatusFromDisabledToEnabled(): void
    {
        // Arrange
        $scriptId = 1;
        $script = new Script();
        $script->setCode('TEST_SCRIPT');
        $script->setValid(false);

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($scriptId)
            ->willReturn($script)
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->scriptCrudService->toggleScriptStatus($scriptId);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertEquals('脚本已启用', $result['message']);
        $this->assertTrue($script->isValid());
    }

    public function testDuplicateScript(): void
    {
        // Arrange
        $sourceScriptId = 1;
        $data = [
            'code' => 'DUPLICATED_SCRIPT',
            'name' => 'Duplicated Script',
        ];

        $sourceScript = new Script();
        $sourceScript->setCode('ORIGINAL_SCRIPT');
        $sourceScript->setName('Original Script');
        $sourceScript->setDescription('Original Description');
        $sourceScript->setScriptType(ScriptType::JAVASCRIPT);
        $sourceScript->setContent('console.log("original");');
        $sourceScript->setProjectPath('/path/to/project');
        $sourceScript->setVersion(2);
        $sourceScript->setParameters('{"param1": "value1"}');
        $sourceScript->setPriority(5);
        $sourceScript->setTimeout(3600);
        $sourceScript->setMaxRetries(3);
        $sourceScript->setSecurityRules('{"rule1": "value1"}');

        $this->scriptRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'DUPLICATED_SCRIPT'])
            ->willReturn(null)
        ;

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

        // Act
        $result = $this->scriptCrudService->duplicateScript($sourceScriptId, $data);

        // Assert
        $this->assertInstanceOf(Script::class, $result);
        $this->assertEquals('DUPLICATED_SCRIPT', $result->getCode());
        $this->assertEquals('Duplicated Script', $result->getName());
        $this->assertEquals('Original Description', $result->getDescription());
        $this->assertEquals(ScriptType::JAVASCRIPT, $result->getScriptType());
        $this->assertEquals('console.log("original");', $result->getContent());
        $this->assertEquals(1, $result->getVersion()); // Reset to 1 for new script
    }

    public function testDuplicateScriptThrowsExceptionWhenCodeMissing(): void
    {
        // Arrange
        $sourceScriptId = 1;
        $data = []; // Missing code

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('请求参数错误: 必须提供新的脚本编码');

        $this->scriptCrudService->duplicateScript($sourceScriptId, $data);
    }

    public function testDuplicateScriptThrowsExceptionWhenCodeExists(): void
    {
        // Arrange
        $sourceScriptId = 1;
        $data = ['code' => 'EXISTING_SCRIPT'];
        $existingScript = new Script();

        $this->scriptRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'EXISTING_SCRIPT'])
            ->willReturn($existingScript)
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('请求参数错误: 脚本编码已存在');

        $this->scriptCrudService->duplicateScript($sourceScriptId, $data);
    }

    public function testDuplicateScriptThrowsExceptionWhenSourceNotFound(): void
    {
        // Arrange
        $sourceScriptId = 999;
        $data = ['code' => 'NEW_SCRIPT'];

        $this->scriptRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'NEW_SCRIPT'])
            ->willReturn(null)
        ;

        $this->scriptRepository->expects($this->once())
            ->method('find')
            ->with($sourceScriptId)
            ->willReturn(null)
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('资源状态错误: 脚本不存在');

        $this->scriptCrudService->duplicateScript($sourceScriptId, $data);
    }

    public function testCreateScriptWithValidationErrors(): void
    {
        // Arrange
        $data = [
            'code' => 'INVALID_SCRIPT',
            'name' => 'Invalid Script',
            'scriptType' => 'javascript',
        ];

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Name is required', null, [], null, 'name', null),
        ]);

        $this->validationService->expects($this->once())
            ->method('validateCreateData')
            ->with($data)
        ;

        $this->scriptRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'INVALID_SCRIPT'])
            ->willReturn(null)
        ;

        $this->validationService->expects($this->once())
            ->method('parseScriptType')
            ->with('javascript')
            ->willReturn(ScriptType::JAVASCRIPT)
        ;

        $this->validationService->expects($this->once())
            ->method('validateScriptContent')
            ->with(self::isInstanceOf(Script::class), $data)
        ;

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn($violations)
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('验证失败: 验证失败: {"name":"Name is required"}');

        $this->scriptCrudService->createScript($data);
    }
}
