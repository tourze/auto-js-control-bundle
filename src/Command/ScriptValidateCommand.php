<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Exception\ScriptValidationException;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;
use Tourze\AutoJsControlBundle\Service\ScriptManager;
use Tourze\AutoJsControlBundle\Service\ScriptValidationService;

#[AsCommand(name: self::NAME, description: '验证脚本语法和安全性', aliases: ['autojs:script:validate', 'autojs:script:check'], help: <<<'TXT'
    <info>%command.name%</info> 命令用于验证脚本的语法和安全性。

    示例：
      <comment>%command.full_name% 123</comment>                           # 验证ID为123的脚本
      <comment>%command.full_name% hello-world</comment>                   # 验证代码为hello-world的脚本
      <comment>%command.full_name% --file=/path/to/script.js</comment>     # 验证文件中的脚本
      <comment>%command.full_name% --content="console.log('Hello')"</comment> # 验证提供的脚本内容
      <comment>%command.full_name% --all</comment>                          # 验证所有脚本
      <comment>%command.full_name% --all --fix</comment>                   # 验证并尝试修复所有脚本

    验证内容：
      1. 基本语法检查
      2. 危险函数检测（eval, Function等）
      3. Auto.js API使用检查
      4. 代码风格检查（严格模式）
      5. 安全性检查

    注意：
      - 使用 --fix 选项可以尝试自动修复一些简单的问题
      - 使用 --strict 选项会执行更严格的代码检查
    TXT)]
final class ScriptValidateCommand extends Command
{
    public const NAME = 'auto-js:script:validate';

