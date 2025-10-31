<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Service;

use Tourze\AutoJsControlBundle\Exception\ScriptValidationException;

/**
 * 脚本验证服务，提供代码风格和性能检查功能.
 */
class ScriptValidationService
{
    /**
     * 验证脚本内容.
     *
     * @throws ScriptValidationException
     */
    public function validate(string $script): bool
    {
        // 检查脚本是否为空
        if ('' === trim($script)) {
            throw new ScriptValidationException('脚本内容不能为空');
        }

        // 检查危险函数
        if ($this->containsDangerousFunctions($script)) {
            throw new ScriptValidationException('脚本包含危险函数');
        }

        // 检查基本语法错误
        if ($this->hasBasicSyntaxErrors($script)) {
            throw new ScriptValidationException('脚本语法错误');
        }

        // 检查脚本复杂度
        if ($this->isScriptTooComplex($script)) {
            throw new ScriptValidationException('脚本复杂度过高');
        }

        // 检查脚本大小
        if ($this->isScriptTooLarge($script)) {
            throw new ScriptValidationException('脚本文件过大');
        }

        // 检查禁用关键字
        if ($this->containsProhibitedKeywords($script)) {
            throw new ScriptValidationException('脚本包含禁用关键字');
        }

        return true;
    }

    /** @return string[] */
    public function checkCodeStyle(string $content, string $type): array
    {
        $issues = [];

        $issues = $this->checkLineLengths($content, $issues);
        $issues = $this->checkExcessiveEmptyLines($content, $issues);

        return $this->checkTypeSpecificStyle($content, $type, $issues);
    }

    /**
     * @return string[]
     */
    public function checkPerformance(string $content, string $type): array
    {
        $checker = $this->getPerformanceChecker($type);

        return null !== $checker ? $checker($content) : [];
    }

    /**
     * @return string[]
     */
    public function performStrictValidation(string $content, string $type): array
    {
        $validator = $this->getStrictValidator($type);

        return null !== $validator ? $validator($content) : [];
    }

    /**
     * @param array<string> $issues
     * @return array<string>
     */
    private function checkLineLengths(string $content, array $issues): array
    {
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            if (strlen($line) > 120) {
                $issues[] = sprintf('第 %d 行超过120个字符，建议分行', $lineNum + 1);
            }
        }

