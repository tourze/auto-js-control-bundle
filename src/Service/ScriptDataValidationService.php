<?php

namespace Tourze\AutoJsControlBundle\Service;

use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;

/**
 * 脚本数据验证服务
 *
 * 负责验证脚本创建和更新时的数据
 */
class ScriptDataValidationService
{
    /**
     * 验证创建脚本的数据.
     *
     * @param array<string, mixed> $data
     */
    public function validateCreateData(array $data): void
    {
        $required = ['code', 'name', 'scriptType'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || '' === $data[$field]) {
                throw BusinessLogicException::invalidRequest("字段 {$field} 不能为空");
            }
        }
    }

    /**
     * 验证脚本类型.
     */
    public function parseScriptType(string $scriptType): ScriptType
    {
        try {
            return ScriptType::from($scriptType);
        } catch (\ValueError $e) {
            throw BusinessLogicException::invalidRequest('无效的脚本类型');
        }
    }

    /**
     * 验证脚本内容.
     *
     * @param array<string, mixed> $data
     */
    public function validateScriptContent(Script $script, array $data): void
    {
        if (ScriptType::JAVASCRIPT === $script->getScriptType()) {
            if (!isset($data['content']) || '' === $data['content']) {
                throw BusinessLogicException::invalidRequest('JavaScript脚本必须提供内容');
            }
        } elseif (ScriptType::AUTO_JS === $script->getScriptType()) {
            if (!isset($data['projectPath']) || '' === $data['projectPath']) {
                throw BusinessLogicException::invalidRequest('Auto.js脚本必须提供项目路径');
            }
        }
    }

    /**
     * 验证参数定义.
     *
     * @param array<string, mixed> $parameters
     */
    public function validateParametersDefinition(array $parameters): void
    {
        foreach ($parameters as $paramName => $paramDef) {
            if (!is_array($paramDef)) {
                throw BusinessLogicException::invalidRequest("参数 {$paramName} 的定义必须是对象");
            }

            if (!isset($paramDef['type'])) {
                throw BusinessLogicException::invalidRequest("参数 {$paramName} 必须指定类型");
            }

            $validTypes = ['string', 'number', 'boolean', 'array', 'object'];
            if (!in_array($paramDef['type'], $validTypes, true)) {
                throw BusinessLogicException::invalidRequest("参数 {$paramName} 的类型无效");
            }

            if (isset($paramDef['required']) && !is_bool($paramDef['required'])) {
                throw BusinessLogicException::invalidRequest("参数 {$paramName} 的 required 字段必须是布尔值");
            }
        }
    }

    /**
     * 验证脚本语法.
     *
     * @return list<string>
     */
    public function validateScriptSyntax(string $content): array
    {
        $errors = [];

        $dangerousFunctions = ['eval', 'Function', 'setTimeout', 'setInterval'];
        foreach ($dangerousFunctions as $func) {
            if (1 === preg_match('/\b' . preg_quote($func, '/') . '\s*\(/', $content)) {
                $errors[] = "检测到潜在危险函数: {$func}";
            }
        }

        $openBraces = substr_count($content, '{');
        $closeBraces = substr_count($content, '}');
        if ($openBraces !== $closeBraces) {
            $errors[] = '大括号不匹配';
        }

        $openParens = substr_count($content, '(');
        $closeParens = substr_count($content, ')');
        if ($openParens !== $closeParens) {
            $errors[] = '圆括号不匹配';
        }

        return $errors;
    }
}
