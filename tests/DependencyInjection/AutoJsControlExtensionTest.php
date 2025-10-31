<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AutoJsControlBundle\DependencyInjection\AutoJsControlExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(AutoJsControlExtension::class)]
final class AutoJsControlExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    protected static function getExtensionClass(): string
    {
        return AutoJsControlExtension::class;
    }

    public function testGetAlias(): void
    {
        // Act
        $extension = new AutoJsControlExtension();
        $alias = $extension->getAlias();

        // Assert
        $this->assertSame('auto_js_control', $alias);
    }
}
