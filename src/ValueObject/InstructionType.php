<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\ValueObject;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 指令类型枚举.
 */
enum InstructionType: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;
    case EXECUTE_SCRIPT = 'execute_script';
    case STOP_SCRIPT = 'stop_script';
    case UPDATE_STATUS = 'update_status';
    case COLLECT_LOG = 'collect_log';
    case RESTART_APP = 'restart_app';
    case UPDATE_APP = 'update_app';
    case PING = 'ping';

    public function getLabel(): string
    {
        return match ($this) {
            self::EXECUTE_SCRIPT => '执行脚本',
            self::STOP_SCRIPT => '停止脚本',
            self::UPDATE_STATUS => '更新状态',
            self::COLLECT_LOG => '收集日志',
            self::RESTART_APP => '重启应用',
            self::UPDATE_APP => '更新应用',
            self::PING => '心跳检测',
        };
    }

    public function isUrgent(): bool
    {
        return match ($this) {
            self::STOP_SCRIPT, self::RESTART_APP, self::PING => true,
            default => false,
        };
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
     * @return array<int, array{value: string, text: string}>
     */
    public static function getSelectItems(): array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[] = [
                'value' => $case->value,
                'text' => $case->getLabel(),
            ];
        }

        return $items;
    }
}
