<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tourze\AutoJsControlBundle\Service\CacheStorageAdapter;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CacheStorageAdapter::class)]
#[RunTestsInSeparateProcesses]
final class CacheStorageAdapterTest extends AbstractIntegrationTestCase
{
    private CacheStorageAdapter $adapter;

    private ArrayAdapter $cache;

    private ?\Redis $redis;

    protected function onSetUp(): void
    {
        $this->cache = new ArrayAdapter();

        // 尝试连接 Redis，如果可用则使用
        try {
            $this->redis = new \Redis();
            $this->redis->connect('127.0.0.1', 6379);
            $this->redis->select(15);
            $this->redis->flushDB();
        } catch (\Exception $e) {
            $this->redis = null;
        }

        self::getContainer()->set('cache.adapter', $this->cache);
        if ($this->redis instanceof \Redis) {
            self::getContainer()->set('redis', $this->redis);
        }

        $this->adapter = self::getService(CacheStorageAdapter::class);
    }

    protected function onTearDown(): void
    {
        if ($this->redis instanceof \Redis) {
            $this->redis->flushDB();
        }
    }

    #[Test]
    public function testSetAndGet(): void
    {
        // Arrange
        $key = 'test_key_' . uniqid();
        $value = 'test_value';

        // 确保键不存在
        $this->adapter->delete($key);

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
        $key = 'test_key_ttl_' . uniqid();
        $value = 'test_value';
        $ttl = 3600;

        // 确保键不存在
        $this->adapter->delete($key);

        // Act
        $this->adapter->set($key, $value, $ttl);
        $result = $this->adapter->get($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function testGetWithDefault(): void
    {
        // Arrange
        $key = 'nonexistent_key_' . uniqid();
        $default = 'default_value';

        // 确保键不存在
        $this->adapter->delete($key);

        // Act
        $result = $this->adapter->get($key, $default);

        // Assert
        $this->assertEquals($default, $result);
    }

    #[Test]
    public function testDelete(): void
    {
        // Arrange
        $key = 'test_key_delete_' . uniqid();
        $value = 'test_value';

        // 确保键不存在
        $this->adapter->delete($key);

        $this->adapter->set($key, $value);

        // Act
        $deleted = $this->adapter->delete($key);
        $result = $this->adapter->get($key);

        // Assert
        $this->assertTrue($deleted);
        $this->assertNull($result);
    }

    #[Test]
    public function testHSet(): void
    {
        // Arrange
        $key = 'hash_key_' . uniqid();
        $field = 'field1';
        $value = 'value1';

        // 确保键不存在
        $this->adapter->delete($key);

        // Act
        $this->adapter->hSet($key, $field, $value);

        // Assert
        $this->assertEquals($value, $this->adapter->hGet($key, $field));
    }

    #[Test]
    public function testHGet(): void
    {
        // Arrange
        $key = 'hash_key_' . uniqid();
        $field = 'field1';
        $value = 'value1';

        // 确保键不存在
        $this->adapter->delete($key);

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
        $key = 'hash_key_' . uniqid();
        $data = ['field1' => 'value1', 'field2' => 'value2'];

        // 确保键不存在
        $this->adapter->delete($key);

        // Act
        $this->adapter->hMSet($key, $data);

        // Assert
        $this->assertEquals($data, $this->adapter->hGetAll($key));
    }

    #[Test]
    public function testHGetAll(): void
    {
        // Arrange
        $key = 'hash_key_' . uniqid();
        $data = ['field1' => 'value1', 'field2' => 'value2'];

        // 确保键不存在
        $this->adapter->delete($key);

        $this->adapter->hMSet($key, $data);

        // Act
        $result = $this->adapter->hGetAll($key);

        // Assert
        $this->assertEquals($data, $result);
    }

    #[Test]
    public function testRPush(): void
    {
        // Arrange
        $key = 'rpush_list_key_' . uniqid();
        $value = 'value1';

        // 确保键不存在
        $this->adapter->delete($key);

        // 验证列表为空
        $this->assertEquals(0, $this->adapter->lLen($key));

        // Act
        $result = $this->adapter->rPush($key, $value);

        // Assert
        $this->assertEquals(1, $result);
        $this->assertEquals(1, $this->adapter->lLen($key));
    }

    #[Test]
    public function testLPush(): void
    {
        // Arrange
        $key = 'lpush_list_key_' . uniqid();
        $value = 'value1';

        // 确保键不存在
        $this->adapter->delete($key);

        // 验证列表为空
        $this->assertEquals(0, $this->adapter->lLen($key));

        // Act
        $result = $this->adapter->lPush($key, $value);

        // Assert
        $this->assertEquals(1, $result);
        $this->assertEquals(1, $this->adapter->lLen($key));
    }

    #[Test]
    public function testLLen(): void
    {
        // Arrange
        $key = 'llen_list_key_' . uniqid();

        // 确保键不存在
        $this->adapter->delete($key);

        // 验证空列表长度为0
        $this->assertEquals(0, $this->adapter->lLen($key));

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
        $key = 'lrange_list_key_' . uniqid();

        // 确保键不存在
        $this->adapter->delete($key);

        // 验证列表为空
        $this->assertEquals(0, $this->adapter->lLen($key));

        $this->adapter->rPush($key, 'value1');
        $this->adapter->rPush($key, 'value2');

        // Act
        $result = $this->adapter->lRange($key, 0, -1);

        // Assert
        $this->assertEquals(['value1', 'value2'], $result);
    }

    #[Test]
    public function testRPop(): void
    {
        // Arrange
        $key = 'rpop_list_key_' . uniqid();
        $value = 'value1';

        // 确保键不存在
        $this->adapter->delete($key);

        // 验证列表为空
        $this->assertEquals(0, $this->adapter->lLen($key));

        $this->adapter->rPush($key, $value);

        // Act
        $result = $this->adapter->rPop($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function testPublish(): void
    {
        // Arrange
        $channel = 'test_channel_' . uniqid();
        $message = 'test_message';

        // Act
        $receivers = $this->adapter->publish($channel, $message);

        // Assert
        $this->assertIsInt($receivers);
    }

    #[Test]
    public function testSubscribe(): void
    {
        // Arrange
        $channel = 'test_channel_' . uniqid();

        // Act & Assert - 验证 subscribe 方法存在
        // 注意：实际的 subscribe 测试需要更复杂的设置，因为它是阻塞操作
        $reflection = new \ReflectionClass($this->adapter);
        $this->assertTrue($reflection->hasMethod('subscribe'));
    }

    #[Test]
    public function testExpire(): void
    {
        // Arrange
        $key = 'expire_key_' . uniqid();
        $value = 'test_value';

        // 确保键不存在
        $this->adapter->delete($key);

        $this->adapter->set($key, $value);

        // Act
        $result = $this->adapter->expire($key, 3600);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function testExists(): void
    {
        // Arrange
        $key = 'exists_key_' . uniqid();
        $value = 'test_value';

        // 确保键不存在
        $this->adapter->delete($key);

        // Act & Assert - 不存在
        $this->assertFalse($this->adapter->exists($key));

        // Act & Assert - 存在
        $this->adapter->set($key, $value);
        $this->assertTrue($this->adapter->exists($key));
    }

    #[Test]
    public function testConvertKeyWithSpecialCharacters(): void
    {
        // Arrange - 包含不允许字符的键
        $key = 'test:key{with}(special)/chars@email.com_' . uniqid();
        $value = 'test_value';

        // 确保键不存在
        $this->adapter->delete($key);

        // Act
        $this->adapter->set($key, $value);
        $result = $this->adapter->get($key);

        // Assert - 应该能正常工作
        $this->assertEquals($value, $result);
    }
}