        return $issues;
    }

    /**
     * @param array<string> $issues
     * @return array<string>
     */
    private function checkExcessiveEmptyLines(string $content, array $issues): array
    {
        if (1 === preg_match('/\n\n\n+/', $content)) {
            $issues[] = '检测到多余的空行（超过2个连续空行）';
        }

        return $issues;
    }

    /**
     * @param array<string> $issues
     * @return array<string>
     */
    private function checkTypeSpecificStyle(string $content, string $type, array $issues): array
    {
        $checker = $this->getStyleChecker($type);
        if (null !== $checker) {
            $typeIssues = $checker($content);
            $issues = array_merge($issues, $typeIssues);
        }

        return $issues;
    }

    /**
     * @return callable(string): array<string>|null
     */
    private function getStyleChecker(string $type): ?callable
    {
        return match ($type) {
            'javascript', 'auto_js' => $this->getJavaScriptStyleChecker(),
            default => null,
        };
    }

    /**
     * @return callable(string): string[]
     */
    private function getJavaScriptStyleChecker(): callable
    {
        return function (string $content): array {
            $issues = [];

            if (1 === preg_match('/^\t/m', $content)) {
                $issues[] = '建议使用空格而不是制表符进行缩进';
            }

            if (1 === preg_match('/function\s+[A-Z]\w*/', $content)) {
                $issues[] = '普通函数名应该使用小驼峰命名法';
            }

            return $issues;
        };
    }

    /**
     * @return callable(string): string[]|null
     */
    private function getPerformanceChecker(string $type): ?callable
    {
        return match ($type) {
            'javascript', 'auto_js' => fn (string $content) => $this->checkJavaScriptPerformance($content, $type),
            default => null,
        };
    }

    /**
     * @return string[]
     */
    private function checkJavaScriptPerformance(string $content, string $type): array
    {
        $suggestions = [];

        if ($this->hasDomOperationsInLoop($content)) {
            $suggestions[] = '在循环中进行 DOM 查询可能影响性能，建议在循环外缓存元素';
        }

        if ($this->hasExcessiveStringConcatenation($content)) {
            $suggestions[] = '大量字符串拼接建议使用数组 join 方法';
        }

        if ('auto_js' === $type && $this->hasLongSleep($content)) {
            $suggestions[] = '检测到长时间 sleep，可能影响脚本响应性';
        }

        return $suggestions;
    }

    private function hasDomOperationsInLoop(string $content): bool
    {
        return (bool) preg_match('/for\s*\([^)]+\)\s*{[^}]*\.(getElementById|querySelector)/', $content);
    }

    private function hasExcessiveStringConcatenation(string $content): bool
    {
        return substr_count($content, '+=') > 5 && false !== strpos($content, 'String');
    }

    private function hasLongSleep(string $content): bool
    {
        return (bool) preg_match('/sleep\s*\(\s*\d{4,}/', $content);
    }

    /**
     * @return callable(string): string[]|null
     */
    private function getStrictValidator(string $type): ?callable
    {
        return match ($type) {
            'javascript', 'auto_js' => $this->getJavaScriptStrictValidator(),
            'shell' => $this->getShellStrictValidator(),
            default => null,
        };
    }

    /**
     * @return callable(string): string[]
     */
    private function getJavaScriptStrictValidator(): callable
    {
        return function (string $content): array {
            $warnings = [];

            if (1 === preg_match('/\b(?<!var |let |const |function )\w+\s*=/', $content)) {
                $warnings[] = '检测到可能未声明的变量';
            }

            if ($this->hasLooseEquality($content)) {
                $warnings[] = '建议使用 === 代替 == 进行严格比较';
            }

            if (1 !== preg_match('/;\s*$/', trim($content))) {
                $warnings[] = '语句末尾缺少分号';
            }

            return $warnings;
        };
    }

    /**
     * @return callable(string): string[]
     */
    private function getShellStrictValidator(): callable
    {
        return function (string $content): array {
            $warnings = [];

            if (1 === preg_match('/\$\w+(?!\w)(?!["\'])/', $content)) {
                $warnings[] = '检测到未引用的变量，建议使用 "${var}" 格式';
            }

            return $warnings;
        };
    }

    private function hasLooseEquality(string $content): bool
    {
        return false !== strpos($content, '==') && false === strpos($content, '===');
    }

    /**
     * 检查是否包含危险函数.
     */
    private function containsDangerousFunctions(string $script): bool
    {
        $dangerousFunctions = [
            'shell',
            'exec',
            'system',
            'passthru',
            'popen',
            'proc_open',
            'file_get_contents',
            'file_put_contents',
            'unlink',
            'rmdir',
        ];

        foreach ($dangerousFunctions as $func) {
            if (false !== strpos($script, $func . '(')) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查基本语法错误.
     */
    private function hasBasicSyntaxErrors(string $script): bool
    {
        // 检查引号匹配
        if (!$this->areQuotesBalanced($script)) {
            return true;
        }

        // 检查括号匹配
        if (!$this->areBracketsBalanced($script)) {
            return true;
        }

        return false;
    }

    /**
     * 检查脚本是否过于复杂
     */
    private function isScriptTooComplex(string $script): bool
    {
        // 检查嵌套循环深度
        $forCount = substr_count($script, 'for(');
        $whileCount = substr_count($script, 'while(');

        return ($forCount + $whileCount) > 50;
    }

    /**
     * 检查脚本大小.
     */
    private function isScriptTooLarge(string $script): bool
    {
        return strlen($script) > 200000; // 200KB
    }

    /**
     * 检查禁用关键字.
     */
    private function containsProhibitedKeywords(string $script): bool
    {
        $prohibitedKeywords = [
            'eval(',
            'Function(',
            'setTimeout(',
            'setInterval(',
        ];

        foreach ($prohibitedKeywords as $keyword) {
            if (false !== strpos($script, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查引号是否平衡
     * 不考虑并发：脚本验证是纯计算操作，无状态变更.
     */
    private function areQuotesBalanced(string $script): bool
    {
        $singleQuotes = substr_count($script, "'") - substr_count($script, "\\'");
        $doubleQuotes = substr_count($script, '"') - substr_count($script, '\"');

        return 0 === $singleQuotes % 2 && 0 === $doubleQuotes % 2;
    }

    /**
     * 检查括号是否平衡
     * 不考虑并发：脚本验证是纯计算操作，无状态变更.
     */
    private function areBracketsBalanced(string $script): bool
    {
        $openParens = substr_count($script, '(');
        $closeParens = substr_count($script, ')');
        $openBraces = substr_count($script, '{');
        $closeBraces = substr_count($script, '}');
        $openBrackets = substr_count($script, '[');
        $closeBrackets = substr_count($script, ']');

        return $openParens === $closeParens
               && $openBraces === $closeBraces
               && $openBrackets === $closeBrackets;
    }
}
