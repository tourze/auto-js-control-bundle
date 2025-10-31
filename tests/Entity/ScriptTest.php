<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(Script::class)]
final class ScriptTest extends AbstractEntityTestCase
{
    private Script $script;

    protected function createEntity(): object
    {
        return new Script();
    }

    protected function setUp(): void
    {
        $this->script = new Script();
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     */
    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'code' => ['code', 'SCRIPT_001'];

        yield 'name' => ['name', 'Auto Click Script'];

        yield 'description' => ['description', 'This script automatically clicks on specified elements'];

        yield 'content' => ['content', 'auto.click(100, 200);\nauto.sleep(1000);'];

        yield 'projectPath' => ['projectPath', '/path/to/project/script.js'];

        yield 'scriptType' => ['scriptType', ScriptType::AUTO_JS];

        yield 'status' => ['status', ScriptStatus::ACTIVE];

        yield 'version' => ['version', 2];

        yield 'parameters' => ['parameters', json_encode([
            'click_x' => 100,
            'click_y' => 200,
            'delay' => 1000,
        ])];

        yield 'priority' => ['priority', 100];

        yield 'timeout' => ['timeout', 7200];

        yield 'maxRetries' => ['maxRetries', 5];

        yield 'valid' => ['valid', false];

        yield 'securityRules' => ['securityRules', json_encode(['rule1' => 'value1', 'rule2' => 'value2'])];

        yield 'checksum' => ['checksum', 'abc123def456'];

        yield 'tags' => ['tags', ['automation', 'click', 'ui']];

        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];

        yield 'updateTime' => ['updateTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
    }

    #[Test]
    public function constructorSetsDefaultValues(): void
    {
        // Assert
        $this->assertNull($this->script->getId());
        $this->assertNull($this->script->getCode());
        $this->assertNull($this->script->getName());
        $this->assertNull($this->script->getDescription());
        $this->assertNull($this->script->getContent());
        $this->assertNull($this->script->getProjectPath());
        $this->assertEquals(ScriptType::JAVASCRIPT, $this->script->getScriptType());
        $this->assertEquals(ScriptStatus::DRAFT, $this->script->getStatus());
        $this->assertEquals(1, $this->script->getVersion());
        $this->assertNull($this->script->getParameters());
        $this->assertEquals(0, $this->script->getPriority());
        $this->assertEquals(3600, $this->script->getTimeout());
        $this->assertEquals(3, $this->script->getMaxRetries());
        $this->assertTrue($this->script->isValid());
        $this->assertNull($this->script->getSecurityRules());
        $this->assertNull($this->script->getChecksum());
        $this->assertNull($this->script->getTags());
        $this->assertCount(0, $this->script->getTasks());
        $this->assertCount(0, $this->script->getExecutionRecords());
        $this->assertNull($this->script->getCreateTime());
        $this->assertNull($this->script->getUpdateTime());
    }

    #[Test]
    public function setNameSetsAndGetsCorrectly(): void
    {
        // Arrange
        $name = 'Auto Click Script';

        // Act
        $this->script->setName($name);

        // Assert
        $this->assertEquals($name, $this->script->getName());
    }

    #[Test]
    public function setDescriptionSetsAndGetsCorrectly(): void
    {
        // Arrange
        $description = 'This script automatically clicks on specified elements';

        // Act
        $this->script->setDescription($description);

        // Assert
        $this->assertEquals($description, $this->script->getDescription());
    }

    #[Test]
    public function setContentSetsAndGetsCorrectly(): void
    {
        // Arrange
        $content = 'auto.click(100, 200);\nauto.sleep(1000);';

        // Act
        $this->script->setContent($content);

        // Assert
        $this->assertEquals($content, $this->script->getContent());
    }

    #[Test]
    public function setScriptTypeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $type = ScriptType::AUTO_JS;

        // Act
        $this->script->setScriptType($type);

