<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(ScriptType::class)]
final class ScriptTypeTest extends AbstractEnumTestCase
{
    #[Test]
    public function casesReturnsAllTypes(): void
    {
        // Act
        $cases = ScriptType::cases();

        // Assert
        $this->assertCount(3, $cases);
        $this->assertContains(ScriptType::JAVASCRIPT, $cases);
        $this->assertContains(ScriptType::AUTO_JS, $cases);
        $this->assertContains(ScriptType::SHELL, $cases);
    }

    #[Test]
    public function valuesAreCorrect(): void
    {
        // Assert
        $this->assertEquals('javascript', ScriptType::JAVASCRIPT->value);
        $this->assertEquals('auto_js', ScriptType::AUTO_JS->value);
        $this->assertEquals('shell', ScriptType::SHELL->value);
    }

    #[Test]
    public function fromCreatesCorrectEnum(): void
    {
        // Assert
        $this->assertEquals(ScriptType::JAVASCRIPT, ScriptType::from('javascript'));
        $this->assertEquals(ScriptType::AUTO_JS, ScriptType::from('auto_js'));
        $this->assertEquals(ScriptType::SHELL, ScriptType::from('shell'));
    }

    #[Test]
    public function tryFromReturnsCorrectEnum(): void
    {
        // Valid values
        $this->assertEquals(ScriptType::JAVASCRIPT, ScriptType::tryFrom('javascript'));
        $this->assertEquals(ScriptType::AUTO_JS, ScriptType::tryFrom('auto_js'));
        $this->assertEquals(ScriptType::SHELL, ScriptType::tryFrom('shell'));

        // Invalid value
        $this->assertNull(ScriptType::tryFrom('invalid'));
    }

    #[Test]
    public function getLabelReturnsCorrectLabel(): void
    {
        // Assert
        $this->assertEquals('JavaScript', ScriptType::JAVASCRIPT->getLabel());
        $this->assertEquals('Auto.js脚本', ScriptType::AUTO_JS->getLabel());
        $this->assertEquals('Shell脚本', ScriptType::SHELL->getLabel());
    }

    #[Test]
    public function getDescriptionReturnsCorrectDescription(): void
    {
        // Assert
        $this->assertEquals('标准JavaScript脚本', ScriptType::JAVASCRIPT->getDescription());
        $this->assertEquals('Auto.js专用脚本，支持Auto.js API', ScriptType::AUTO_JS->getDescription());
        $this->assertEquals('Shell命令脚本', ScriptType::SHELL->getDescription());
    }

    #[Test]
    public function getFileExtensionReturnsCorrectExtension(): void
    {
        // Assert
        $this->assertEquals('js', ScriptType::JAVASCRIPT->getFileExtension());
        $this->assertEquals('js', ScriptType::AUTO_JS->getFileExtension());
        $this->assertEquals('sh', ScriptType::SHELL->getFileExtension());
    }

    #[Test]
    public function testToArray(): void
    {
        // Test SHELL enum
        $shellArray = ScriptType::SHELL->toArray();
        $this->assertEquals([
            'value' => 'shell',
            'label' => 'Shell脚本',
        ], $shellArray);

        // Test JAVASCRIPT enum
        $javascriptArray = ScriptType::JAVASCRIPT->toArray();
        $this->assertEquals([
            'value' => 'javascript',
            'label' => 'JavaScript',
        ], $javascriptArray);
    }

    #[Test]
    public function genOptionsReturnsCorrectArray(): void
    {
        // Act
        $options = ScriptType::genOptions();

        // Assert
        $this->assertCount(3, $options);

        // Check first option
        $this->assertEquals([
            'label' => 'JavaScript',
            'text' => 'JavaScript',
            'value' => 'javascript',
            'name' => 'JavaScript',
        ], $options[0]);

        // Check all values are present
        $values = array_column($options, 'value');
        $expectedValues = ['javascript', 'auto_js', 'shell'];
        $this->assertEquals($expectedValues, $values);

        // Check all labels are present
        $labels = array_column($options, 'label');
        $expectedLabels = ['JavaScript', 'Auto.js脚本', 'Shell脚本'];
        $this->assertEquals($expectedLabels, $labels);
    }
}