    public function __construct(
        private readonly ScriptManager $scriptManager,
        private readonly ScriptRepository $scriptRepository,
        private readonly ScriptValidationService $validationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('script', InputArgument::OPTIONAL, '脚本ID或脚本代码')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, '从文件读取脚本内容')
            ->addOption('content', 'c', InputOption::VALUE_REQUIRED, '直接提供脚本内容')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, '脚本类型: javascript, auto_js, shell', 'auto_js')
            ->addOption('all', 'a', InputOption::VALUE_NONE, '验证所有脚本')
            ->addOption('fix', null, InputOption::VALUE_NONE, '尝试自动修复一些常见问题')
            ->addOption('strict', 's', InputOption::VALUE_NONE, '严格模式验证')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Auto.js 脚本验证');

        try {
            return $this->handleValidation($input, $io);
        } catch (\Exception $e) {
            $io->error('验证脚本失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function handleValidation(InputInterface $input, SymfonyStyle $io): int
    {
        $validationTarget = $this->determineValidationTarget($input);

        return match ($validationTarget['type']) {
            'all' => $this->validateAllScripts($input, $io),
            'id' => $this->validateScriptById($this->safelyParseString($validationTarget['value']), $input, $io),
            'file' => $this->validateScriptFromFile($this->safelyParseString($validationTarget['value']), $input, $io),
            'content' => $this->validateScriptContent($this->safelyParseString($validationTarget['value']), $input, $io),
            default => $this->handleNoValidationTarget($io),
        };
    }

    /**
     * @return array{type: string, value: mixed}
     */
    private function determineValidationTarget(InputInterface $input): array
    {
        if (true === $input->getOption('all')) {
            return ['type' => 'all', 'value' => null];
        }

        $scriptArg = $input->getArgument('script');
        if (null !== $scriptArg) {
            return ['type' => 'id', 'value' => $scriptArg];
        }

        $file = $input->getOption('file');
        if (null !== $file) {
            return ['type' => 'file', 'value' => $file];
        }

        $content = $input->getOption('content');
        if (null !== $content) {
            return ['type' => 'content', 'value' => $content];
        }

        return ['type' => 'none', 'value' => null];
    }

    private function handleNoValidationTarget(SymfonyStyle $io): int
    {
        $io->error('必须指定脚本ID、脚本代码、文件路径或使用 --all 选项');

        return Command::FAILURE;
    }

    private function validateAllScripts(InputInterface $input, SymfonyStyle $io): int
    {
        $scripts = $this->scriptRepository->findBy(['valid' => true]);

        if ([] === $scripts) {
            $io->warning('没有找到任何脚本');

            return Command::SUCCESS;
        }

        $validation = $this->performBatchValidation($scripts, $input, $io);
        $this->displayBatchResults($validation['results'], $validation['totalErrors'], $validation['totalWarnings'], $io);

        return ($validation['totalErrors'] > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param array<Script> $scripts
     *
     * @return array{results: array<array{script: Script, result: array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}}>, totalErrors: int, totalWarnings: int}
     */
    private function performBatchValidation(array $scripts, InputInterface $input, SymfonyStyle $io): array
    {
        $io->progressStart(count($scripts));

        $validation = [
            'results' => [],
            'totalErrors' => 0,
            'totalWarnings' => 0,
        ];

        foreach ($scripts as $script) {
            $io->progressAdvance();
            $validation = $this->validateSingleScript($script, $input, $validation);
        }

        $io->progressFinish();

        return $validation;
    }

    /**
     * @param array{results: array<array{script: Script, result: array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}}>, totalErrors: int, totalWarnings: int} $validation
     * @return array{results: array<array{script: Script, result: array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}}>, totalErrors: int, totalWarnings: int}
     */
    private function validateSingleScript(Script $script, InputInterface $input, array $validation): array
    {
        $result = $this->validateScript(
            $script->getContent() ?? '',
            $script->getScriptType()->value,
            (bool) $input->getOption('strict')
        );

        $errorCount = count($result['errors']);
        $warningCount = count($result['warnings'] ?? []);

        $validation['totalErrors'] += $errorCount;
        $validation['totalWarnings'] += $warningCount;

        if ($errorCount > 0 || $warningCount > 0) {
            $validation['results'][] = [
                'script' => $script,
                'result' => $result,
            ];
        }

        if ((bool) $input->getOption('fix') && [] !== $result['suggestions']) {
            $this->applyFixes($script, $result['suggestions']);
        }

        return $validation;
    }

    private function validateScriptById(string $scriptId, InputInterface $input, SymfonyStyle $io): int
    {
        $script = $this->findScriptByIdOrCode($scriptId);
        $io->section(sprintf('验证脚本: %s (%s)', $script->getName(), $script->getCode()));

        $result = $this->validateAndDisplayScript($script, $input, $io);
        $this->handleFixOption($script, $result, $input, $io);

        return $result['valid'] ? Command::SUCCESS : Command::FAILURE;
    }

    private function findScriptByIdOrCode(string $scriptId): Script
    {
        $script = null;

        if (is_numeric($scriptId)) {
            $script = $this->scriptRepository->find((int) $scriptId);
        }

        if (null === $script) {
            $script = $this->scriptRepository->findOneBy(['code' => $scriptId]);
        }

        if (null === $script) {
            throw ScriptValidationException::scriptNotFound($scriptId);
        }

        return $script;
    }

    /**
     * @return array{valid: bool, errors: array<string>, warnings?: array<string>, suggestions?: array<string>}
     */
    /**
     * @return array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}
     */
    private function validateAndDisplayScript(Script $script, InputInterface $input, SymfonyStyle $io): array
    {
        $result = $this->validateScript(
            $script->getContent() ?? '',
            $script->getScriptType()->value,
            (bool) $input->getOption('strict')
        );

        $this->displayValidationResult($result, $io);

        return $result;
    }

    /**
     * @param array{valid: bool, errors: array<string>, warnings?: array<string>, suggestions?: array<string>} $result
     */
    /**
     * @param array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>} $result
     */
    private function handleFixOption(Script $script, array $result, InputInterface $input, SymfonyStyle $io): void
    {
        if ((bool) $input->getOption('fix') && [] !== $result['suggestions']) {
            if ($io->confirm('是否应用建议的修复？', true)) {
                $this->applyFixes($script, $result['suggestions']);
            }
        }
    }

    private function validateScriptFromFile(string $file, InputInterface $input, SymfonyStyle $io): int
    {
        if (!file_exists($file)) {
            throw ScriptValidationException::fileNotFound($file);
        }

        $content = file_get_contents($file);
        if (false === $content) {
            throw ScriptValidationException::fileNotReadable($file);
        }
        $io->section(sprintf('验证文件: %s', $file));

        return $this->validateScriptContent($content, $input, $io);
    }

    private function validateScriptContent(string $content, InputInterface $input, SymfonyStyle $io): int
    {
        $type = $this->safelyParseString($input->getOption('type'));
        $io->section(sprintf('验证脚本内容 (类型: %s)', $type));

        $result = $this->validateScript($content, $type, (bool) $input->getOption('strict'));
        $this->displayValidationResult($result, $io);

        return $result['valid'] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}
     */
    private function validateScript(string $content, string $type, bool $strict = false): array
    {
        $result = $this->getBaseValidationResult($content, $type);

        if ($strict) {
            $result = $this->performStrictValidation($content, $type, $result);
        }

        $result = $this->addCodeStyleChecks($content, $type, $result);
        $result = $this->addPerformanceChecks($content, $type, $result);

        return $this->finalizeValidationResult($result);
    }

    /**
     * @return array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}
     */
    private function getBaseValidationResult(string $content, string $type): array
    {
        $baseResult = $this->scriptManager->validateScriptContent($content, $type);

        return [
            'valid' => $this->safelyParseBool($baseResult['valid'] ?? (($baseResult['errors'] ?? []) === [])),
            'errors' => $this->safelyParseArray($baseResult['errors'] ?? []),
            'warnings' => [],
            'suggestions' => [],
        ];
    }

    /**
     * @param array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>} $result
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}
     */
    private function addCodeStyleChecks(string $content, string $type, array $result): array
    {
        $styleIssues = $this->validationService->checkCodeStyle($content, $type);
        if ([] !== $styleIssues) {
            $result['warnings'] = array_merge($result['warnings'], $styleIssues);
        }

        return $result;
    }

    /**
     * @param array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>} $result
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}
     */
    private function addPerformanceChecks(string $content, string $type, array $result): array
    {
        $performanceIssues = $this->validationService->checkPerformance($content, $type);
        if ([] !== $performanceIssues) {
            $result['suggestions'] = array_merge($result['suggestions'], $performanceIssues);
        }

        return $result;
    }

    /**
     * @param array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>} $result
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}
     */
    private function finalizeValidationResult(array $result): array
    {
        $result['valid'] = [] === $result['errors'];

        return $result;
    }

    /**
     * @param array{errors: array<string>, warnings: array<string>, suggestions: array<string>, valid: bool} $result
     *
     * @return array{errors: array<string>, warnings: array<string>, suggestions: array<string>, valid: bool}
     */
    private function performStrictValidation(string $content, string $type, array $result): array
    {
        $warnings = $this->validationService->performStrictValidation($content, $type);
        if ([] !== $warnings) {
            $result['warnings'] = array_merge($result['warnings'], $warnings);
        }

        return $result;
    }

    /**
     * @param array{valid: bool, errors: array<string>, warnings?: array<string>, suggestions?: array<string>} $result
     */
    private function displayValidationResult(array $result, SymfonyStyle $io): void
    {
        $this->displayValidationStatus($result['valid'], $io);
        $this->displayErrors($result['errors'] ?? [], $io);
        $this->displayWarnings($result['warnings'] ?? [], $io);
        $this->displaySuggestions($result['suggestions'] ?? [], $io);
    }

    private function displayValidationStatus(bool $valid, SymfonyStyle $io): void
    {
        if ($valid) {
            $io->success('脚本验证通过！');
        } else {
            $io->error('脚本验证失败！');
        }
    }

    /**
     * @param array<string> $errors
     */
    private function displayErrors(array $errors, SymfonyStyle $io): void
    {
        if ([] !== $errors) {
            $io->section('错误');
            foreach ($errors as $error) {
                $io->writeln('<fg=red>✗</> ' . $error);
            }
        }
    }

    /**
     * @param array<string> $warnings
     */
    private function displayWarnings(array $warnings, SymfonyStyle $io): void
    {
        if ([] !== $warnings) {
            $io->section('警告');
            foreach ($warnings as $warning) {
                $io->writeln('<fg=yellow>⚠</> ' . $warning);
            }
        }
    }

    /**
     * @param array<string> $suggestions
     */
    private function displaySuggestions(array $suggestions, SymfonyStyle $io): void
    {
        if ([] !== $suggestions) {
            $io->section('性能建议');
            foreach ($suggestions as $suggestion) {
                $io->writeln('<fg=blue>💡</> ' . $suggestion);
            }
        }
    }

    /**
     * @param array<array{script: Script, result: array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}}> $scriptResults
     */
    private function displayBatchResults(array $scriptResults, int $totalErrors, int $totalWarnings, SymfonyStyle $io): void
    {
        $io->newLine();

        if ([] === $scriptResults) {
            $io->success('所有脚本验证通过！');

            return;
        }

        $this->displayValidationSummary($scriptResults, $totalErrors, $totalWarnings, $io);
        $this->displayProblematicScripts($scriptResults, $io);
    }

    /**
     * @param array<array{script: Script, result: array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}}> $scriptResults
     */
    private function displayValidationSummary(array $scriptResults, int $totalErrors, int $totalWarnings, SymfonyStyle $io): void
    {
        $io->section('验证结果汇总');
        $io->table(
            ['指标', '数量'],
            [
                ['验证的脚本总数', (string) count($scriptResults)],
                ['错误总数', sprintf('<fg=red>%d</>', $totalErrors)],
                ['警告总数', sprintf('<fg=yellow>%d</>', $totalWarnings)],
            ]
        );
    }

    /**
     * @param array<array{script: Script, result: array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>}}> $scriptResults
     */
    private function displayProblematicScripts(array $scriptResults, SymfonyStyle $io): void
    {
        $io->section('有问题的脚本');

        foreach ($scriptResults as $item) {
            $this->displayScriptIssue($item['script'], $item['result'], $io);
        }

        if (!$io->isVerbose()) {
            $io->note('使用 -v 选项查看详细错误信息');
        }
    }

    /**
     * @param array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>} $result
     */
    private function displayScriptIssue(Script $script, array $result, SymfonyStyle $io): void
    {
        $io->writeln(sprintf(
            '<fg=cyan>%s</> (%s) - 错误: <fg=red>%d</>, 警告: <fg=yellow>%d</>',
            $script->getName(),
            $script->getCode(),
            count($result['errors']),
            count($result['warnings'])
        ));

        if ($io->isVerbose()) {
            $this->displayVerboseIssues($result, $io);
        }
    }

    /**
     * @param array{valid: bool, errors: array<string>, warnings: array<string>, suggestions: array<string>} $result
     */
    private function displayVerboseIssues(array $result, SymfonyStyle $io): void
    {
        foreach ($result['errors'] as $error) {
            $io->writeln('  <fg=red>✗</> ' . $error);
        }
        foreach ($result['warnings'] as $warning) {
            $io->writeln('  <fg=yellow>⚠</> ' . $warning);
        }
    }

    /**
     * @param array<string> $suggestions
     */
    /**
     * @param array<string> $suggestions
     */
    private function applyFixes(Script $script, array $suggestions): void
    {
        // TODO: 实现自动修复功能
        // 暂时移除 $io 参数，因为在批量验证时没有传递
        // 未来可以考虑记录修复日志而不是直接输出
    }

    /**
     * 安全地将混合类型转换为字符串
     */
    private function safelyParseString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * 安全地将混合类型转换为布尔值
     */
    private function safelyParseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return $value > 0;
        }

        return false;
    }

    /**
     * 安全地将混合类型转换为数组
     *
     * @return array<string>
     */
    private function safelyParseArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->safelyParseString($item), $value);
        }

        return [];
    }
}
