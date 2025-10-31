<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Command;

/**
 * 提供输入参数的类型安全转换方法
 */
trait InputSanitizerTrait
{
    /**
     * 安全地将混合类型转换为字符串
     */
    private function safelyParseString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * 安全地将混合类型转换为整数
     */
    private function safelyParseInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * 安全地将混合类型转换为布尔值
     */
    private function safelyParseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return $value > 0;
        }

        return false;
    }

    /**
     * 清理字符串选项，提供默认值
     */
    private function sanitizeStringOption(mixed $option, string $default): string
    {
        return is_string($option) ? $option : $default;
    }

    /**
     * 清理整数选项，提供默认值
     */
    private function sanitizeIntOption(mixed $option, int $default): int
    {
        return is_numeric($option) ? (int) $option : $default;
    }
}