        // Assert
        $this->assertEquals($type, $this->script->getScriptType());
    }

    #[Test]
    public function setCodeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $code = 'SCRIPT_001';

        // Act
        $this->script->setCode($code);

        // Assert
        $this->assertEquals($code, $this->script->getCode());
    }

    #[Test]
    public function setProjectPathSetsAndGetsCorrectly(): void
    {
        // Arrange
        $path = '/path/to/project/script.js';

        // Act
        $this->script->setProjectPath($path);

        // Assert
        $this->assertEquals($path, $this->script->getProjectPath());
    }

    #[Test]
    public function setPrioritySetsAndGetsCorrectly(): void
    {
        // Arrange
        $priority = 100;

        // Act
        $this->script->setPriority($priority);

        // Assert
        $this->assertEquals($priority, $this->script->getPriority());
    }

    #[Test]
    public function setTimeoutSetsAndGetsCorrectly(): void
    {
        // Arrange
        $timeout = 7200;

        // Act
        $this->script->setTimeout($timeout);

        // Assert
        $this->assertEquals($timeout, $this->script->getTimeout());
    }

    #[Test]
    public function setMaxRetriesSetsAndGetsCorrectly(): void
    {
        // Arrange
        $maxRetries = 5;

        // Act
        $this->script->setMaxRetries($maxRetries);

        // Assert
        $this->assertEquals($maxRetries, $this->script->getMaxRetries());
    }

    #[Test]
    public function setValidSetsAndGetsCorrectly(): void
    {
        // Act & Assert - valid
        $this->script->setValid(true);
        $this->assertTrue($this->script->isValid());

        // Act & Assert - invalid
        $this->script->setValid(false);
        $this->assertFalse($this->script->isValid());
    }

    #[Test]
    public function setSecurityRulesSetsAndGetsCorrectly(): void
    {
        // Arrange
        $rules = json_encode(['rule1' => 'value1', 'rule2' => 'value2']);
        $this->assertIsString($rules);

        // Act
        $this->script->setSecurityRules($rules);

        // Assert
        $this->assertEquals($rules, $this->script->getSecurityRules());
    }

    #[Test]
    public function setChecksumSetsAndGetsCorrectly(): void
    {
        // Arrange
        $checksum = 'abc123def456';

        // Act
        $this->script->setChecksum($checksum);

        // Assert
        $this->assertEquals($checksum, $this->script->getChecksum());
    }

    #[Test]
    public function setTagsSetsAndGetsCorrectly(): void
    {
        // Arrange
        $tags = ['automation', 'click', 'ui'];

        // Act
        $this->script->setTags($tags);

        // Assert
        $this->assertEquals($tags, $this->script->getTags());
    }

    #[Test]
    public function setStatusSetsAndGetsCorrectly(): void
    {
        // Arrange
        $status = ScriptStatus::ACTIVE;

        // Act
        $this->script->setStatus($status);

        // Assert
        $this->assertEquals($status, $this->script->getStatus());
    }

    #[Test]
    public function setVersionSetsAndGetsCorrectly(): void
    {
        // Arrange
        $version = 2;

        // Act
        $this->script->setVersion($version);

        // Assert
        $this->assertEquals($version, $this->script->getVersion());
    }

    #[Test]
    public function setParametersSetsAndGetsCorrectly(): void
    {
        // Arrange
        $parameters = json_encode([
            'click_x' => 100,
            'click_y' => 200,
            'delay' => 1000,
        ]);
        $this->assertIsString($parameters);

        // Act
        $this->script->setParameters($parameters);

        // Assert
        $this->assertEquals($parameters, $this->script->getParameters());
    }

    #[Test]
    public function addTaskAddsToCollection(): void
    {
        // Arrange
        $task1 = new Task();
        $task2 = new Task();

        // Act
        $this->script->addTask($task1);
        $this->script->addTask($task2);

        // Assert
        $this->assertCount(2, $this->script->getTasks());
        $this->assertTrue($this->script->getTasks()->contains($task1));
        $this->assertTrue($this->script->getTasks()->contains($task2));
        $this->assertSame($this->script, $task1->getScript());
        $this->assertSame($this->script, $task2->getScript());
    }

    #[Test]
    public function addTaskPreventsDuplicates(): void
    {
        // Arrange
        $task = new Task();

        // Act
        $this->script->addTask($task);
        $this->script->addTask($task); // Add same task again

        // Assert
        $this->assertCount(1, $this->script->getTasks());
    }

    #[Test]
    public function removeTaskRemovesFromCollection(): void
    {
        // Arrange
        $task1 = new Task();
        $task2 = new Task();

        $this->script->addTask($task1);
        $this->script->addTask($task2);

        // Act
        $this->script->removeTask($task1);

        // Assert
        $this->assertCount(1, $this->script->getTasks());
        $this->assertFalse($this->script->getTasks()->contains($task1));
        $this->assertTrue($this->script->getTasks()->contains($task2));
        $this->assertNull($task1->getScript());
        $this->assertSame($this->script, $task2->getScript());
    }

    #[Test]
    public function addExecutionRecordAddsToCollection(): void
    {
        // Arrange
        $record1 = new ScriptExecutionRecord();
        $record2 = new ScriptExecutionRecord();

        // Act
        $this->script->addExecutionRecord($record1);
        $this->script->addExecutionRecord($record2);

        // Assert
        $this->assertCount(2, $this->script->getExecutionRecords());
        $this->assertTrue($this->script->getExecutionRecords()->contains($record1));
        $this->assertTrue($this->script->getExecutionRecords()->contains($record2));
        $this->assertSame($this->script, $record1->getScript());
        $this->assertSame($this->script, $record2->getScript());
    }

    #[Test]
    public function toStringReturnsCorrectFormat(): void
    {
        // Test with no name and no code
        $this->assertEquals('未命名脚本 ()', (string) $this->script);

        // Test with name
        $this->script->setName('测试脚本');
        $this->assertEquals('测试脚本 ()', (string) $this->script);

        // Test with code
        $this->script->setCode('SCRIPT_001');
        $this->assertEquals('测试脚本 (SCRIPT_001)', (string) $this->script);
    }

    #[Test]
    public function timestampableTraitSetsTimestamps(): void
    {
        // Arrange
        $now = new \DateTimeImmutable();

        // Act
        $this->script->setCreateTime($now);
        $this->script->setUpdateTime($now);

        // Assert
        $this->assertSame($now, $this->script->getCreateTime());
        $this->assertSame($now, $this->script->getUpdateTime());
    }
}
