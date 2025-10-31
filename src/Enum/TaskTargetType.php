<?php

namespace Tourze\AutoJsControlBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 任务目标类型枚举.
 */
enum TaskTargetType: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case ALL = 'all';
    case GROUP = 'group';
    case SPECIFIC = 'specific';

    public function getLabel(): string
    {
        return match ($this) {
            self::ALL => '所有设备',
            self::GROUP => '设备分组',
            self::SPECIFIC => '指定设备',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ALL => 'globe',
            self::GROUP => 'object-group',
            self::SPECIFIC => 'crosshairs',
        };
    }

    /**
     * 获取徽章颜色.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::ALL => 'primary',
            self::GROUP => 'info',
            self::SPECIFIC => 'warning',
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
     * 是否需要目标设备列表.
     */
    public function requiresDeviceList(): bool
    {
        return self::SPECIFIC === $this;
    }

    /**
     * 是否需要设备分组.
     */
    public function requiresGroup(): bool
    {
        return self::GROUP === $this;
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::ALL => '在所有可用设备上执行',
            self::GROUP => '在指定设备组上执行',
            self::SPECIFIC => '在特定设备上执行',
        };
    }

    public function requiresTarget(): bool
    {
        return in_array($this, [self::GROUP, self::SPECIFIC], true);
    }

    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            '所有设备' => 'all',
            '设备组' => 'group',
            '指定设备' => 'specific',
        ];
    }
}
