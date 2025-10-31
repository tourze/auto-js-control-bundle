<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(ExecutionStatus::class)]
final class ExecutionStatusTest extends AbstractEnumTestCase
{
    #[Test]
    public function casesReturnsAllStatuses(): void
    {
        // Act
        $cases = ExecutionStatus::cases();

        // Assert
        $this->assertCount(6, $cases);
        $this->assertContains(ExecutionStatus::PENDING, $cases);
        $this->assertContains(ExecutionStatus::RUNNING, $cases);
        $this->assertContains(ExecutionStatus::SUCCESS, $cases);
        $this->assertContains(ExecutionStatus::FAILED, $cases);
        $this->assertContains(ExecutionStatus::TIMEOUT, $cases);
        $this->assertContains(ExecutionStatus::CANCELLED, $cases);
    }

    #[Test]
    public function valuesAreCorrect(): void
    {
        // Assert
        $this->assertEquals('pending', ExecutionStatus::PENDING->value);
        $this->assertEquals('running', ExecutionStatus::RUNNING->value);
        $this->assertEquals('success', ExecutionStatus::SUCCESS->value);
        $this->assertEquals('failed', ExecutionStatus::FAILED->value);
        $this->assertEquals('timeout', ExecutionStatus::TIMEOUT->value);
        $this->assertEquals('cancelled', ExecutionStatus::CANCELLED->value);
    }

    #[Test]
    public function fromCreatesCorrectEnum(): void
    {
        // Assert
        $this->assertEquals(ExecutionStatus::PENDING, ExecutionStatus::from('pending'));
        $this->assertEquals(ExecutionStatus::RUNNING, ExecutionStatus::from('running'));
        $this->assertEquals(ExecutionStatus::SUCCESS, ExecutionStatus::from('success'));
        $this->assertEquals(ExecutionStatus::FAILED, ExecutionStatus::from('failed'));
        $this->assertEquals(ExecutionStatus::TIMEOUT, ExecutionStatus::from('timeout'));
        $this->assertEquals(ExecutionStatus::CANCELLED, ExecutionStatus::from('cancelled'));
    }

    #[Test]
    public function tryFromReturnsCorrectEnum(): void
    {
        // Valid values
        $this->assertEquals(ExecutionStatus::PENDING, ExecutionStatus::tryFrom('pending'));
        $this->assertEquals(ExecutionStatus::SUCCESS, ExecutionStatus::tryFrom('success'));

        // Invalid value
        $this->assertNull(ExecutionStatus::tryFrom('invalid'));
    }

    #[Test]
    public function getLabelReturnsCorrectLabel(): void
    {
        // Assert
        $this->assertEquals('待执行', ExecutionStatus::PENDING->getLabel());
        $this->assertEquals('执行中', ExecutionStatus::RUNNING->getLabel());
        $this->assertEquals('执行成功', ExecutionStatus::SUCCESS->getLabel());
        $this->assertEquals('执行失败', ExecutionStatus::FAILED->getLabel());
        $this->assertEquals('执行超时', ExecutionStatus::TIMEOUT->getLabel());
        $this->assertEquals('已取消', ExecutionStatus::CANCELLED->getLabel());
    }

    #[Test]
    public function getColorReturnsCorrectColor(): void
    {
        // Assert
        $this->assertEquals('secondary', ExecutionStatus::PENDING->getColor());
        $this->assertEquals('primary', ExecutionStatus::RUNNING->getColor());
        $this->assertEquals('success', ExecutionStatus::SUCCESS->getColor());
        $this->assertEquals('danger', ExecutionStatus::FAILED->getColor());
        $this->assertEquals('warning', ExecutionStatus::TIMEOUT->getColor());
        $this->assertEquals('dark', ExecutionStatus::CANCELLED->getColor());
    }

    #[Test]
    public function isFinalReturnsCorrectValue(): void
    {
        // Non-final states
        $this->assertFalse(ExecutionStatus::PENDING->isFinal());
        $this->assertFalse(ExecutionStatus::RUNNING->isFinal());

        // Final states
        $this->assertTrue(ExecutionStatus::SUCCESS->isFinal());
        $this->assertTrue(ExecutionStatus::FAILED->isFinal());
        $this->assertTrue(ExecutionStatus::TIMEOUT->isFinal());
        $this->assertTrue(ExecutionStatus::CANCELLED->isFinal());
    }

    #[Test]
    public function isSuccessReturnsCorrectValue(): void
    {
        // Non-success states
        $this->assertFalse(ExecutionStatus::PENDING->isSuccess());
        $this->assertFalse(ExecutionStatus::RUNNING->isSuccess());
        $this->assertFalse(ExecutionStatus::FAILED->isSuccess());
        $this->assertFalse(ExecutionStatus::TIMEOUT->isSuccess());
        $this->assertFalse(ExecutionStatus::CANCELLED->isSuccess());

        // Success state
        $this->assertTrue(ExecutionStatus::SUCCESS->isSuccess());
    }

