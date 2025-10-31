<?php

namespace Tourze\AutoJsControlBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 日志级别枚举.
 */
enum LogLevel: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::DEBUG => '调试',
            self::INFO => '信息',
            self::WARNING => '警告',
            self::ERROR => '错误',
            self::CRITICAL => '严重',
        };
    }

    /**
     * 获取颜色.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::DEBUG => 'secondary',
            self::INFO => 'info',
            self::WARNING => 'warning',
            self::ERROR => 'danger',
            self::CRITICAL => 'dark',
        };
    }

    /**
     * 获取徽章样式.
     */
    public function getBadge(): string
    {
        return $this->getColor();
    }

    /**
     * 获取优先级.
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::DEBUG => 100,
            self::INFO => 200,
            self::WARNING => 300,
            self::ERROR => 400,
            self::CRITICAL => 500,
        };
    }

    /**
     * 是否为错误级别.
     */
    public function isErrorLevel(): bool
    {
        return in_array($this, [self::ERROR, self::CRITICAL], true);
    }

    /**
     * 获取选择项数组.
     *
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            '调试' => 'debug',
            '信息' => 'info',
            '警告' => 'warning',
            '错误' => 'error',
            '严重' => 'critical',
        ];
    }

    /**
     * 比较优先级.
     */
    public function compareTo(self $other): int
    {
        return $this->getPriority() <=> $other->getPriority();
    }

    /**
     * 获取图标.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::DEBUG => 'bug_report',
            self::INFO => 'info',
            self::WARNING => 'warning',
            self::ERROR => 'error',
            self::CRITICAL => 'report',
        };
    }

    /**
     * 获取权重.
     */
    public function getWeight(): int
    {
        return $this->getPriority();
    }

    /**
     * 是否为错误.
     */
    public function isError(): bool
    {
        return $this->isErrorLevel();
    }

    /**
     * 获取选择数组.
     *
     * @return array<string, string>
     */
    public static function toSelect(): array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[$case->getLabel()] = $case->value;
        }

        return $items;
    }
}
