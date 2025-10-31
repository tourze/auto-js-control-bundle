<?php

namespace Tourze\AutoJsControlBundle\Handler;

use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

/**
 * 指令处理器接口.
 */
interface InstructionHandlerInterface
{
    /**
     * 处理指令.
     */
    public function handle(DeviceInstruction $instruction): void;

    /**
     * 获取支持的指令类型.
     */
    public function getSupportedType(): string;
}