    #[Test]
    public function getItemsReturnsCorrectArray(): void
    {
        // Act
        $items = ExecutionStatus::getItems();

        // Assert
        $expected = [
            'pending' => '待执行',
            'running' => '执行中',
            'success' => '执行成功',
            'failed' => '执行失败',
            'timeout' => '执行超时',
            'cancelled' => '已取消',
        ];
        $this->assertEquals($expected, $items);
    }

    #[Test]
    public function toSelectReturnsCorrectArray(): void
    {
        // Act
        $choices = ExecutionStatus::toSelect();

        // Assert
        $expected = [
            '待执行' => 'pending',
            '执行中' => 'running',
            '执行成功' => 'success',
            '执行失败' => 'failed',
            '执行超时' => 'timeout',
            '已取消' => 'cancelled',
        ];
        $this->assertEquals($expected, $choices);
    }

    #[Test]
    public function getBadgeClassReturnsCorrectClass(): void
    {
        // Assert
        $this->assertEquals('badge-secondary', ExecutionStatus::PENDING->getBadgeClass());
        $this->assertEquals('badge-primary', ExecutionStatus::RUNNING->getBadgeClass());
        $this->assertEquals('badge-success', ExecutionStatus::SUCCESS->getBadgeClass());
        $this->assertEquals('badge-danger', ExecutionStatus::FAILED->getBadgeClass());
        $this->assertEquals('badge-warning', ExecutionStatus::TIMEOUT->getBadgeClass());
        $this->assertEquals('badge-dark', ExecutionStatus::CANCELLED->getBadgeClass());
    }

    #[Test]
    public function isFailureReturnsCorrectValue(): void
    {
        // Non-failure states
        $this->assertFalse(ExecutionStatus::PENDING->isFailure());
        $this->assertFalse(ExecutionStatus::RUNNING->isFailure());
        $this->assertFalse(ExecutionStatus::SUCCESS->isFailure());

        // Failure states
        $this->assertTrue(ExecutionStatus::FAILED->isFailure());
        $this->assertTrue(ExecutionStatus::TIMEOUT->isFailure());
        $this->assertTrue(ExecutionStatus::CANCELLED->isFailure());
    }

    #[Test]
    public function testCanRetryReturnsCorrectValue(): void
    {
        // Non-retryable states
        $this->assertFalse(ExecutionStatus::PENDING->canRetry());
        $this->assertFalse(ExecutionStatus::RUNNING->canRetry());
        $this->assertFalse(ExecutionStatus::SUCCESS->canRetry());

        // Retryable states (failure states)
        $this->assertTrue(ExecutionStatus::FAILED->canRetry());
        $this->assertTrue(ExecutionStatus::TIMEOUT->canRetry());
        $this->assertTrue(ExecutionStatus::CANCELLED->canRetry());
    }

    #[Test]
    public function testToSelectItemReturnsCorrectFormat(): void
    {
        // Test PENDING enum
        $pendingItem = ExecutionStatus::PENDING->toSelectItem();
        $this->assertEquals([
            'label' => '待执行',
            'text' => '待执行',
            'value' => 'pending',
            'name' => '待执行',
        ], $pendingItem);

        // Test SUCCESS enum
        $successItem = ExecutionStatus::SUCCESS->toSelectItem();
        $this->assertEquals([
            'label' => '执行成功',
            'text' => '执行成功',
            'value' => 'success',
            'name' => '执行成功',
        ], $successItem);
    }

    #[Test]
    public function testToArrayReturnsCorrectFormat(): void
    {
        // Test RUNNING enum
        $runningArray = ExecutionStatus::RUNNING->toArray();
        $this->assertEquals([
            'value' => 'running',
            'label' => '执行中',
        ], $runningArray);

        // Test FAILED enum
        $failedArray = ExecutionStatus::FAILED->toArray();
        $this->assertEquals([
            'value' => 'failed',
            'label' => '执行失败',
        ], $failedArray);
    }

    #[Test]
    public function genOptionsReturnsCorrectArray(): void
    {
        // Act
        $options = ExecutionStatus::genOptions();

        // Assert
        $this->assertCount(6, $options);

        // Check first option
        $this->assertEquals([
            'label' => '待执行',
            'text' => '待执行',
            'value' => 'pending',
            'name' => '待执行',
        ], $options[0]);

        // Check all values are present
        $values = array_column($options, 'value');
        $expectedValues = ['pending', 'running', 'success', 'failed', 'timeout', 'cancelled'];
        $this->assertEquals($expectedValues, $values);

        // Check all labels are present
        $labels = array_column($options, 'label');
        $expectedLabels = ['待执行', '执行中', '执行成功', '执行失败', '执行超时', '已取消'];
        $this->assertEquals($expectedLabels, $labels);
    }
}
