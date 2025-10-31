<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Service\InMemoryStorageAdapter;

/**
 * @internal
 */
#[CoversClass(InMemoryStorageAdapter::class)]
final class InMemoryStorageAdapterTest extends TestCase
{
    private InMemoryStorageAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryStorageAdapter();
    }

    #[Test]
    public function testSetAndGet(): void
    {
        // Act
        $this->adapter->set('key1', 'value1');
        $result = $this->adapter->get('key1');

        // Assert
        $this->assertEquals('value1', $result);
    }

    #[Test]
    public function testGetNonExistent(): void
    {
        // Act
        $result = $this->adapter->get('nonexistent');

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function testGetWithDefault(): void
    {
        // Act
        $result = $this->adapter->get('nonexistent', 'default');

        // Assert
        $this->assertEquals('default', $result);
    }

    #[Test]
    public function testSetWithTtl(): void
    {
        // Act
        $this->adapter->set('key1', 'value1', 1);
        $this->assertEquals('value1', $this->adapter->get('key1'));

        sleep(2);

        // Assert - 键应该已过期
        $this->assertNull($this->adapter->get('key1'));
    }

    #[Test]
    public function testDelete(): void
    {
        // Arrange
        $this->adapter->set('key1', 'value1');

        // Act
        $result = $this->adapter->delete('key1');

        // Assert
        $this->assertTrue($result);
        $this->assertNull($this->adapter->get('key1'));
    }

    #[Test]
    public function testDeleteNonExistent(): void
    {
        // Act
        $result = $this->adapter->delete('nonexistent');

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function testHSetAndHGet(): void
    {
        // Act
        $this->adapter->hSet('hash1', 'field1', 'value1');
        $result = $this->adapter->hGet('hash1', 'field1');

        // Assert
        $this->assertEquals('value1', $result);
    }

    #[Test]
    public function testHGetNonExistent(): void
    {
        // Act
        $result = $this->adapter->hGet('hash1', 'field1');

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function testHMSetAndHGetAll(): void
    {
        // Arrange
        $data = [
            'field1' => 'value1',
            'field2' => 'value2',
            'field3' => 'value3',
        ];

        // Act
        $this->adapter->hMSet('hash1', $data);
        $result = $this->adapter->hGetAll('hash1');

        // Assert
        $this->assertEquals($data, $result);
    }

    #[Test]
    public function testHGetAllNonExistent(): void
    {
        // Act
        $result = $this->adapter->hGetAll('nonexistent');

        // Assert
        $this->assertEquals([], $result);
    }

    #[Test]
    public function testLPush(): void
    {
        // Act
        $len1 = $this->adapter->lPush('list1', 'value1');
        $len2 = $this->adapter->lPush('list1', 'value2');

        // Assert
        $this->assertEquals(1, $len1);
        $this->assertEquals(2, $len2);
    }

    #[Test]
    public function testRPush(): void
    {
        // Act
        $len1 = $this->adapter->rPush('list1', 'value1');
        $len2 = $this->adapter->rPush('list1', 'value2');

        // Assert
        $this->assertEquals(1, $len1);
        $this->assertEquals(2, $len2);
    }

    #[Test]
    public function testRPop(): void
    {
        // Arrange
        $this->adapter->rPush('list1', 'value1');
        $this->adapter->rPush('list1', 'value2');

        // Act
        $value1 = $this->adapter->rPop('list1');
        $value2 = $this->adapter->rPop('list1');
        $value3 = $this->adapter->rPop('list1');

        // Assert
        $this->assertEquals('value2', $value1);
        $this->assertEquals('value1', $value2);
        $this->assertFalse($value3);
    }

    #[Test]
    public function testLLen(): void
    {
        // Arrange
        $this->adapter->rPush('list1', 'value1');
        $this->adapter->rPush('list1', 'value2');

        // Act
        $len = $this->adapter->lLen('list1');

        // Assert
        $this->assertEquals(2, $len);
    }

    #[Test]
    public function testLLenNonExistent(): void
    {
        // Act
        $len = $this->adapter->lLen('nonexistent');

        // Assert
        $this->assertEquals(0, $len);
    }

    #[Test]
    public function testLRange(): void
    {
        // Arrange
        $this->adapter->rPush('list1', 'value1');
        $this->adapter->rPush('list1', 'value2');
        $this->adapter->rPush('list1', 'value3');

        // Act
        $result = $this->adapter->lRange('list1', 0, 1);

        // Assert
        $this->assertEquals(['value1', 'value2'], $result);
    }

    #[Test]
    public function testLRangeWithNegativeIndex(): void
    {
        // Arrange
        $this->adapter->rPush('list1', 'value1');
        $this->adapter->rPush('list1', 'value2');
        $this->adapter->rPush('list1', 'value3');

        // Act
        $result = $this->adapter->lRange('list1', -2, -1);

        // Assert
        $this->assertEquals(['value2', 'value3'], $result);
    }

    #[Test]
    public function testExpire(): void
    {
        // Arrange
        $this->adapter->set('key1', 'value1');

        // Act
        $result = $this->adapter->expire('key1', 1);
        $this->assertTrue($result);

        sleep(2);

        // Assert - 键应该已过期
        $this->assertNull($this->adapter->get('key1'));
    }

    #[Test]
    public function testExpireNonExistent(): void
    {
        // Act
        $result = $this->adapter->expire('nonexistent', 1);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function testExists(): void
    {
        // Arrange
        $this->adapter->set('key1', 'value1');

        // Act & Assert
        $this->assertTrue($this->adapter->exists('key1'));
        $this->assertFalse($this->adapter->exists('nonexistent'));
    }

    #[Test]
    public function testTtl(): void
    {
        // Arrange
        $this->adapter->set('key1', 'value1', 10);

        // Act
        $ttl = $this->adapter->ttl('key1');

        // Assert
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(10, $ttl);
    }

    #[Test]
    public function testTtlNonExistent(): void
    {
        // Act
        $ttl = $this->adapter->ttl('nonexistent');

        // Assert
        $this->assertEquals(-2, $ttl);
    }

    #[Test]
    public function testTtlNoExpiry(): void
    {
        // Arrange
        $this->adapter->set('key1', 'value1');

        // Act
        $ttl = $this->adapter->ttl('key1');

        // Assert
        $this->assertEquals(-1, $ttl);
    }

    #[Test]
    public function testFlushAll(): void
    {
        // Arrange
        $this->adapter->set('key1', 'value1');
        $this->adapter->set('key2', 'value2');

        // Act
        $this->adapter->flushAll();

        // Assert
        $this->assertNull($this->adapter->get('key1'));
        $this->assertNull($this->adapter->get('key2'));
    }

    #[Test]
    public function testPublish(): void
    {
        // Act
        $subscribers = $this->adapter->publish('channel1', 'message1');

        // Assert - 内存实现不支持发布订阅，应返回 0
        $this->assertEquals(0, $subscribers);
    }

    #[Test]
    public function testSubscribe(): void
    {
        // Act & Assert - 不应抛出异常
        $callbackCalled = false;
        $callback = function () use (&$callbackCalled): void {
            $callbackCalled = true;
        };

        $this->adapter->subscribe(['channel1'], $callback);

        // 内存实现不支持发布订阅，callback 不应被调用
        $this->assertFalse($callbackCalled);
    }
}
