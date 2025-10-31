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
 * 脚本状态枚举.
 */
enum ScriptStatus: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case DRAFT = 'draft';
    case TESTING = 'testing';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DEPRECATED = 'deprecated';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => '草稿',
            self::TESTING => '测试中',
            self::ACTIVE => '激活',
            self::INACTIVE => '未激活',
            self::DEPRECATED => '已废弃',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::DRAFT => '脚本正在编写中，尚未完成',
            self::TESTING => '脚本正在测试阶段',
            self::ACTIVE => '脚本已激活，可以正常使用',
            self::INACTIVE => '脚本暂时停用',
            self::DEPRECATED => '脚本已废弃，不建议使用',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::TESTING => 'warning',
            self::ACTIVE => 'success',
            self::INACTIVE => 'light',
            self::DEPRECATED => 'danger',
        };
    }

    /**
     * 获取忽章样式.
     */
    public function getBadge(): string
    {
        return $this->getColor();
    }

    /**
     * 是否可以执行.
     */
    public function isExecutable(): bool
    {
        return in_array($this, [self::TESTING, self::ACTIVE], true);
    }
}
