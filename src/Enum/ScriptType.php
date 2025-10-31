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
 * 脚本类型枚举.
 */
enum ScriptType: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case JAVASCRIPT = 'javascript';
    case AUTO_JS = 'auto_js';
    case SHELL = 'shell';

    public function getLabel(): string
    {
        return match ($this) {
            self::JAVASCRIPT => 'JavaScript',
            self::AUTO_JS => 'Auto.js脚本',
            self::SHELL => 'Shell脚本',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::JAVASCRIPT => '标准JavaScript脚本',
            self::AUTO_JS => 'Auto.js专用脚本，支持Auto.js API',
            self::SHELL => 'Shell命令脚本',
        };
    }

    public function getFileExtension(): string
    {
        return match ($this) {
            self::JAVASCRIPT => 'js',
            self::AUTO_JS => 'js',
            self::SHELL => 'sh',
        };
    }

    /**
     * 获取忽章颜色.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::JAVASCRIPT => 'warning',
            self::AUTO_JS => 'success',
            self::SHELL => 'secondary',
        };
    }

    /**
     * 获取忽章样式.
     */
    public function getBadge(): string
    {
        return $this->getColor();
    }
}
