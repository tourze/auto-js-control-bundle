<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(ScriptRepository::class)]
#[RunTestsInSeparateProcesses]
final class ScriptRepositoryTest extends AbstractRepositoryTestCase
{
    private ScriptRepository $repository;

    protected function onSetUp(): void
    {
        $repository = self::getEntityManager()->getRepository(Script::class);
        $this->assertInstanceOf(ScriptRepository::class, $repository);
        $this->repository = $repository;
    }

    // Test custom repository methods
    #[Test]
    public function findActiveScripts(): void
    {
        // Arrange
        $activeScript1 = $this->createScript('ACTIVE_001', true, 20);
        $activeScript2 = $this->createScript('ACTIVE_002', true, 10);
        $inactiveScript = $this->createScript('INACTIVE_001', false, 30); // Higher priority but invalid

        $em = self::getEntityManager();
        $em->persist($activeScript1);
        $em->persist($activeScript2);
        $em->persist($inactiveScript);
        $em->flush();

        // Act
        $scripts = $this->repository->findActiveScripts();

        // Assert
        $this->assertCount(2, $scripts);
        $scriptCodes = array_map(fn ($s) => $s->getCode(), $scripts);
        $this->assertContains('ACTIVE_001', $scriptCodes);
        $this->assertContains('ACTIVE_002', $scriptCodes);
        $this->assertNotContains('INACTIVE_001', $scriptCodes);

        // Check order (priority DESC, then name ASC)
        $this->assertEquals('ACTIVE_001', $scripts[0]->getCode()); // Higher priority (20)
        $this->assertEquals('ACTIVE_002', $scripts[1]->getCode()); // Lower priority (10)
    }

    #[Test]
    public function findActiveScriptsOrdersByPriorityAndName(): void
    {
        // Arrange
        $script1 = $this->createScript('SCRIPT_B', true, 10);
        $script2 = $this->createScript('SCRIPT_A', true, 10); // Same priority, should be ordered by name
        $script3 = $this->createScript('SCRIPT_C', true, 20); // Higher priority

        $em = self::getEntityManager();
        $em->persist($script1);
        $em->persist($script2);
        $em->persist($script3);
        $em->flush();

        // Act
        $scripts = $this->repository->findActiveScripts();

        // Assert
        $scriptCodes = array_map(fn ($s) => $s->getCode(), $scripts);

        // Check order: SCRIPT_C (priority 20), then SCRIPT_A and SCRIPT_B (priority 10, alphabetically)
        $this->assertEquals('SCRIPT_C', $scriptCodes[0]);
        $this->assertEquals('SCRIPT_A', $scriptCodes[1]);
        $this->assertEquals('SCRIPT_B', $scriptCodes[2]);
    }

    #[Test]
    public function findByCode(): void
    {
        // Arrange
        $script = $this->createScript('UNIQUE_CODE_001', true);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        // Act
        $found = $this->repository->findByCode('UNIQUE_CODE_001');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals('UNIQUE_CODE_001', $found->getCode());
        $this->assertEquals('Script UNIQUE_CODE_001', $found->getName());
    }

