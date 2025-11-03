<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\DbalType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BigIntType;

/**
 * 测试环境专用的 BigInt 类型.
 *
 * 解决 SQLite 在测试环境中返回整数而非字符串的问题
 * Doctrine DBAL 期望 bigint 类型的值以字符串形式返回，以避免 PHP 整数溢出
 */
class TestBigIntType extends BigIntType
{
    /**
     * 将数据库值转换为 PHP 值.
     *
     * 在测试环境（通常使用 SQLite）中，确保 bigint 值总是以字符串形式返回
     *
     * @param mixed $value
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        // 将整数转换为字符串，确保与生产环境行为一致
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        // 处理其他类型，确保总是返回字符串
        if (is_scalar($value)) {
            return (string) $value;
        }

        // 对于对象等其他类型，转换为JSON字符串
        $jsonResult = json_encode($value);
        return $jsonResult !== false ? $jsonResult : '';
    }
}
