<?php

namespace Tourze\AutoJsControlBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 执行状态枚举.
 */
enum ExecutionStatus: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case TIMEOUT = 'timeout';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待执行',
            self::RUNNING => '执行中',
            self::SUCCESS => '执行成功',
            self::FAILED => '执行失败',
            self::TIMEOUT => '执行超时',
            self::CANCELLED => '已取消',
        };
    }

    /**
     * 获取状态颜色.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'secondary',
            self::RUNNING => 'primary',
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
            self::TIMEOUT => 'warning',
            self::CANCELLED => 'dark',
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
     * 获取徽章样式类.
     */
    public function getBadgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'badge-secondary',
            self::RUNNING => 'badge-primary',
            self::SUCCESS => 'badge-success',
            self::FAILED => 'badge-danger',
            self::TIMEOUT => 'badge-warning',
            self::CANCELLED => 'badge-dark',
        };
    }

    /**
     * 是否为终态
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::SUCCESS,
            self::FAILED,
            self::TIMEOUT,
            self::CANCELLED,
        ], true);
    }

    /**
     * 是否为成功状态
     */
    public function isSuccess(): bool
    {
        return self::SUCCESS === $this;
    }

    /**
     * 是否为失败状态（包括失败、超时、取消）.
     */
    public function isFailure(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::TIMEOUT,
            self::CANCELLED,
        ], true);
    }

    /**
     * 是否可以重试.
     */
    public function canRetry(): bool
    {
        return $this->isFailure();
    }

    /**
     * @return array<string, string>
     */
    public static function getItems(): array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[$case->value] = $case->getLabel();
        }

        return $items;
    }

    /**
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
