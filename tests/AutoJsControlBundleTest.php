<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AutoJsControlBundle\AutoJsControlBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(AutoJsControlBundle::class)]
#[RunTestsInSeparateProcesses]
final class AutoJsControlBundleTest extends AbstractBundleTestCase
{
}