    #[Test]
    public function findByCodeReturnsNullWhenNotFound(): void
    {
        // Arrange
        $script = $this->createScript('EXISTING_CODE', true);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        // Act
        $found = $this->repository->findByCode('NON_EXISTENT_CODE');

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function findByScriptType(): void
    {
        // Arrange
        $script1 = $this->createScript('TYPE_TEST_001', true, 10, ScriptType::AUTO_JS);
        $script2 = $this->createScript('TYPE_TEST_002', true, 20, ScriptType::AUTO_JS);
        $script3 = $this->createScript('TYPE_TEST_003', true, 15, ScriptType::JAVASCRIPT); // Different type
        $script4 = $this->createScript('TYPE_TEST_004', false, 30, ScriptType::AUTO_JS); // Invalid

        $em = self::getEntityManager();
        $em->persist($script1);
        $em->persist($script2);
        $em->persist($script3);
        $em->persist($script4);
        $em->flush();

        // Act
        $scripts = $this->repository->findByScriptType(ScriptType::AUTO_JS->value);

        // Assert
        $this->assertCount(2, $scripts);
        $scriptCodes = array_map(fn ($s) => $s->getCode(), $scripts);
        $this->assertContains('TYPE_TEST_001', $scriptCodes);
        $this->assertContains('TYPE_TEST_002', $scriptCodes);
        $this->assertNotContains('TYPE_TEST_003', $scriptCodes); // Different type
        $this->assertNotContains('TYPE_TEST_004', $scriptCodes); // Invalid

        // Check order by priority
        $this->assertEquals('TYPE_TEST_002', $scripts[0]->getCode()); // Priority 20
        $this->assertEquals('TYPE_TEST_001', $scripts[1]->getCode()); // Priority 10
    }

    #[Test]
    public function findByScriptTypeReturnsEmptyArrayWhenNoMatch(): void
    {
        // Arrange
        $script = $this->createScript('SCRIPT_001', true, 10, ScriptType::AUTO_JS);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        // Act
        $scripts = $this->repository->findByScriptType('non_existent_type');

        // Assert
        $this->assertIsArray($scripts);
        $this->assertCount(0, $scripts);
    }

    // Standard repository method tests
    #[Test]
    public function findReturnsNullForNonExistentId(): void
    {
        // Act
        $found = $this->repository->find(99999);

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function findOneByReturnsNullWhenNoMatch(): void
    {
        // Arrange
        $script = $this->createScript('ONE_TEST_003', true, 10);
        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        // Act
        $found = $this->repository->findOneBy(['priority' => 999]);

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function testFindOneByWithOrderBy(): void
    {
        // Arrange
        $script1 = $this->createScript('FINDONE_ORDER_001', true, 10);
        $script2 = $this->createScript('FINDONE_ORDER_002', true, 20);

        $em = self::getEntityManager();
        $em->persist($script1);
        $em->persist($script2);
        $em->flush();

        // Act
        $found = $this->repository->findOneBy(['valid' => true], ['priority' => 'DESC']);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals(20, $found->getPriority()); // Should return highest priority first
    }

    #[Test]
    public function save(): void
    {
        // Arrange
        $script = $this->createScript('SAVE_TEST_001', true);

        // Act
        $this->repository->save($script);

        // Assert - Verify script is persisted with correct data
        $found = $this->repository->find($script->getId());
        $this->assertNotNull($found);
        $this->assertEquals('SAVE_TEST_001', $found->getCode());
    }

    #[Test]
    public function saveWithoutFlush(): void
    {
        // Arrange
        $script = $this->createScript('SAVE_TEST_002', true);

        // Act
        $this->repository->save($script, false);

        // The entity is managed but not yet persisted to database

        // Clear entity manager to force database query
        self::getEntityManager()->clear();

        // Try to find the script again - it should not be found in database
        $scripts = $this->repository->findBy(['code' => 'SAVE_TEST_002']);
        $this->assertCount(0, $scripts);
    }

    #[Test]
    public function remove(): void
    {
        // Arrange
        $script = $this->createScript('REMOVE_TEST_001', true);
        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        $scriptId = $script->getId();
        $this->assertNotNull($scriptId);

        // Act
        $this->repository->remove($script);

        // Assert
        $found = $this->repository->find($scriptId);
        $this->assertNull($found);
    }

    #[Test]
    public function removeWithoutFlush(): void
    {
        // Arrange
        $script = $this->createScript('REMOVE_TEST_002', true);
        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        $scriptId = $script->getId();
        $this->assertNotNull($scriptId);

        // Act
        $this->repository->remove($script, false);

        // Clear entity manager to force database query
        $em->clear();

        // Assert
        $found = $this->repository->find($scriptId);
        $this->assertNotNull($found);
    }

    #[Test]
    public function findByWithNullValuesWorks(): void
    {
        // Arrange
        $script = $this->createScript('NULL_TEST_001', true);
        $script->setDescription(null);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        // Act
        $scripts = $this->repository->findBy(['description' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($scripts));
        $scriptCodes = array_map(fn ($s) => $s->getCode(), $scripts);
        $this->assertContains('NULL_TEST_001', $scriptCodes);
    }

    #[Test]
    public function countWithNullValuesWorks(): void
    {
        // Arrange
        $script1 = $this->createScript('NULL_COUNT_001', true);
        $script1->setDescription(null);

        $script2 = $this->createScript('NULL_COUNT_002', true);
        $script2->setDescription('Not null');

        $em = self::getEntityManager();
        $em->persist($script1);
        $em->persist($script2);
        $em->flush();

        // Act
        $nullCount = $this->repository->count(['description' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(1, $nullCount);
    }

    #[Test]
    public function testFindOneByWithOrderByPriority(): void
    {
        // Arrange
        $script1 = $this->createScript('PRIORITY_SORT_001', true, 10);
        $script2 = $this->createScript('PRIORITY_SORT_002', true, 30);
        $script3 = $this->createScript('PRIORITY_SORT_003', true, 20);

        $em = self::getEntityManager();
        $em->persist($script1);
        $em->persist($script2);
        $em->persist($script3);
        $em->flush();

        // Act - Find highest priority valid script
        $found = $this->repository->findOneBy(['valid' => true], ['priority' => 'DESC']);

        // Assert
        $this->assertNotNull($found);
        $this->assertInstanceOf(Script::class, $found);
        $this->assertEquals(30, $found->getPriority());
        $this->assertEquals('PRIORITY_SORT_002', $found->getCode());
    }

    #[Test]
    public function testFindByNullContent(): void
    {
        // Arrange
        $scriptWithContent = $this->createScript('CONTENT_TEST_001', true);
        $scriptWithContent->setContent('console.log("test");');

        $scriptWithoutContent = $this->createScript('CONTENT_TEST_002', true);
        $scriptWithoutContent->setContent(null);

        $em = self::getEntityManager();
        $em->persist($scriptWithContent);
        $em->persist($scriptWithoutContent);
        $em->flush();

        // Act
        $scriptsWithoutContent = $this->repository->findBy(['content' => null]);

        // Assert
        $this->assertIsArray($scriptsWithoutContent);
        $scriptCodes = array_map(fn ($s) => $s->getCode(), $scriptsWithoutContent);
        $this->assertContains('CONTENT_TEST_002', $scriptCodes);
        $this->assertNotContains('CONTENT_TEST_001', $scriptCodes);
    }

    #[Test]
    public function testCountByNullProjectPath(): void
    {
        // Arrange
        $scriptWithProjectPath = $this->createScript('PROJECT_PATH_001', true);
        $scriptWithProjectPath->setProjectPath('/path/to/project');

        $scriptWithoutProjectPath1 = $this->createScript('PROJECT_PATH_002', true);
        $scriptWithoutProjectPath1->setProjectPath(null);

        $scriptWithoutProjectPath2 = $this->createScript('PROJECT_PATH_003', true);
        $scriptWithoutProjectPath2->setProjectPath(null);

        $em = self::getEntityManager();
        $em->persist($scriptWithProjectPath);
        $em->persist($scriptWithoutProjectPath1);
        $em->persist($scriptWithoutProjectPath2);
        $em->flush();

        // Act
        $countWithoutProjectPath = $this->repository->count(['projectPath' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(2, $countWithoutProjectPath);
    }

    #[Test]
    public function testFindByNullParameters(): void
    {
        // Arrange
        $scriptWithParams = $this->createScript('PARAMS_TEST_001', true);
        $scriptWithParams->setParameters('{"param1": "value1"}');

        $scriptWithoutParams = $this->createScript('PARAMS_TEST_002', true);
        $scriptWithoutParams->setParameters(null);

        $em = self::getEntityManager();
        $em->persist($scriptWithParams);
        $em->persist($scriptWithoutParams);
        $em->flush();

        // Act
        $scriptsWithoutParams = $this->repository->findBy(['parameters' => null]);

        // Assert
        $this->assertIsArray($scriptsWithoutParams);
        $scriptCodes = array_map(fn ($s) => $s->getCode(), $scriptsWithoutParams);
        $this->assertContains('PARAMS_TEST_002', $scriptCodes);
        $this->assertNotContains('PARAMS_TEST_001', $scriptCodes);
    }

    #[Test]
    public function testCountByNullChecksum(): void
    {
        // Arrange
        $scriptWithChecksum = $this->createScript('CHECKSUM_TEST_001', true);
        $scriptWithChecksum->setChecksum('abc123def456');

        $scriptWithoutChecksum1 = $this->createScript('CHECKSUM_TEST_002', true);
        $scriptWithoutChecksum1->setChecksum(null);

        $scriptWithoutChecksum2 = $this->createScript('CHECKSUM_TEST_003', true);
        $scriptWithoutChecksum2->setChecksum(null);

        $em = self::getEntityManager();
        $em->persist($scriptWithChecksum);
        $em->persist($scriptWithoutChecksum1);
        $em->persist($scriptWithoutChecksum2);
        $em->flush();

        // Act
        $countWithoutChecksum = $this->repository->count(['checksum' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(2, $countWithoutChecksum);
    }

    #[Test]
    public function testFindByNullSecurityRules(): void
    {
        // Arrange
        $scriptWithSecurityRules = $this->createScript('SECURITY_RULES_001', true);
        $scriptWithSecurityRules->setSecurityRules('{"rules": ["no_system_calls"]}');

        $scriptWithoutSecurityRules = $this->createScript('SECURITY_RULES_002', true);
        $scriptWithoutSecurityRules->setSecurityRules(null);

        $em = self::getEntityManager();
        $em->persist($scriptWithSecurityRules);
        $em->persist($scriptWithoutSecurityRules);
        $em->flush();

        // Act
        $scriptsWithoutSecurityRules = $this->repository->findBy(['securityRules' => null]);

        // Assert
        $this->assertIsArray($scriptsWithoutSecurityRules);
        $scriptCodes = array_map(fn ($s) => $s->getCode(), $scriptsWithoutSecurityRules);
        $this->assertContains('SECURITY_RULES_002', $scriptCodes);
        $this->assertNotContains('SECURITY_RULES_001', $scriptCodes);
    }

    #[Test]
    public function testCountByNullTags(): void
    {
        // Arrange
        $scriptWithTags = $this->createScript('TAGS_TEST_001', true);
        $scriptWithTags->setTags(['automation', 'test']);

        $scriptWithoutTags1 = $this->createScript('TAGS_TEST_002', true);
        $scriptWithoutTags1->setTags(null);

        $scriptWithoutTags2 = $this->createScript('TAGS_TEST_003', true);
        $scriptWithoutTags2->setTags(null);

        $em = self::getEntityManager();
        $em->persist($scriptWithTags);
        $em->persist($scriptWithoutTags1);
        $em->persist($scriptWithoutTags2);
        $em->flush();

        // Act
        $countWithoutTags = $this->repository->count(['tags' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(2, $countWithoutTags);
    }

    private function createScript(string $code, bool $valid, int $priority = 10, ?ScriptType $scriptType = null): Script
    {
        $script = new Script();
        $script->setCode($code);
        $script->setName('Script ' . $code);
        $script->setDescription('Test script ' . $code);
        $script->setContent('// Test script content for ' . $code);
        $script->setScriptType($scriptType ?? ScriptType::AUTO_JS);
        $script->setValid($valid);
        $script->setPriority($priority);
        $script->setMaxRetries(3);
        $script->setTimeout(300);

        return $script;
    }

    protected function createNewEntity(): object
    {
        $script = new Script();
        $script->setCode('TEST-SCRIPT-' . uniqid());
        $script->setName('Test Script');
        $script->setDescription('Test script description');
        $script->setContent('// Test script content');
        $script->setScriptType(ScriptType::AUTO_JS);
        $script->setValid(true);
        $script->setPriority(10);
        $script->setMaxRetries(3);
        $script->setTimeout(300);

        return $script;
    }

    /**
     * @return ServiceEntityRepository<Script>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    #[Test]
    public function testFindOneByRespectsSortingLogic(): void
    {
        // Arrange
        $script1 = $this->createScript('SORT_SCRIPT_001', true, 10);
        $script2 = $this->createScript('SORT_SCRIPT_002', true, 20);
        $em = self::getEntityManager();
        $em->persist($script1);
        $em->persist($script2);
        $em->flush();

        // Act
        $result = $this->repository->findOneBy(['valid' => true], ['priority' => 'DESC']);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(20, $result->getPriority());
        $this->assertEquals('SORT_SCRIPT_002', $result->getCode());
    }

    #[Test]
    public function testFindByContentNull(): void
    {
        // Arrange
        $scriptWithContent = $this->createScript('CONTENT_SCRIPT_001', true);
        $scriptWithContent->setContent('console.log("test");');

        $scriptWithoutContent = $this->createScript('CONTENT_SCRIPT_002', true);
        $scriptWithoutContent->setContent(null);

        $em = self::getEntityManager();
        $em->persist($scriptWithContent);
        $em->persist($scriptWithoutContent);
        $em->flush();

        // Act
        $results = $this->repository->findBy(['content' => null]);

        // Assert
        $this->assertIsArray($results);
        $found = false;
        foreach ($results as $result) {
            if ($result->getId() === $scriptWithoutContent->getId()) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    #[Test]
    public function testFindByDescriptionNull(): void
    {
        // Arrange
        $scriptWithDescription = $this->createScript('DESC_SCRIPT_001', true);
        $scriptWithDescription->setDescription('Test description');

        $scriptWithoutDescription = $this->createScript('DESC_SCRIPT_002', true);
        $scriptWithoutDescription->setDescription(null);

        $em = self::getEntityManager();
        $em->persist($scriptWithDescription);
        $em->persist($scriptWithoutDescription);
        $em->flush();

        // Act
        $results = $this->repository->findBy(['description' => null]);

        // Assert
        $this->assertIsArray($results);
        $found = false;
        foreach ($results as $result) {
            if ($result->getId() === $scriptWithoutDescription->getId()) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    #[Test]
    public function testCountByDescriptionNull(): void
    {
        // Arrange
        $scriptWithDescription = $this->createScript('COUNT_DESC_SCRIPT_001', true);
        $scriptWithDescription->setDescription('Test description');

        $scriptWithoutDescription1 = $this->createScript('COUNT_DESC_SCRIPT_002', true);
        $scriptWithoutDescription1->setDescription(null);

        $scriptWithoutDescription2 = $this->createScript('COUNT_DESC_SCRIPT_003', true);
        $scriptWithoutDescription2->setDescription(null);

        $em = self::getEntityManager();
        $em->persist($scriptWithDescription);
        $em->persist($scriptWithoutDescription1);
        $em->persist($scriptWithoutDescription2);
        $em->flush();

        // Act
        $count = $this->repository->count(['description' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(2, $count);
    }

    #[Test]
    public function testFindActiveScripts(): void
    {
        // Arrange
        $activeScript1 = $this->createScript('TEST_ACTIVE_001', true, 20);
        $activeScript2 = $this->createScript('TEST_ACTIVE_002', true, 10);
        $inactiveScript = $this->createScript('TEST_INACTIVE_001', false, 30);

        $em = self::getEntityManager();
        $em->persist($activeScript1);
        $em->persist($activeScript2);
        $em->persist($inactiveScript);
        $em->flush();

        // Act
        $scripts = $this->repository->findActiveScripts();

        // Assert
        $this->assertIsArray($scripts);
        $scriptCodes = array_map(fn ($s) => $s->getCode(), $scripts);
        $this->assertContains('TEST_ACTIVE_001', $scriptCodes);
        $this->assertContains('TEST_ACTIVE_002', $scriptCodes);
        $this->assertNotContains('TEST_INACTIVE_001', $scriptCodes);
    }

    #[Test]
    public function testFindByCode(): void
    {
        // Arrange
        $script = $this->createScript('TEST_FIND_CODE', true);
        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        // Act
        $found = $this->repository->findByCode('TEST_FIND_CODE');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals('TEST_FIND_CODE', $found->getCode());
    }

    #[Test]
    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        // Act
        $found = $this->repository->findByCode('NON_EXISTENT_CODE');

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function testFindByScriptType(): void
    {
        // Arrange
        $autoJsScript1 = $this->createScript('TEST_AUTOJS_001', true);
        $autoJsScript1->setScriptType(ScriptType::AUTO_JS);

        $autoJsScript2 = $this->createScript('TEST_AUTOJS_002', true);
        $autoJsScript2->setScriptType(ScriptType::AUTO_JS);

        $shellScript = $this->createScript('TEST_SHELL_001', true);
        $shellScript->setScriptType(ScriptType::SHELL);

        $invalidAutoJsScript = $this->createScript('TEST_AUTOJS_INVALID', false);
        $invalidAutoJsScript->setScriptType(ScriptType::AUTO_JS);

        $em = self::getEntityManager();
        $em->persist($autoJsScript1);
        $em->persist($autoJsScript2);
        $em->persist($shellScript);
        $em->persist($invalidAutoJsScript);
        $em->flush();

        // Act
        $autoJsScripts = $this->repository->findByScriptType(ScriptType::AUTO_JS->value);

        // Assert
        $this->assertIsArray($autoJsScripts);
        $scriptCodes = array_map(fn ($s) => $s->getCode(), $autoJsScripts);
        $this->assertContains('TEST_AUTOJS_001', $scriptCodes);
        $this->assertContains('TEST_AUTOJS_002', $scriptCodes);
        $this->assertNotContains('TEST_SHELL_001', $scriptCodes);
        $this->assertNotContains('TEST_AUTOJS_INVALID', $scriptCodes);
    }
}
