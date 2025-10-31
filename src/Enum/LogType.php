<?php

namespace Tourze\AutoJsControlBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 日志类型枚举.
 */
enum LogType: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case SYSTEM = 'system';
    case SCRIPT = 'script';
    case CONNECTION = 'connection';
    case COMMAND = 'command';
    case TASK = 'task';

    public function getLabel(): string
    {
        return match ($this) {
            self::SYSTEM => '系统日志',
            self::SCRIPT => '脚本执行',
            self::CONNECTION => '连接日志',
            self::COMMAND => '命令执行',
            self::TASK => '任务日志',
        };
    }

    /**
     * 获取类型对应的图标.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::SYSTEM => 'settings',
            self::SCRIPT => 'code',
            self::CONNECTION => 'sync_alt',
            self::COMMAND => 'terminal',
            self::TASK => 'assignment',
        };
    }

    /**
     * 获取类型对应的颜色.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::SYSTEM => 'primary',
            self::SCRIPT => 'success',
            self::CONNECTION => 'info',
            self::COMMAND => 'secondary',
            self::TASK => 'warning',
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
     * 判断是否为系统类型.
     */
    public function isSystemType(): bool
    {
        return self::SYSTEM === $this;
    }

    /**
     * 获取选择项数组.
     *
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            '系统日志' => self::SYSTEM->value,
            '脚本执行' => self::SCRIPT->value,
            '连接日志' => self::CONNECTION->value,
            '命令执行' => self::COMMAND->value,
            '任务日志' => self::TASK->value,
        ];
    }
}
