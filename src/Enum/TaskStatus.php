<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 任务状态枚举.
 */
enum TaskStatus: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case PENDING = 'pending';
    case SCHEDULED = 'scheduled';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case PARTIALLY_COMPLETED = 'partially_completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待执行',
            self::SCHEDULED => '已计划',
            self::RUNNING => '执行中',
            self::PAUSED => '已暂停',
            self::COMPLETED => '已完成',
            self::PARTIALLY_COMPLETED => '部分完成',
            self::FAILED => '执行失败',
            self::CANCELLED => '已取消',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'secondary',
            self::SCHEDULED => 'info',
            self::RUNNING => 'primary',
            self::PAUSED => 'info',
            self::COMPLETED => 'success',
            self::PARTIALLY_COMPLETED => 'warning',
            self::FAILED => 'danger',
            self::CANCELLED => 'warning',
        };
    }

    /**
     * 获取徽章样式.
     */
    public function getBadge(): string
    {
        return $this->getColor();
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'badge-secondary',
            self::SCHEDULED => 'badge-info',
            self::RUNNING => 'badge-primary',
            self::PAUSED => 'badge-info',
            self::COMPLETED => 'badge-success',
            self::PARTIALLY_COMPLETED => 'badge-warning',
            self::FAILED => 'badge-danger',
            self::CANCELLED => 'badge-warning',
        };
    }

    /**
     * 是否为终态
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::PARTIALLY_COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    /**
     * 是否可以取消.
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::PENDING, self::SCHEDULED, self::RUNNING, self::PAUSED], true);
    }

    /**
     * 是否可以重试.
     */
    public function canRetry(): bool
    {
        return in_array($this, [self::FAILED, self::CANCELLED], true);
    }
}
