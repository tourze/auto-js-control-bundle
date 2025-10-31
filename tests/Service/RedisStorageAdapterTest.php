<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Service\RedisStorageAdapter;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RedisStorageAdapter::class)]
#[RunTestsInSeparateProcesses]
final class RedisStorageAdapterTest extends AbstractIntegrationTestCase
{
    private RedisStorageAdapter $adapter;

    private \Redis $redis;

    protected function onSetUp(): void
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->redis->select(15); // 使用测试数据库
        $this->redis->flushDB(); // 清空测试数据

        self::getContainer()->set('redis', $this->redis);
        $this->adapter = self::getService(RedisStorageAdapter::class);
    }

    protected function onTearDown(): void
    {
        $this->redis->flushDB();
    }

    #[Test]
    public function testSetAndGet(): void
    {
        // Arrange
        $key = 'test_key';
        $value = 'test_value';

        // Act
        $this->adapter->set($key, $value);
        $result = $this->adapter->get($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function testSetWithTtl(): void
    {
        // Arrange
        $key = 'test_key_ttl';
        $value = 'test_value';
        $ttl = 2; // 2秒后过期

        // Act
        $this->adapter->set($key, $value, $ttl);
        $result = $this->adapter->get($key);

        // Assert - 立即获取应该成功
        $this->assertEquals($value, $result);

        // 验证TTL设置
        $actualTtl = $this->redis->ttl($key);
        $this->assertGreaterThan(0, $actualTtl);
        $this->assertLessThanOrEqual($ttl, $actualTtl);
    }

    #[Test]
    public function testGetWithDefault(): void
    {
        // Arrange
        $key = 'nonexistent_key';
        $default = 'default_value';

        // Act
        $result = $this->adapter->get($key, $default);

        // Assert
        $this->assertEquals($default, $result);
    }

    public function testDelete(): void
    {
        // Arrange
        $key = 'test_key_delete';
        $value = 'test_value';
        $this->adapter->set($key, $value);

        // Act
        $deleted = $this->adapter->delete($key);
        $result = $this->adapter->get($key);

        // Assert
        $this->assertTrue($deleted);
        $this->assertNull($result);
    }

    public function testHashOperations(): void
    {
        // Arrange
        $key = 'hash_key';
        $field1 = 'field1';
        $value1 = 'value1';
        $field2 = 'field2';
        $value2 = 'value2';

        // Act - hSet
        $this->adapter->hSet($key, $field1, $value1);
        $result1 = $this->adapter->hGet($key, $field1);

        // Assert
        $this->assertEquals($value1, $result1);

        // Act - hMSet
        $this->adapter->hMSet($key, [$field2 => $value2]);
        $allData = $this->adapter->hGetAll($key);

        // Assert
        $this->assertArrayHasKey($field1, $allData);
        $this->assertArrayHasKey($field2, $allData);
        $this->assertEquals($value1, $allData[$field1]);
        $this->assertEquals($value2, $allData[$field2]);
    }

    public function testListOperations(): void
    {
        // Arrange
        $key = 'list_key';
        $value1 = 'value1';
        $value2 = 'value2';
        $value3 = 'value3';

        // Act - rPush
        $length1 = $this->adapter->rPush($key, $value1);
        $this->assertEquals(1, $length1);

        // Act - lPush
        $length2 = $this->adapter->lPush($key, $value2);
        $this->assertEquals(2, $length2);

        // Act - rPush again
        $length3 = $this->adapter->rPush($key, $value3);
        $this->assertEquals(3, $length3);

        // Act - lLen
        $length = $this->adapter->lLen($key);
        $this->assertEquals(3, $length);

        // Act - lRange (全部元素)
        $range = $this->adapter->lRange($key, 0, -1);
        $this->assertEquals([$value2, $value1, $value3], $range);

        // Act - lRange (部分元素)
        $partialRange = $this->adapter->lRange($key, 0, 1);
        $this->assertEquals([$value2, $value1], $partialRange);

        // Act - rPop
        $popped = $this->adapter->rPop($key);
        $this->assertEquals($value3, $popped);

        // Verify length after pop
        $this->assertEquals(2, $this->adapter->lLen($key));
    }

    public function testPublish(): void
    {
        // Arrange
        $channel = 'test_channel';
        $message = 'test_message';

        // Act
        $receivers = $this->adapter->publish($channel, $message);

        // Assert - 没有订阅者时返回0
        $this->assertEquals(0, $receivers);
    }

    public function testExpire(): void
    {
        // Arrange
        $key = 'expire_key';
        $value = 'test_value';
        $this->adapter->set($key, $value);

        // Act
        $result = $this->adapter->expire($key, 10);

        // Assert
        $this->assertTrue($result);

        // 验证TTL被设置
        $ttl = $this->redis->ttl($key);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(10, $ttl);
    }

    public function testExpireOnNonExistentKey(): void
    {
        // Arrange
        $key = 'nonexistent_expire_key';

        // Act
        $result = $this->adapter->expire($key, 10);

        // Assert
        $this->assertFalse($result);
    }

    public function testExists(): void
    {
        // Arrange
        $key = 'exists_key';
        $value = 'test_value';

        // Act & Assert - 不存在
        $this->assertFalse($this->adapter->exists($key));

        // Act & Assert - 存在
        $this->adapter->set($key, $value);
        $this->assertTrue($this->adapter->exists($key));

        // Act & Assert - 删除后不存在
        $this->adapter->delete($key);
        $this->assertFalse($this->adapter->exists($key));
    }

    public function testEmptyListOperations(): void
    {
        // Arrange
        $key = 'empty_list';

        // Act & Assert - 空列表长度为0
        $this->assertEquals(0, $this->adapter->lLen($key));

        // Act & Assert - 空列表rPop返回false
        $this->assertFalse($this->adapter->rPop($key));

        // Act & Assert - 空列表lRange返回空数组
        $this->assertEquals([], $this->adapter->lRange($key, 0, -1));
    }

    public function testHashGetNonExistentField(): void
    {
        // Arrange
        $key = 'hash_key';
        $field = 'nonexistent_field';

        // Act
        $result = $this->adapter->hGet($key, $field);

        // Assert
        $this->assertFalse($result);
    }

    public function testDeleteNonExistentKey(): void
    {
        // Arrange
        $key = 'nonexistent_key';

        // Act
        $result = $this->adapter->delete($key);

        // Assert - Redis del 返回0表示没有删除任何键
        $this->assertFalse($result);
    }

    #[Test]
    public function testHSet(): void
    {
        // Arrange
        $key = 'hash_key';
        $field = 'field1';
        $value = 'value1';

        // Act
        $this->adapter->hSet($key, $field, $value);
        $result = $this->adapter->hGet($key, $field);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function testHGet(): void
    {
        // Arrange
        $key = 'hash_key';
        $field = 'field1';
        $value = 'value1';
        $this->adapter->hSet($key, $field, $value);

        // Act
        $result = $this->adapter->hGet($key, $field);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function testHMSet(): void
    {
        // Arrange
        $key = 'hash_key';
        $data = ['field1' => 'value1', 'field2' => 'value2'];

        // Act
        $this->adapter->hMSet($key, $data);
        $result = $this->adapter->hGetAll($key);

        // Assert
        $this->assertEquals($data, $result);
    }

    #[Test]
    public function testHGetAll(): void
    {
        // Arrange
        $key = 'hash_key';
        $data = ['field1' => 'value1', 'field2' => 'value2'];
        $this->adapter->hMSet($key, $data);

        // Act
        $result = $this->adapter->hGetAll($key);

        // Assert
        $this->assertEquals($data, $result);
    }

    #[Test]
    public function testLPush(): void
    {
        // Arrange
        $key = 'list_key';
        $value = 'value1';

        // Act
        $result = $this->adapter->lPush($key, $value);

        // Assert
        $this->assertEquals(1, $result);
    }

    #[Test]
    public function testRPush(): void
    {
        // Arrange
        $key = 'list_key';
        $value = 'value1';

        // Act
        $result = $this->adapter->rPush($key, $value);

        // Assert
        $this->assertEquals(1, $result);
    }

    #[Test]
    public function testRPop(): void
    {
        // Arrange
        $key = 'list_key';
        $value = 'value1';
        $this->adapter->rPush($key, $value);

        // Act
        $result = $this->adapter->rPop($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function testLLen(): void
    {
        // Arrange
        $key = 'list_key';
        $this->adapter->rPush($key, 'value1');
        $this->adapter->rPush($key, 'value2');

        // Act
        $result = $this->adapter->lLen($key);

        // Assert
        $this->assertEquals(2, $result);
    }

    #[Test]
    public function testLRange(): void
    {
        // Arrange
        $key = 'list_key';
        $this->adapter->rPush($key, 'value1');
        $this->adapter->rPush($key, 'value2');

        // Act
        $result = $this->adapter->lRange($key, 0, -1);

        // Assert
        $this->assertEquals(['value1', 'value2'], $result);
    }

    #[Test]
    public function testSubscribe(): void
    {
        // Arrange
        $channel = 'test_channel';

        // Act & Assert - 验证 subscribe 方法存在
        // 注意：实际的 subscribe 测试需要更复杂的设置，因为它是阻塞操作
        $reflection = new \ReflectionClass($this->adapter);
        $this->assertTrue($reflection->hasMethod('subscribe'));
    }
}
