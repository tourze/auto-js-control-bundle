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
 * 任务类型枚举.
 */
enum TaskType: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case IMMEDIATE = 'immediate';
    case SCHEDULED = 'scheduled';
    case RECURRING = 'recurring';

    public function getLabel(): string
    {
        return match ($this) {
            self::IMMEDIATE => '立即执行',
            self::SCHEDULED => '定时执行',
            self::RECURRING => '循环执行',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::IMMEDIATE => 'bolt',
            self::SCHEDULED => 'clock',
            self::RECURRING => 'sync',
        };
    }

    /**
     * 获取徽章颜色.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::IMMEDIATE => 'danger',
            self::SCHEDULED => 'primary',
            self::RECURRING => 'success',
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
     * 是否需要调度时间.
     */
    public function requiresSchedule(): bool
    {
        return in_array($this, [self::SCHEDULED, self::RECURRING], true);
    }

    /**
     * 获取选项数组.
     *
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            '立即执行' => 'immediate',
            '定时执行' => 'scheduled',
            '循环执行' => 'recurring',
        ];
    }

    /**
     * 是否需要Cron表达式.
     */
    public function requiresCron(): bool
    {
        return self::RECURRING === $this;
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::IMMEDIATE => '创建后立即执行',
            self::SCHEDULED => '在指定时间执行一次',
            self::RECURRING => '按照设定的规则重复执行',
        };
    }
}
